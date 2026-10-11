# Lara Multi-Vendor Store

A Laravel 13 application with a React/Vite frontend, session authentication, and an admin-managed vendor and product catalog.

## Features

- React registration and login using same-origin Sanctum sessions.
- Admin-only dashboard and vendor management.
- Shared products with public-disk images and vendor-specific prices.
- Lowest offer calculated from active offers at active vendors.

Orders, checkout, public storefront pages, vendor accounts, and discounts are not implemented yet.

## Requirements

- PHP 8.3 or newer and Composer
- Node.js and npm
- MySQL or SQLite

## Setup

1. Install PHP dependencies: `composer install`.
2. Create `.env` from `.env.example` if needed, configure the database, and run `php artisan key:generate`.
3. Install frontend dependencies: `npm install`.
4. Apply the schema and prepare local sample data:

```powershell
php artisan migrate
php artisan storage:link
php artisan db:seed
```

The sample admin and catalog seeder is skipped in production.

Run the app and Vite in separate terminals:

```powershell
php artisan serve
npm run dev
```

## Verification

```powershell
php artisan test --compact
npm run build
```

See [docs/README.md](docs/README.md) for architecture, API, development, and implementation-plan details.