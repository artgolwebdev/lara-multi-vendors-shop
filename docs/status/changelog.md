# Documentation Changelog

## 2026-10-12

- Implemented Plan 019: fixed the 422 "The images field must be an array" error when editing a product. The real trigger (differs from the plan's original hypothesis) is that the React edit form spreads the API product payload into its state and the submit loop stringified the `images`/`offers`/`category` values back into `FormData`, so PHP received a present, non-array `images` field. Changed `images` to `nullable` in `UpdateProductRequest` and `StoreProductRequest`, and made `ProductForm.submit()` append only editable fields (keys present in `emptyProduct`). Per the user's request, the edit form now hides the image upload control (create keeps it) because image storage is not durable on Vercel yet. Added three regression tests (update without images, update with new images, update with invalid image). Full suite 154 passed (709 assertions), Pint clean, `npm run build` exit 0. This change set also includes the removal of the unauthenticated `/run-seeder` route from `routes/web.php` (deleted outside this task after it was flagged as a production risk) with Pint's cleanup of its now-unused `Artisan` import. See [Plan 019](../plans/019-fix-product-update-images-validation.md).
- Implemented Plan 018: fixed the cart "+" button being unresponsive on mobile devices (320–390 px). Root causes were (1) the `@media (max-width: 620px)` grid placing `.store-cart-qty` and `.store-cart-total` side by side in a 78 px column while `QuantityControl` is 120 px wide, causing the "+" button to overflow and be covered by `.store-cart-total`; (2) `.store-quantity button` touch target was 38 × 38 px, below the 44 px minimum; (3) no `touch-action: manipulation` on the buttons, leaving a 300 ms tap delay on mobile. Fixed by making `qty` and `total` each span the full row width in the mobile grid, increasing button size to `min-width/min-height: 44px`, and adding `touch-action: manipulation`. CSS-only change; no JSX, backend, or API files touched. `npm run build` passed (exit 0). See [Plan 018](../plans/018-cart-plus-button-mobile-bug-fix.md).

## 2026-10-11


- Reproduced the Production API 404 behavior through the local Vercel PHP entry point. Found Symfony was treating `/api` as Laravel's base URL because the script server variables described `api/index.php`. Updated `api/index.php` to present `public/index.php` as the Laravel front controller. Local smoke checks now return 401 for unauthenticated `/api/user`, 422 for invalid catalog query parameters, route/database errors rather than 404 for catalog GETs, and 200 for the built JS asset. Focused auth/catalog tests (12 tests, 68 assertions), PHP lint, and Pint pass. Preview/Production verification remains pending.
- Updated Plan 017, Plan 016, deployment guidance, current state, known issues, and backlog with the reproduced root cause and local correction. Local catalog HTTP requests currently fail at MySQL (`server has gone away`), so Aiven/data response health remains a separate unresolved check.
- Added Plan 017 for the new Production API 404s reported after commit `2cfb494`: `/api/user` and the public `/api/products` query both return 404 in the user's browser. Confirmed both routes are registered locally and documented that routing/path dispatch must be distinguished from Sanctum and Aiven failures before changing code.
- Recorded Production deployment `dpl_3AJqEQPwbi784jt5TTZnr7ZpWerm` as Ready for commit `2cfb494`; the user's API error evidence indicates the storefront bundle is executing, while Vercel response/log access remains unavailable for independent diagnosis.
- Added Plan 016 to diagnose and fix the blank Production storefront, with separate verification for Vite build output, static asset routing, browser rendering, and catalog API behavior.
- Added the user's browser evidence that Vite JS/CSS assets return 404. The favicon request also returns 404, but its local file is a zero-byte placeholder and is a separate issue.
- Implemented a serverless asset response in `api/index.php` after confirming that `vercel-php` does not automatically expose files produced during its Composer build hook to browsers. The local Vite build and PHP-server asset/Laravel-fallback smoke checks pass; Vercel Preview validation remains outstanding because deployment access returns 403.
- Addressed Vercel's missing `dist` output error by setting the framework preset to `Other` and the output directory to Laravel's `public/` in `vercel.json`; updated Plan 015 and deployment status docs.
- Pushed commit `b3283de` to `main`; Vercel Production deployment `dpl_JDJjUB9RkLezhKzoqsyMGEtumu1L` reached `READY`, confirming the output-directory build error no longer blocks deployment. Build-log access was denied, so migration output and runtime/API/asset checks remain unverified.

- Updated Vercel deployment documentation and Plan 014 for Production-only database migrations; confirmed demo seeders are not required in Production.
- Added a Vercel Production-only migration command after the frontend build. It configures the Aiven CA, skips Preview/local environments, and does not invoke seeders. Confirmed the workspace connection is local MySQL, so no Production migration was run.

## 2026-10-11

- Started implementing Plan 014. The supplied Vercel Preview URL is behind Vercel Authentication; the app alias reaches PHP 8.5.2/Laravel, but `/api/categories` returns a database `QueryException` with a trace in its response. Raw error contents were not retained. Vercel project access was denied to the connected account, so Production `APP_DEBUG=false`, deployment logs, and DB/TLS diagnosis require authorized project access.
- Added proposed Plan 014 for investigating Vercel Preview HTML responses and completing the Laravel 13/React/Vite/Aiven deployment review. Corrected the recorded React Router DOM version to 7.18.4 from the installed package tree.
- Continued Plan 013: completed the local configuration/runtime prerequisite audit. XAMPP PHP 8.4.23 has `pdo_mysql`, OpenSSL, and the PDO MySQL verification option; the Aiven CA parses, is marked as a CA, and is within its current validity window. Vercel build/runtime and live Aiven verification remain outstanding.
- Checked the user-provided Vercel Preview's `/up` endpoint with a read-only HTTPS GET; it returned HTTP 200 with HTML, so no Laravel health or database result was established.
- With the user's approval for read-only access to the demo's Aiven Production database, requested the public `/api/categories` endpoint. It returned HTTP 200 with HTML instead of JSON, so database connectivity remains unverified; no write, migration, or seed request was made.
- Added proposed Plan 013 to audit and verify Aiven MySQL TLS from Laravel on Vercel. It records the current CA/PDO setup, local-only checks, unresolved remote access and trust questions, a safe preview test sequence, and migration/rollback safeguards.
- Started Plan 012 with Vercel's community PHP runtime, a Laravel serverless entry point using `/tmp` for writable runtime paths, Vite build integration, MySQL TLS verification configuration, and deployment documentation. The owner reports Aiven MySQL credentials are in Vercel. Deployment, Aiven connectivity, migration procedure, and durable product-image storage remain unverified; local disk is temporary on Vercel.
- Configured Plan 012's Vercel entry point to use the owner-provided `storage/certs/ca.pem` as the default Aiven CA path, while retaining environment overrides and failing closed when no CA file is available.
- Verified that PHP OpenSSL parses the provided Aiven CA PEM; this format check does not establish CA trust or live connectivity.
- Started plan 011: made the storefront header sticky and added a compact mobile disclosure for primary navigation, search, cart count, and account actions. At the available ~506px browser width, the header stayed visible while scrolling; the menu exposed its state, closed on Escape, and the account page stacked. Production build and focused storefront tests passed (5 tests, 41 assertions). Exact plan viewport widths and authenticated admin UI review remain outstanding.
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
