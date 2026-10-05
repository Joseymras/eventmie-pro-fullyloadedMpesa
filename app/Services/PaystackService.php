<?php

namespace App\Services;

use App\Jobs\ProcessPaystackWebhook;
use App\Models\Booking;
use App\Models\PaystackTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackService
{
    public function isEnabled(): bool
    {
        return (bool) config('paystack.enabled')
            || filter_var(setting('apps.paystack_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public function getPublicKey(): string
    {
        return trim((string) (config('paystack.publicKey') ?: setting('apps.paystack_public_key') ?: ''));
    }

    public function isConfigured(): bool
    {
        return $this->getPublicKey() !== '' && $this->getSecretKey() !== '';
    }

    protected function getSecretKey(): string
    {
        return trim((string) (config('paystack.secretKey') ?: setting('apps.paystack_secret_key') ?: ''));
    }

    protected function getApiBaseUrl(): string
    {
        return rtrim((string) config('paystack.paymentUrl', 'https://api.paystack.co'), '/');
    }

    public function normalizeKenyanPhone(string $phone): string
    {
        $number = preg_replace('/[\s().-]+/', '', trim($phone));

        if (strpos($number, '+254') === 0) {
            $national = substr($number, 4);
        } elseif (strpos($number, '254') === 0) {
            $national = substr($number, 3);
        } elseif (strpos($number, '0') === 0) {
            $national = substr($number, 1);
        } else {
            $national = $number;
        }

        if (!preg_match('/^[17][0-9]{8}$/', $national)) {
            throw new \InvalidArgumentException('Enter a valid Kenyan Safaricom number.');
        }

        return '+254'.$national;
    }

    public function generateReference(array $order = [], ?string $suffix = null): string
    {
        $orderNumber = $order['order_number'] ?? $order['common_order'] ?? null;
        $parts = ['wz', $orderNumber ?: now()->timestamp, $suffix ?: Str::random(12)];

        return implode('_', $parts);
    }

    public function initializeTransaction(array $order = [], array $booking = [], array $user = []): array
    {
        $this->assertConfigured();

        $email = $user['email'] ?? data_get($booking, '0.customer_email');
        $currency = strtoupper((string) (data_get($booking, '0.currency') ?: 'KES'));
        $amount = $this->toMinorUnits($order['price'] ?? 0);
        if ($amount < 1) {
            throw new \InvalidArgumentException('The payment amount must be greater than zero.');
        }
        $commonOrder = data_get($booking, '0.common_order');
        $reference = $this->generateReference($order, Str::random(8));
        $metadata = $this->metadata($booking, $commonOrder);
        $transaction = $this->savePendingTransaction(
            $reference,
            $commonOrder,
            data_get($booking, '0.customer_id'),
            $amount,
            $currency,
            'redirect'
        );

        try {
            $response = $this->request()->post($this->getApiBaseUrl().'/transaction/initialize', [
                'email' => $email,
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'callback_url' => route('paystack.callback', [], true),
                'channels' => ['mobile_money', 'card'],
                'metadata' => $metadata,
            ]);
            $data = $this->validatedResponse($response, 'Unable to start Paystack checkout.');
        } catch (\Illuminate\Http\Client\ConnectionException|\RuntimeException $exception) {
            $transaction->update(['status' => 'failed']);
            throw $exception;
        }

        if (empty($data['authorization_url']) || empty($data['reference'])) {
            $transaction->update(['status' => 'failed']);
            throw new \RuntimeException('Paystack returned an incomplete checkout response.');
        }

        if ($data['reference'] !== $reference) {
            $transaction->update(['reference' => $data['reference']]);
        }

        return [
            'status' => true,
            'reference' => $data['reference'],
            'authorization_url' => $data['authorization_url'],
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => $metadata,
        ];
    }

    public function chargeMobileMoney(array $order, array $booking, string $email, string $phone): array
    {
        $this->assertConfigured();

        $normalizedPhone = $this->normalizeKenyanPhone($phone);
        $currency = strtoupper((string) (data_get($booking, '0.currency') ?: 'KES'));
        if ($currency !== 'KES') {
            throw new \InvalidArgumentException('M-Pesa checkout is available for KES orders only.');
        }

        $amount = $this->toMinorUnits($order['price'] ?? 0);
        if ($amount < 1) {
            throw new \InvalidArgumentException('The payment amount must be greater than zero.');
        }

        $commonOrder = data_get($booking, '0.common_order');
        $reference = $this->generateReference($order, Str::random(8));
        $transaction = $this->savePendingTransaction(
            $reference,
            $commonOrder,
            data_get($booking, '0.customer_id'),
            $amount,
            $currency,
            'mobile_money',
            $this->maskPhone($normalizedPhone)
        );

        try {
            $response = $this->request()->post($this->getApiBaseUrl().'/charge', [
                'email' => $email,
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'mobile_money' => [
                    'phone' => $normalizedPhone,
                    'provider' => 'mpesa',
                ],
                'metadata' => $this->metadata($booking, $commonOrder),
            ]);
            $data = $this->validatedResponse($response, 'Unable to send the M-Pesa payment prompt.');
        } catch (\Illuminate\Http\Client\ConnectionException|\RuntimeException $exception) {
            $transaction->update(['status' => 'failed']);
            throw $exception;
        }

        $gatewayReference = $data['reference'] ?? $reference;
        $initialStatus = strtolower((string) ($data['status'] ?? 'pending'));
        $localStatus = in_array($initialStatus, ['failed', 'abandoned'], true) ? 'failed' : 'pending';
        $transaction->update([
            'reference' => $gatewayReference,
            'status' => $localStatus,
            'gateway_response' => $this->safeGatewayResponse($data),
        ]);

        return [
            'reference' => $gatewayReference,
            'status' => $localStatus,
            'display_text' => $data['display_text'] ?? null,
        ];
    }

    public function verifyTransaction(string $reference, $expectedAmount = null, ?string $expectedCurrency = null): array
    {
        $transaction = PaystackTransaction::where('reference', $reference)->first();
        if (!$transaction) {
            return ['verified' => false, 'reference' => $reference, 'message' => 'Payment reference was not found.'];
        }

        $expectedAmount = $expectedAmount === null ? $transaction->amount : $this->toMinorUnits($expectedAmount);
        $expectedCurrency = strtoupper((string) ($expectedCurrency ?: $transaction->currency));
        $response = $this->request()->get($this->getApiBaseUrl().'/transaction/verify/'.rawurlencode($reference));
        $data = $this->validatedResponse($response, 'Unable to verify this payment right now.');

        if (strtolower((string) ($data['status'] ?? '')) !== 'success') {
            $transaction->update([
                'status' => in_array(strtolower((string) ($data['status'] ?? '')), ['failed', 'abandoned'], true) ? 'failed' : 'pending',
                'gateway_response' => $this->safeGatewayResponse($data),
            ]);

            return ['verified' => false, 'reference' => $reference, 'message' => 'Payment has not been completed.'];
        }

        if ((int) ($data['amount'] ?? 0) !== (int) $expectedAmount
            || strtoupper((string) ($data['currency'] ?? '')) !== $expectedCurrency
            || (string) ($data['reference'] ?? '') !== $reference) {
            $transaction->update([
                'status' => 'mismatch',
                'gateway_response' => $this->safeGatewayResponse($data),
            ]);

            return ['verified' => false, 'reference' => $reference, 'message' => 'Payment amount or currency did not match the order.'];
        }

        DB::transaction(function () use ($transaction, $data) {
            $locked = PaystackTransaction::whereKey($transaction->id)->lockForUpdate()->first();
            if ($locked->status === 'success') {
                return;
            }

            $locked->update([
                'status' => 'success',
                'channel' => $data['channel'] ?? $locked->channel,
                'gateway_response' => $this->safeGatewayResponse($data),
            ]);

            if ($locked->order_id) {
                Booking::where('common_order', $locked->order_id)->update([
                    'is_paid' => 1,
                    'status' => 1,
                ]);
            }
        });

        return [
            'verified' => true,
            'reference' => $reference,
            'amount' => (int) $data['amount'],
            'currency' => $expectedCurrency,
            'message' => 'Payment verified.',
            'data' => $data,
        ];
    }

    public function handleWebhook(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('x-paystack-signature');
        $secretKey = $this->getSecretKey();

        if ($secretKey === '' || $signature === ''
            || !hash_equals(hash_hmac('sha512', $payload, $secretKey), $signature)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature.'], 403);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['event']) || empty($event['data']['reference'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid event payload.'], 400);
        }

        $reference = (string) $event['data']['reference'];
        $transaction = PaystackTransaction::where('reference', $reference)->first();
        if (!$transaction) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $safePayload = [
            'event' => $event['event'],
            'reference' => $reference,
            'status' => $event['data']['status'] ?? null,
            'amount' => $event['data']['amount'] ?? null,
            'currency' => $event['data']['currency'] ?? null,
        ];

        $transaction->update(['webhook_payload' => $safePayload]);
        ProcessPaystackWebhook::dispatch($reference, (string) $event['event']);

        return response()->json(['status' => 'accepted'], 200);
    }

    public function processWebhook(string $reference, string $eventName): void
    {
        $transaction = PaystackTransaction::where('reference', $reference)->first();
        if (!$transaction || $transaction->status === 'success') {
            return;
        }

        if ($eventName === 'charge.failed') {
            $transaction->update(['status' => 'failed']);
            return;
        }

        if (in_array($eventName, ['refund.processed', 'refund.success'], true)) {
            $transaction->update(['status' => 'refunded']);
            return;
        }

        if ($eventName === 'charge.success') {
            $this->verifyTransaction($reference);
        }
    }

    public function maskPhone(string $phone): string
    {
        return substr($phone, 0, 4).'*****'.substr($phone, -2);
    }

    protected function assertConfigured(): void
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Paystack payments are disabled.');
        }
        if ($this->getSecretKey() === '') {
            throw new \RuntimeException('Paystack secret key is not configured.');
        }
    }

    protected function request()
    {
        return Http::withToken($this->getSecretKey())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('paystack.timeout', 15));
    }

    protected function validatedResponse($response, string $errorMessage): array
    {
        if (!$response->successful() || !$response->json('status') || !is_array($response->json('data'))) {
            throw new \RuntimeException($errorMessage);
        }

        return $response->json('data');
    }

    protected function toMinorUnits($amount): int
    {
        if (!is_numeric($amount) || (float) $amount < 0) {
            throw new \InvalidArgumentException('Payment amount must be a non-negative number.');
        }

        return (int) round(((float) $amount) * 100);
    }

    protected function metadata(array $booking, $commonOrder): array
    {
        return [
            'booking_id' => $commonOrder,
            'order_number' => $commonOrder,
            'event_id' => data_get($booking, '0.event_id'),
            'user_id' => data_get($booking, '0.customer_id'),
            'ticket_ids' => collect($booking)->pluck('ticket_id')->filter()->values()->all(),
        ];
    }

    protected function savePendingTransaction(
        string $reference,
        $orderId,
        $userId,
        int $amount,
        string $currency,
        string $channel,
        ?string $maskedPhone = null,
        ?array $gatewayResponse = null
    ): PaystackTransaction {
        return PaystackTransaction::create([
            'reference' => $reference,
            'order_id' => $orderId === null ? null : (string) $orderId,
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => $currency,
            'channel' => $channel,
            'status' => 'pending',
            'masked_phone' => $maskedPhone,
            'gateway_response' => $gatewayResponse ? $this->safeGatewayResponse($gatewayResponse) : null,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    protected function safeGatewayResponse(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'reference', 'status', 'amount', 'currency', 'channel', 'gateway_response', 'display_text',
        ]));
    }
}
