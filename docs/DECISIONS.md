# Implementation decisions

- The production currency for the M-Pesa charge path is KES, converted to minor units by multiplying the validated amount by 100. Redirect checkout continues to use the order's configured currency.
- Kenyan mobile numbers accept `07...`, `01...`, national `7...`/`1...`, `254...`, and `+254...`; the server sends `+254XXXXXXXXX` to Paystack and stores only a masked number.
- `PAYSTACK_ENABLED` is off by default. The existing Eventmie Paystack settings can continue to supply keys; environment configuration is the deployment source of truth. Daraja settings and behavior are not changed.
- Paystack Charge is the M-Pesa-first flow; Initialize Transaction with `mobile_money` and `card` channels remains the fallback. Payment completion is trusted only after the server Verify Transaction response matches the persisted reference, amount, and currency.
- Webhooks store only non-PII event fields and are queued. Queue defaults to `database`, matching shared-hosting cron operation.
- The release workflow builds with Node, runs tests, then replaces development dependencies with the production Composer install before creating the artifact. cPanel itself does not need Composer or Node.
- Transfers are intentionally disabled by default. Automatic payouts and refunds are not activated until the platform's commission, cancellation, and Paystack account-recipient rules are confirmed.
- Eventmie transaction finalization relies on the checkout session. Browser return/polling completes the existing Eventmie booking flow; webhook-only checkout completion remains an explicitly documented limitation rather than fabricating a booking or commission.
- Paystack's official documentation pages returned HTTP 403 to the documentation fetcher during this task. The endpoint shapes implemented here must be revalidated against the merchant's current Paystack dashboard/API documentation before enabling live charging.
