# Testing

## Current Setup

PHPUnit 12 is configured by `phpunit.xml` with `tests/Unit` and `tests/Feature` suites. Feature coverage includes API authentication, admin authorization, vendor/product CRUD, product images and offers, lowest price, sample seed data, and admin privilege boundaries. Test environment settings use in-memory SQLite, array cache/session/mail, and synchronous queues.

## Commands

- Run all tests: `php artisan test --compact`.
- Run focused admin/catalog tests: `php artisan test --compact tests/Feature/AdminAccessTest.php tests/Feature/AdminCatalogTest.php`.
- The Composer test script clears configuration and runs Artisan tests: `composer test`.

Latest plan 003 run: 17 tests and 117 assertions passed. See its verification results for focused tests, formatting, frontend build, and migration/seed checks.