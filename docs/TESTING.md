# Testing

## Automated tests

The focused Paystack gateway tests use Laravel `Http::fake()` and SQLite:

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=PaystackGatewayTest
```

Coverage includes Kenyan number normalization, Paystack redirect initialization, M-Pesa Charge API payloads, masked phone storage, invalid webhook signature rejection, duplicate webhook persistence/queueing, and verification amount/currency matching.

## Manual Paystack test-mode script

1. Set test keys in local `.env` (`PAYSTACK_ENABLED=true`, `PAYSTACK_PUBLIC_KEY=pk_test_...`, `PAYSTACK_SECRET_KEY=sk_test_...`) and configure `APP_URL` to a publicly reachable HTTPS test domain for webhook testing.
2. Run migrations and queue processing: `php artisan migrate`, `php artisan queue:work --stop-when-empty`.
3. In the Paystack dashboard, enable the Kenya M-Pesa channel and set the webhook to `https://YOUR_TEST_HOST/paystack/webhook`.
4. Create a KES event and paid ticket. Select Paystack, enter a test Safaricom-format number, and submit once.
5. Confirm the prompt status changes only after polling or a signed webhook and Verify Transaction; confirm the booking is not paid for a failed charge or mismatched amount/currency.
6. Test redirect fallback with the Paystack-hosted checkout, then verify that the return URL does not mark a payment successful without server verification.
7. Replay an identical signed webhook and check that the unique reference leaves one transaction record.
8. Review Laravel logs and browser console; do not paste keys, phone numbers, or webhook payloads into issue reports.

The test suite does not yet cover a real Paystack account, a complete booking/email/commission cycle, race/overselling behavior, payment expiry, refunds, transfers, or admin CSV export. Those checks are required before enabling live payments.
