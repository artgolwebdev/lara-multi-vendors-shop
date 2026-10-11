# Multi-Vendor Store

A Laravel 13 and React storefront with customer authentication, an admin-managed vendor/catalog workspace, shopping cart, checkout, orders, and stackable quantity, category, and loyalty discounts.

## Features

- Storefront catalog, product/category pages, and a persistent guest cart.
- React sign-in, registration, account dashboard, and same-origin Sanctum sessions.
- Admin-only catalog, vendor, customer, order, and discount-rule management.
- Vendor offers, checkout, order confirmation, and order history.
- A discount engine that stacks every matching rule on each line and caps the combined discount at that line's subtotal.

## Requirements

- PHP 8.3 or newer and Composer
- Node.js and npm
- SQLite (default) or MySQL

## Install and run locally

From the project root, install PHP dependencies and create the local environment file:

```sh
composer install
cp .env.example .env
php artisan key:generate
```

If `.env` already exists, keep it and do not overwrite it. The default `.env.example` uses SQLite. Create its database file if it is missing, then install frontend dependencies, migrate, seed sample data, and link public uploads:

```sh
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
npm install
php artisan migrate --seed
php artisan storage:link
```

Start Laravel and Vite in separate terminals:

```sh
php artisan serve
npm run dev
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000). For more detail, including MySQL setup, see [Development Setup](docs/development/setup.md).

## Local seeded test accounts

Run `php artisan db:seed` (or `php artisan migrate --seed`) in a non-production environment to create the sample accounts:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@admin.com` | `secret` |
| Customer | `casey@example.com` | `password` |
| Customer | `jordan@example.com` | `password` |
| Customer | `riley@example.com` | `password` |

Sign in at `/login`; the admin workspace is available at `/admin`. These are public development credentials: use them only with a local development database. The sample account and catalog seeders skip production.

## Verify

```sh
php artisan test --compact
npm run build
```

See [docs/README.md](docs/README.md) for architecture, API, development, and implementation-plan documentation.
