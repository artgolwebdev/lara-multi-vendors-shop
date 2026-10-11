# Development Setup

## Requirements Verified from Manifests

- PHP `^8.3`; this workspace reports PHP 8.4.23.
- Composer dependencies include Laravel Framework `^13.17`; installed Laravel is 13.35.0.
- Node.js/npm are required by the Vite scripts. Installed frontend package versions are listed in [current state](../status/current-state.md).
- The example environment defaults to SQLite. MySQL is also supported; choose a connection through environment configuration for the target environment.

## Local Setup

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env` if `.env` does not already exist, then generate an app key with `php artisan key:generate`. Do not overwrite an existing environment file.
3. Configure the database and other environment values locally; do not commit secrets.
4. Install JavaScript dependencies with `npm install`.
5. Apply migrations with `php artisan migrate` (or use `php artisan migrate --seed` to migrate and seed in one step).
6. Create the public storage symlink with `php artisan storage:link` so uploaded product images can be served.
7. Seed local/sample accounts, catalog, discount rules, and demo orders with `php artisan db:seed`. These seeders skip production.
8. Run `php artisan serve` and `npm run dev` in separate terminals. The site is then available at `http://127.0.0.1:8000` by default.

### SQLite (default)

`.env.example` sets `DB_CONNECTION=sqlite`. Ensure the database file exists before migrating:

```sh
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
```

### MySQL

Create an empty database and configure `.env` with the database connection, host, port, database name, username, and password. For example:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=multi_vendor_store
DB_USERNAME=root
DB_PASSWORD=
```

Then run:

```sh
php artisan migrate --seed
```

### Seeded local accounts

In non-production environments, database seeding creates the following development accounts:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@admin.com` | `secret` |
| Customer | `casey@example.com` | `password` |
| Customer | `jordan@example.com` | `password` |
| Customer | `riley@example.com` | `password` |

Use the admin account to open `/admin`; customer accounts can exercise the storefront and account pages. These shared credentials are for local development only and must not be used in a deployed environment.

### Reset local data

To drop and recreate all tables, then load sample data, run this only against a disposable local database:

```sh
php artisan migrate:fresh --seed
```

The repository also defines `composer run setup`; it installs PHP and npm dependencies, creates the environment file if missing, generates the key, runs migrations, and builds frontend assets. It does not seed sample accounts or create the storage symlink, so use the explicit steps above when you want a complete local demo. Environment variable names and purpose are described in the root `.env.example`; do not commit local `.env` values.

For tests, see [testing](testing.md). For framework conventions, use [development conventions](conventions.md) together with Laravel Boost.
