# WaziEvents

WaziEvents is a Laravel 9 application based on Eventmie Pro with optional Paystack checkout and the existing Daraja M-Pesa gateway.

## Local setup

Requirements: PHP 8.2, Composer 2, Node.js 20, npm, and MySQL 8 (or MariaDB).

1. Clone the repository and install dependencies:

   ```sh
   composer install
   npm ci
   cp .env.example .env
   php artisan key:generate
   ```

2. Set the local database values in `.env`. In the supplied devcontainer use `DB_HOST=mysql`, `DB_DATABASE=wazievents`, `DB_USERNAME=root`, and an empty `DB_PASSWORD`.
3. Run database migrations and seed the application, then build frontend assets:

   ```sh
   php artisan migrate --seed
   npm run prod
   php artisan serve
   ```

   On first visit, Eventmie may redirect to `/license`; complete its setup and confirm the installer creates `storage/installed`. Reset the seeded administrator password with `php artisan wazi:admin-password ADMIN_EMAIL`.
4. Set `PAYSTACK_ENABLED=true` and test keys only when exercising Paystack. Never commit `.env`.

Open the folder in a Codespace and reopen in the devcontainer to start the PHP/Node development environment and MySQL service.

## Tests

The Paystack feature tests use `Http::fake()` and in-memory SQLite:

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
```

## Deployment

See [cPanel deployment instructions](./docs/DEPLOYMENT_CPANEL.md) for the release artifact, production `.env`, cron, storage, and Paystack setup.
