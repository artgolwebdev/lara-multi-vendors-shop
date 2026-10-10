# Testing

## Current Setup

PHPUnit 12 is configured by `phpunit.xml` with `tests/Unit` and `tests/Feature` suites. The repository currently has one example unit test and one feature test; the feature test asserts the welcome route returns HTTP 200. Test environment settings use in-memory SQLite, array cache/session/mail, and synchronous queues.

## Commands

- Run all tests: `php artisan test --compact`.
- Run a focused test: `php artisan test --compact tests/Feature/ExampleTest.php`.
- The Composer test script clears configuration and runs Artisan tests: `composer test`.

The baseline result recorded for the documentation task was 2 tests passed, 2 assertions. That result is evidence only for this run, not a claim of comprehensive feature coverage. Record actual outcomes in each implementation plan.