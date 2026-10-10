# Development Setup

## Requirements Verified from Manifests

- PHP `^8.3`; this workspace reports PHP 8.4.23.
- Composer dependencies include Laravel Framework `^13.17`; installed Laravel is 13.35.0.
- Node.js/npm are required by the Vite scripts. Installed frontend package versions are listed in [current state](../status/current-state.md).
- MySQL is available in the current Boost-connected environment; repository defaults select SQLite. Choose a connection through environment configuration for the target environment.

## Local Setup

1. Install PHP dependencies with `composer install`.
2. Create `.env` from `.env.example` if needed and generate an app key with `php artisan key:generate`.
3. Configure database and other environment values locally; do not commit secrets.
4. Install JS dependencies with `npm install`.
5. Apply migrations with `php artisan migrate`.
6. Run the application and asset watcher with `composer run dev`, or run `php artisan serve` and `npm run dev` separately.

The repository also defines `composer run setup`; inspect its script in `composer.json` before running because it installs dependencies, creates environment state, migrates, and builds assets. Environment variable names and purpose are described without values in the root `.env.example`; no `.env` values are reproduced here.

For tests, see [testing](testing.md). For framework conventions, use [development conventions](conventions.md) together with Laravel Boost.