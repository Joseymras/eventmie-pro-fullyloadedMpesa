# cPanel deployment

This release is a Laravel application. Use a supported PHP 8.2+ account and the workflow artifact `wazievents-release.zip`, which includes `vendor/` and the compiled `public/` assets. Do not upload `.env`, `.git`, `node_modules`, or test files.

## 1. Create the database

1. Sign in to cPanel.
2. Open **MySQL Database Wizard** (or **MySQL® Databases**).
3. Create a database named `wazievents`; record the cPanel-prefixed database name.
4. Create a database user with a unique strong password; record its cPanel-prefixed username.
5. Add the user to the database and select **ALL PRIVILEGES**.
6. Keep the password in a password manager. Do not put it in Git or support tickets.

## 2. Upload the release and set the document root

### Recommended: app outside `public_html`

1. Download the `wazievents-release.zip` artifact from the successful GitHub Actions release run.
2. In **File Manager**, create `/home/CPANEL_USER/wazievents` (outside `public_html`).
3. Upload and extract the zip into that directory. Confirm `artisan`, `vendor/`, `bootstrap/`, and `public/` are at the extracted app root.
4. In `public_html`, copy the *contents* of the app's `public/` directory. Do not copy private Laravel directories there.
5. Edit `public_html/index.php`: change `../vendor/autoload.php` to `/home/CPANEL_USER/wazievents/vendor/autoload.php`, and change `../bootstrap/app.php` to `/home/CPANEL_USER/wazievents/bootstrap/app.php`.
6. Copy `public/.htaccess` into `public_html/.htaccess`. The supplied root `.htaccess` rewrite is useful only when the app root itself is served and should not expose the app root as the document root.

### Alternative: document root points at Laravel `public/`

If the cPanel plan allows changing the domain's document root, use **Domains → Manage → Document Root** and point the domain directly to `/home/CPANEL_USER/wazievents/public`. Keep the full app outside the public directory. This is preferable to exposing the Laravel root.

## 3. Set the PHP runtime and environment

1. Open **MultiPHP Manager** and select PHP 8.2 or a newer version supported by the application dependencies.
2. Open **Select PHP Version → Extensions** and enable `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `iconv`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, and `zlib`. Enable `exif` if image uploads require it.
3. In File Manager, create `/home/CPANEL_USER/wazievents/.env` with the template below. Generate a unique `APP_KEY` via `php artisan key:generate` if Terminal is available; otherwise generate it on a trusted local PHP install and copy only the generated key through cPanel. Never reuse a key from another environment.
4. Fill in the cPanel-prefixed DB values and real SMTP values. Start in Paystack test mode. Do not put live keys in GitHub Actions variables used to build public artifacts.

```dotenv
APP_NAME=WaziEvents
APP_ENV=production
APP_KEY=base64:REPLACE_WITH_UNIQUE_GENERATED_KEY
APP_DEBUG=false
APP_URL=https://wazievents.co.ke
APP_TIMEZONE=Africa/Nairobi

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPANELPREFIX_wazievents
DB_USERNAME=CPANELPREFIX_waziuser
DB_PASSWORD=REPLACE_WITH_DATABASE_PASSWORD

BROADCAST_DRIVER=log
CACHE_DRIVER=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=mail.wazievents.co.ke
MAIL_PORT=587
MAIL_USERNAME=hello@wazievents.co.ke
MAIL_PASSWORD=REPLACE_WITH_MAILBOX_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@wazievents.co.ke
MAIL_FROM_NAME="WaziEvents"

PAYSTACK_ENABLED=true
PAYSTACK_PUBLIC_KEY=pk_test_REPLACE_ME
PAYSTACK_SECRET_KEY=sk_test_REPLACE_ME
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_TIMEOUT=15
PAYSTACK_TRANSFERS_ENABLED=false
PAYSTACK_WEBHOOK_URL=https://wazievents.co.ke/paystack/webhook
```

Do not change `APP_DEBUG=false` on production. Use live Paystack keys only after end-to-end test-mode signoff and account approval.

## 4. Migrations when SSH/Terminal is available

In **Terminal**, run:

```sh
cd /home/CPANEL_USER/wazievents
php artisan migrate --force
php artisan db:seed --force
php artisan wazi:admin-password admin@admin.com
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If SSH is unavailable, do not expose an unauthenticated web migration endpoint. Generate a schema-only SQL export from a clean local install using the same application release and MariaDB-compatible schema; upload it in **phpMyAdmin → select the production database → Import**. Then run the Eventmie installer/required seed process through its documented administrator setup, or arrange a one-time cPanel Terminal session. Never import a local database containing real customer data into production.

## 5. Storage, permissions, HTTPS, cron

1. In File Manager, set directories `storage/` and `bootstrap/cache/` writable by the PHP process (typically `0755` or `0775` depending on the host). Avoid `0777`; ask the host to correct ownership if those modes fail.
2. For public uploads, use `php artisan storage:link`. If symlinks are disabled, ask the host to enable them or configure a controlled copy from `storage/app/public` to `public/storage`; never make private storage public.
3. In **Domains → Force HTTPS Redirect**, enable HTTPS. If unavailable, configure the site's SSL certificate first and use the existing Laravel rewrite/front-controller HTTPS configuration; verify callbacks use HTTPS.
4. In **Cron Jobs**, add the following (replace `CPANEL_USER` and the PHP binary path shown by the host):

```cron
* * * * * cd /home/CPANEL_USER/wazievents && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/CPANEL_USER/wazievents && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

5. Use **Terminal** to confirm the host's PHP CLI version matches the selected MultiPHP version. Run `php artisan wazi:preflight` and investigate every reported failure before launch.

## 6. Paystack dashboard

1. In **Settings → API Keys & Webhooks**, use test credentials during acceptance testing.
2. Set the webhook URL to `https://wazievents.co.ke/paystack/webhook`.
3. Set the callback URL to `https://wazievents.co.ke/paystack/payment/callback` if the dashboard requests one; the application supplies this callback during transaction initialization.
4. In Paystack payment-channel settings, request/enable **M-Pesa** for **Kenya**. Availability depends on Paystack account approval and country/channel configuration.
5. Run the complete test flow before replacing `pk_test_...`/`sk_test_...` with live credentials in the production `.env`.

## 7. Backups, rollback, smoke checks

- Before each release, download a cPanel database backup and archive the current application directory and `.env` separately. Restrict access to both.
- Keep the previous release directory. To roll back, point the document root/public `index.php` to the prior release, restore the matching DB backup only if the migration is backward-incompatible, then clear cached config/routes/views.
- Smoke-test HTTPS home page, login, event browsing, a free booking, a test-mode paid booking, callback/webhook delivery, ticket email/QR, organiser/admin booking views, `https://wazievents.co.ke/health`, and `php artisan wazi:preflight`.
- Check `storage/logs/laravel.log`, queue failures, and Paystack dashboard event delivery. Rotate any accidentally exposed credentials immediately.
