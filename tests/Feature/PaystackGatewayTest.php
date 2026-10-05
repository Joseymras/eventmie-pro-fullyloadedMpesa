<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaystackWebhook;
use App\Models\Booking;
use App\Models\PaystackTransaction;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaystackGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('paystack.enabled', true);
        config()->set('paystack.secretKey', 'sk_test_placeholder');
        config()->set('paystack.publicKey', 'pk_test_placeholder');
        config()->set('paystack.paymentUrl', 'https://api.paystack.co');
        config()->set('queue.default', 'database');
    }

    public function test_it_normalizes_common_kenyan_phone_formats_and_rejects_invalid_numbers(): void
    {
        $service = app(PaystackService::class);

        foreach (['0712345678', '712345678', '254712345678', '+254 712 345 678', '0112345678'] as $phone) {
            $this->assertSame('+254'.substr(preg_replace('/\D/', '', $phone), -9), $service->normalizeKenyanPhone($phone));
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->normalizeKenyanPhone('0900123');
    }

    public function test_it_initializes_redirect_checkout_with_expected_payload(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc',
                    'reference' => 'wz_98765_checkout',
                ],
            ]),
        ]);

        $booking = [$this->bookingData()];
        $result = app(PaystackService::class)->initializeTransaction(
            ['order_number' => 98765, 'price' => 2500],
            $booking,
            ['email' => 'user@example.test']
        );

        $this->assertSame('wz_98765_checkout', $result['reference']);
        $this->assertDatabaseHas('paystack_transactions', [
            'reference' => 'wz_98765_checkout',
            'amount' => 250000,
            'currency' => 'KES',
            'order_id' => '98765',
            'status' => 'pending',
        ]);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request->hasHeader('Authorization')
                && $payload['email'] === 'user@example.test'
                && $payload['amount'] === 250000
                && $payload['currency'] === 'KES'
                && in_array('mobile_money', $payload['channels'], true);
        });
    }

    public function test_it_starts_a_mobile_money_charge_and_masks_the_phone(): void
    {
        Http::fake([
            'https://api.paystack.co/charge' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'wz_98765_mpesa',
                    'status' => 'send_otp',
                    'display_text' => 'Check your phone',
                ],
            ]),
        ]);

        $result = app(PaystackService::class)->chargeMobileMoney(
            ['order_number' => 98765, 'price' => 2500],
            [$this->bookingData()],
            'user@example.test',
            '0712 345 678'
        );

        $this->assertSame('wz_98765_mpesa', $result['reference']);
        $this->assertDatabaseHas('paystack_transactions', [
            'reference' => 'wz_98765_mpesa',
            'channel' => 'mobile_money',
            'masked_phone' => '+254*****78',
        ]);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://api.paystack.co/charge'
                && $payload['amount'] === 250000
                && $payload['mobile_money']['provider'] === 'mpesa'
                && $payload['mobile_money']['phone'] === '+254712345678';
        });
    }

    public function test_it_records_a_failed_mobile_money_charge(): void
    {
        Http::fake([
            'https://api.paystack.co/charge' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'wz_98765_failed',
                    'status' => 'failed',
                ],
            ]),
        ]);

        $result = app(PaystackService::class)->chargeMobileMoney(
            ['order_number' => 98765, 'price' => 2500],
            [$this->bookingData()],
            'user@example.test',
            '0712345678'
        );

        $this->assertSame('failed', $result['status']);
        $this->assertDatabaseHas('paystack_transactions', [
            'reference' => 'wz_98765_failed',
            'status' => 'failed',
        ]);
    }

    public function test_it_rejects_a_webhook_without_a_valid_signature(): void
    {
        $response = $this->postJson('/paystack/webhook', [
            'event' => 'charge.success',
            'data' => ['reference' => 'wz_98765_mpesa'],
        ], ['x-paystack-signature' => 'invalid-signature']);

        $response->assertForbidden();
    }

    public function test_health_route_reports_application_database_and_queue_status(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'app' => true,
                    'database' => true,
                    'queue' => true,
                ],
            ]);
    }

    public function test_a_valid_duplicate_webhook_is_saved_once_and_queued(): void
    {
        Queue::fake();
        $this->createTransaction();

        $body = json_encode([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'wz_98765_mpesa',
                'status' => 'success',
                'amount' => 250000,
                'currency' => 'KES',
                'customer' => ['email' => 'must-not-be-stored@example.test'],
            ],
        ]);
        $signature = hash_hmac('sha512', $body, 'sk_test_placeholder');

        foreach ([1, 2] as $attempt) {
            $this->call('POST', '/paystack/webhook', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ], $body)->assertOk();
        }

        $this->assertDatabaseCount('paystack_transactions', 1);
        $transaction = PaystackTransaction::first();
        $this->assertSame('wz_98765_mpesa', $transaction->webhook_payload['reference']);
        $this->assertArrayNotHasKey('customer', $transaction->webhook_payload);
        Queue::assertPushed(ProcessPaystackWebhook::class, 2);
    }

    public function test_it_verifies_amount_and_currency_before_marking_a_booking_paid(): void
    {
        $booking = Booking::create(array_merge($this->bookingData(), [
            'price' => 2500,
            'ticket_price' => 2500,
            'event_category' => 'Music',
            'is_paid' => 0,
            'status' => 0,
        ]));
        $this->createTransaction();

        Http::fake([
            'https://api.paystack.co/transaction/verify/wz_98765_mpesa' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'wz_98765_mpesa',
                    'amount' => 249999,
                    'currency' => 'KES',
                    'status' => 'success',
                ],
            ]),
        ]);

        $result = app(PaystackService::class)->verifyTransaction('wz_98765_mpesa', 2500, 'KES');

        $this->assertFalse($result['verified']);
        $this->assertSame('mismatch', PaystackTransaction::first()->status);
        $this->assertSame(0, (int) $booking->fresh()->is_paid);
    }

    public function test_it_marks_a_payment_paid_only_after_verification_matches(): void
    {
        $booking = Booking::create(array_merge($this->bookingData(), [
            'price' => 2500,
            'ticket_price' => 2500,
            'event_category' => 'Music',
            'is_paid' => 0,
            'status' => 0,
        ]));
        $this->createTransaction();

        Http::fake([
            'https://api.paystack.co/transaction/verify/wz_98765_mpesa' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'wz_98765_mpesa',
                    'amount' => 250000,
                    'currency' => 'KES',
                    'status' => 'success',
                    'customer' => ['id' => 'customer_1'],
                ],
            ]),
        ]);

        $result = app(PaystackService::class)->verifyTransaction('wz_98765_mpesa', 2500, 'KES');

        $this->assertTrue($result['verified']);
        $this->assertSame('success', PaystackTransaction::first()->status);
        $this->assertSame(1, (int) $booking->fresh()->is_paid);
    }

    public function test_it_expires_stale_pending_payments_after_a_final_verification_attempt(): void
    {
        $transaction = $this->createTransaction();
        $transaction->update(['expires_at' => now()->subMinute()]);
        Http::fake([
            'https://api.paystack.co/transaction/verify/wz_98765_mpesa' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => 'wz_98765_mpesa',
                    'amount' => 250000,
                    'currency' => 'KES',
                    'status' => 'pending',
                ],
            ]),
        ]);

        $this->artisan('paystack:reconcile')
            ->expectsOutput('Checked 1 pending payment(s); expired 1 stale attempt(s).')
            ->assertExitCode(0);

        $this->assertSame('expired', $transaction->fresh()->status);
    }

    private function createTransaction(): PaystackTransaction
    {
        return PaystackTransaction::create([
            'reference' => 'wz_98765_mpesa',
            'order_id' => '98765',
            'user_id' => 2,
            'amount' => 250000,
            'currency' => 'KES',
            'channel' => 'mobile_money',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function bookingData(): array
    {
        return [
            'customer_id' => 2,
            'organiser_id' => 1,
            'event_id' => 15,
            'ticket_id' => 5,
            'quantity' => 1,
            'status' => 1,
            'is_paid' => 0,
            'common_order' => 98765,
            'currency' => 'KES',
            'price' => 2500,
            'ticket_price' => 2500,
            'net_price' => 2500,
            'event_title' => 'Test Event',
            'event_category' => 'Music',
            'ticket_title' => 'General Admission',
            'customer_name' => 'Test User',
            'customer_email' => 'user@example.test',
            'payment_type' => 'online',
        ];
    }
}
