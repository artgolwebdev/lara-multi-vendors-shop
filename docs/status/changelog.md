# Documentation Changelog

## 2026-10-11

- Implemented plan 004: admin-managed quantity and category discount rules, a categories table replacing the product string category, and a stateless integer-cent discount engine with tagged rule implementations.
- Added `app/Services/Discounts` (`DiscountRuleInterface`, `QuantityDiscountRule`, `CategoryDiscountRule`, `DiscountEngine`, `Money`, and immutable DTOs); matching rules stack cumulatively per line and are capped at the line subtotal.
- Added category and discount-rule admin APIs, routes, Form Requests, resources, factories, seeders, React admin screens with computed statuses and a live test panel, plus discount unit and feature tests (54 tests, 276 assertions).
- Verified the full `migrate:fresh --seed` chain on disposable SQLite and applied the three new migrations (and sample discount rules) to the configured MySQL database without a reset.
- Implemented plan 003: admin-only React catalog workspace, protected web/API routes, managed vendors, products, public-disk product images, and vendor-specific offers.
- Added `is_admin` default false with registration and mass-assignment protections; the non-production sample seeder provisions the requested admin account and sample catalog data.
- Added feature coverage for admin access, vendor CRUD, product images/offers, lowest active offer, and admin privilege protection.
- Replaced the earlier vendor-self-service direction with admin-managed vendor records; orders, discounts, public shop, and vendor login remain out of scope.

## 2026-10-10

- Added persistent project documentation based on inspected code, route list, installed package versions, and Boost database schema.
- Added the backlog and reusable plan template, with plan `001-project-foundation` tracking this rollout.
- Added one canonical agent task workflow while preserving Boost-managed guidance, skills, and MCP configuration.
- Implemented Step 1 React/API registration, login, and logout using Sanctum stateful sessions; added the Sanctum token table and auth feature tests.
- Updated product, architecture, API, and current-state docs to separate requested future marketplace scope from implemented auth.
