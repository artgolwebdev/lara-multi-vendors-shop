# Documentation Changelog

## 2026-10-11

- Implemented plan 006: customer accounts and loyalty discounts. Added `phone`/`address` to users, loyalty fields (`loyalty_condition`, `audience`) and a `discount_rule_user` pivot to discount rules, and a `loyalty` rule type discovered through the existing tagged `discount.rules` binding.
- Added `LoyaltyCustomerDiscountRule` with `DiscountContext`, the `CustomerOrderHistory` contract and a default zero-order implementation; `apply()`/`calculate()` now accept an optional context, and `AppliedRule`/`PriceQuoteLine` expose `rule_type`/`is_loyalty`.
- Added account APIs (`/api/account/profile`, `/api/account/password`, `/api/account/discounts`, `/api/account/orders`) and admin customer APIs with selected-customer loyalty grant/revoke; the quote endpoint now takes the authenticated customer and returns guest `promotions`.
- Added the storefront account dashboard, auth-aware header, guest promotion banner, loyalty cart labels, and an admin Customers section plus loyalty fields in the discount-rule form; sample customers and a "Welcome discount" rule are seeded.
- Added unit and feature coverage (117 tests, 535 assertions total) and verified `migrate:fresh --seed` on disposable SQLite and the forward migrations on the configured MySQL database; `npm run build` succeeded.
- Implemented plan 005: a public storefront (React Router + Redux Toolkit) with home, shop, category, product, and cart pages, backed by read-only catalog APIs and a stateless cart-quote endpoint.
- Added `app/Services/Cart/CartQuoter` (offer resolution, engine delegation, per-vendor grouping, line-keyed validation, single-call listing prices) and `app/Services/Discounts/DiscountHintResolver` for quantity hints; all pricing comes from the existing `DiscountEngine`.
- Added public `GET /api/products`, `GET /api/products/{product}`, `GET /api/categories`, `GET /api/categories/{category}`, and `POST /api/cart/quote`, with `CartQuoteRequest` and listing/detail/category resources.
- Moved the React auth SPA from `/` to `/account`; the storefront SPA now owns `/`, `/shop`, `/categories`, `/categories/{category}`, `/products/{product}`, and `/cart` (`store-app`), with the guest cart persisted in `localStorage` (`goodgoods.cart.v1`) and re-priced by a debounced, abortable thunk.
- Added storefront, cart-quote, and cart-quoter/hint-resolver tests (97 tests, 490 assertions) and `npm run build` output.
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
