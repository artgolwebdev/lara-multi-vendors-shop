# Testing

## Current Setup

PHPUnit 12 is configured by `phpunit.xml` with `tests/Unit` and `tests/Feature` suites. Feature coverage includes API authentication, admin authorization, vendor/product CRUD, product images and offers, lowest price, sample seed data, admin privilege boundaries, the public storefront catalog and cart-quote endpoints, and storefront SPA page mounts; unit coverage includes the discount engine, `CartQuoter`, and `DiscountHintResolver`. Test environment settings use in-memory SQLite, array cache/session/mail, and synchronous queues. There is no JavaScript test runner; the storefront is validated by `npm run build` plus its host-route feature test.

## Commands

- Run all tests: `php artisan test --compact`.
- Run focused admin/catalog tests: `php artisan test --compact tests/Feature/AdminAccessTest.php tests/Feature/AdminCatalogTest.php`.
- Run focused storefront/cart tests: `php artisan test --compact tests/Feature/StorefrontProductListingTest.php tests/Feature/CartQuoteTest.php tests/Feature/StorefrontPagesTest.php`.
- The Composer test script clears configuration and runs Artisan tests: `composer test`.

Latest plan 005 run: 97 tests and 490 assertions passed (Pint clean, `npm run build` succeeded). See that plan's verification results for the focused suites and the manual walkthrough that remains outstanding.