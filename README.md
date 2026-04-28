# electricity-payment-app

A Laravel backend for electricity bill payments (auth + meter verification).

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

## API endpoints

- `POST /api/register`
- `POST /api/login`
- `POST /api/logout` (requires `auth:sanctum`)
- `POST /api/meters/verify` (requires `auth:sanctum`)

## Notes

- Users use **UUID** as primary key.
- Meter verification currently uses a **mock ENEO client** (swap to a real HTTP client later).
