# Repository and payment audit

## Scope and environment

- Application: Laravel 9 (`laravel/framework` `^9.19`), PHP constraint in `composer.json` is `^7.3|^7.4|^8.0`; checked package is Eventmie Pro `^1.8` through the local `eventmie-pro/` path repository.
- Payment checkout is overridden in `app/Http/Controllers/Eventmie/BookingsController.php`; bookings and platform transactions use the Eventmie `bookings` and `transactions` tables. M-Pesa/Daraja remains independent of this Paystack integration.
- The Codespace used PHP 8.4, SQLite was available, but `pdo_mysql` was not. The checked-in `.env` selects MySQL, so commands requiring the local MySQL driver need the devcontainer or `DB_CONNECTION=sqlite`.
- This was a source/configuration audit plus focused automated testing, not a browser click-through against a fully installed guest/customer/organiser/admin database. No live Paystack keys or live account were available.

## Findings and disposition

| Area | Issue | Severity | Disposition |
| --- | --- | --- | --- |
| Paystack credentials | Checkout response used a field named `secretKey` in Vue even though the server returned the public key under another name; it must never expose the secret. | High | Removed all secret-key data from the checkout response; server-only Bearer authorization is used. |
| Webhook | Existing handler processed `charge.success` synchronously without enforcing a local amount/currency match or persistent duplicate protection. | High | Added signature verification over the raw body, unique transaction references, sanitized payload storage, queued processing, and server-side Verify Transaction reconciliation. |
| Kenyan M-Pesa | The previous integration only supported redirect initialization and had no Safaricom number validation or charge flow. | High | Added normalized Kenyan phone input, M-Pesa Charge API request, status polling, and redirect checkout fallback. |
| Checkout completion | Existing Eventmie booking finalization depends on the customer's session and callback. Webhook-only processing can mark a transaction verified, but cannot reconstruct/finalize the Eventmie booking if that session is gone. | High | Callback and authenticated checkout polling finalize the normal Eventmie checkout. A durable pending-order/finalization redesign remains a release blocker for guaranteed webhook-only completion. |
| Duplicate checkout | UI disables a repeated request while a request is in flight and routes are rate-limited; independent concurrent sessions for the same logical order are not protected by a database reservation lock. | Medium | Rate limits and unique provider references are in place; inventory reservation/overselling is not addressed by this patch. |
| Payment operations | Paystack refund, Transfer payouts, administrator transaction list/CSV, and end-to-end commission/email behavior were not connected to a tested cancellation/payout lifecycle. | High | Not enabled or represented as complete. Transfers remain off by default; see `DECISIONS.md` and `TESTING.md`. |
| Credentials in history | Targeted patterns for live/test Paystack keys, AWS access IDs, webhook secrets, and private-key headers did not identify a match in reachable Git history. This is not a substitute for provider-account rotation or a complete secret-scanner review. | Informational | No credential values are included in this report. |
| Seeded accounts | The prior user seeder embedded a shared password hash source and static remember-me tokens for default accounts. | High | New seeds use random undisclosed passwords and empty remember tokens; existing installations must reset their administrator credentials and invalidate remembered sessions. |
| Repository hygiene | The repository contained tracked macOS `__MACOSX` metadata. | Low | Removed from the working tree. |
| API routes | Two authenticated routes referenced a missing `MessagesController`, preventing `route:list` from resolving all route actions. | Medium | Removed the dead route declarations; no controller or message provider existed to preserve. |
| Kenya defaults and brand | Initial settings used generic vendor site name, USD, and Asia/Kolkata. | Medium | New installs seed WaziEvents metadata, KES, Africa/Nairobi, Kenya legal-information pages, contact placeholder, sitemap, robots rules, and Event JSON-LD/canonical metadata. Existing production settings are intentionally not overwritten. |
| SEO/content | Open Graph metadata was available from Eventmie; event schema/canonical and sitemap support were absent. Legal templates require operator review and verified contact details. | Medium | Added event schema/canonical and dynamic sitemap; legal templates are marked for Kenyan counsel review. |
| Release validation | Paystack docs endpoints returned HTTP 403 from this environment. | Medium | Endpoint assumptions are documented and must be checked against current account/API docs before live enablement. |
| Compatibility | This Codespace's PHP 8.4 reports deprecations from the pinned PHPUnit/Collision dependency tree; no application exception was reproduced by the focused Paystack suite. | Medium | Dependency upgrade is deferred to avoid a broad framework/package upgrade. |

## ROTATE THESE

The prior source history contained default seeded-account password/token material; its values are intentionally not reproduced here. New seeds no longer use those values. Reset the production administrator password with `php artisan wazi:admin-password ADMIN_EMAIL`, remove demo accounts that are not needed, and invalidate existing remembered sessions. Independently rotate any credentials that have ever been pasted into GitHub, used in a shared environment, or exposed in a Paystack dashboard/support exchange. Keep production values only in the cPanel `.env`.
