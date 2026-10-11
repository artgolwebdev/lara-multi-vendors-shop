# Testing

## Current Setup

PHPUnit 12 is configured by `phpunit.xml` with `tests/Unit` and `tests/Feature` suites. Feature coverage includes API authentication, admin authorization and CRUD, audience normalization and selected-customer pivot synchronization, catalog/offer pricing, checkout/order access, queued notification behavior, customer loyalty and storefront APIs. Unit coverage includes the discount engine and implementations, `CartQuoter`, and `DiscountHintResolver`. Test environment settings use in-memory SQLite, array cache/session/mail, and synchronous queues. There is no JavaScript test runner; frontend checks use `npm run build` plus host-route feature tests.

## Commands

- Run all tests: `php artisan test --compact`.
- Run focused admin/catalog tests: `php artisan test --compact tests/Feature/AdminAccessTest.php tests/Feature/AdminCatalogTest.php`.
- Run focused storefront/cart tests: `php artisan test --compact tests/Feature/StorefrontProductListingTest.php tests/Feature/CartQuoteTest.php tests/Feature/StorefrontPagesTest.php`.
- The Composer test script clears configuration and runs Artisan tests: `composer test`.

Latest recorded full run in plan 010: 150 tests and 694 assertions passed; focused discount/checkout run: 30 tests and 151 assertions. Pint passed and `npm run build` succeeded with existing Sass deprecation and Tailwind/Lightning CSS unknown-at-rule warnings. These are recorded results, not a claim that the suite was rerun during the current audit. See plan 010 for commands and details.
