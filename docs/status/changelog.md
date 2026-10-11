# Documentation Changelog

## 2026-10-11

- Added the assignment audit report and reconciled architecture, database, product, user-flow, system-component, known-issue, and test documentation with the implemented checkout/order workflow. Recorded the latest plan 010 test results as historical verification; the audit did not rerun tests.
- Updated `DiscountRuleSeeder` so every sample discount rule uses priority 30, the same as “Welcome discount.” Added a regression test asserting the shared priority and confirming welcome loyalty plus matching product/storewide quantity discounts appear together on a seeded first-order cart; the focused catalog feature tests pass (11 tests, 73 assertions).
- Implemented plan 010: normalized non-loyalty discount rule audiences to `all_customers` in the React admin form and both store/update requests, preserving the non-null database invariant and selected-customer loyalty synchronization.
- Added feature coverage for null audience create/update, loyalty audience persistence, transitions away from selected-customer loyalty, and checkout stacking: a new customer's five-unit order receives welcome loyalty plus quantity discounts; their subsequent order receives only the quantity discount.
- Verified the full suite (150 tests, 694 assertions), Pint, and the Vite production build. Build succeeds with existing Sass deprecation and Tailwind/Lightning CSS unknown-at-rule warnings.
- Implemented plan 009: aligned login, registration, and password recovery with the Goodgoods design system.
  - Mounted the React auth experience at `/login` and `/register` while preserving `/account`; added route-aware modes, shared form primitives, field errors, remember-me, password visibility, and recovery navigation.
  - Preserved Laravel intended destinations and validated explicit auth redirects as same-origin URLs.
  - Restyled the Laravel UI layout and password recovery/reset views; added route, redirect, remember-cookie, and page coverage (146 tests, 662 assertions).
  - Verified Pint and production asset build; Vite reports existing Sass/Tailwind deprecation and minifier warnings.
- Fixed storefront-to-auth navigation after the checkout sign-in CTA was intercepted by the storefront React Router. Cart registration promotions, checkout, order confirmation, and the signed-in account header now perform full-page navigation to the separately mounted auth app.
- Restored the storefront cart page's `Link` import for its in-app checkout and shop links; rebuilt the production bundle.
- Implemented plan 008: UX/UI polish, design tokens, shared UI primitives, and checkout/confirmation fixes.
  - Added CSS custom property tokens (`resources/css/tokens.css`) and neutral `gg-*` primitives (`resources/css/ui.css`, `resources/js/components/ui/*`).
  - Fixed order confirmation crash (`ReferenceError: user is not defined`), added order details, delivery card, per-vendor sub-orders with applied discount rule labels, and clearCart dispatch on completion.
  - Fixed customer account order history endpoint (`/api/orders`), added live list with status badges and direct links to order confirmation.
  - Redesigned checkout with responsive two-column layout, Steps indicator, per-field validation error displays, guest registration callout, and 409 Conflict price re-confirmation flow (`expected_total`).
  - Rewrote admin orders index with multi-criteria filtering (search, status, vendor, customer, date range), Apply/Reset, active filter chips, URL persistence, and items count.
  - Redesigned admin order detail with sub-order status controls, conditional vendor notification dispatch, and items tables; added matching order history and sales/spend metrics to admin vendor and customer detail pages.
  - Verified tests (142 tests, 645 assertions passing), Pint formatting passed, and asset compilation (`npm run build`) succeeded.
- Implemented plan 007: orders, checkout, vendor notifications, and admin order management.
  - Added order, sub-order, and order item models, migrations, relationships, and status workflows.
  - Added storefront checkout endpoint (`POST /api/orders`) with atomic order creation, optimistic total check (`409 Conflict`), guest access token, and COD payment support.
  - Added queued vendor notifications (`NotifyVendorJob`) and re-send triggers for sub-orders.
  - Added customer order history (`GET /api/orders`) and single order access (`GET /api/orders/{order}`) with guest token and owner access checks.
  - Added admin order management APIs (`GET /api/admin/orders`, `GET /api/admin/orders/{order}`, `PATCH /api/admin/sub-orders/{subOrder}/status`, `POST /api/admin/sub-orders/{subOrder}/notify`).

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
