# Current State

Last verified: 2026-10-11.

## Runtime and Dependencies

- PHP: 8.4.23 in the current workspace.
- Laravel Framework: 13.35.0 (composer constraint `^13.17`).
- Laravel Boost: 2.10.3 (`^2.10`), configured for Laravel Boost MCP.
- Laravel Sanctum: 4.3.3 (`^4.0`), added for stateful SPA/API authentication.
- Database: Boost's active connection reports MySQL. `config/database.php` and `.env.example` default to SQLite; runtime selection depends on environment.
- Frontend installed versions: Vite 8.3.4, Laravel Vite plugin 3.2.0, React/React DOM 18.3.1, React Router DOM 6.30.6, React Redux 9.3.0, Redux Toolkit 2.13.0, Bootstrap 5.3.8, Tailwind CSS and its Vite plugin 4.3.3, Sass 1.105.1, Axios 1.20.0, and Vite React plugin 6.1.2.

## Implemented Application

- A React storefront SPA at `/` with `/shop`, `/categories`, `/categories/{category}`, `/products/{product}`, and `/cart`, backed by public `/api/products`, `/api/products/{product}`, `/api/categories`, `/api/categories/{category}`, and `POST /api/cart/quote`. The Goodgoods authentication SPA is mounted at `/login`, `/register`, and `/account`; it uses same-origin Sanctum sessions, field-level validation, remember-me, password visibility, recovery links, and same-origin intended redirects. Signed-in customers see their profile/password/discounts/orders dashboard at `/account`. Laravel UI web authentication POST routes and branded password-reset views remain available.
- `is_admin` defaults false, is excluded from mass assignment, and is rejected by web/API registration. `EnsureUserIsAdmin` protects all admin screens and JSON endpoints.
- Admin React screens provide dashboard counts, vendor CRUD, category CRUD, product CRUD with image upload/removal and per-vendor pricing, discount-rule CRUD with computed status, enable/disable, an engine test panel, and loyalty fields (condition, audience, selected customers), and a Customers section listing/searching non-admin users with selected-customer loyalty grant/revoke. APIs paginate catalog and customer lists; offers sync in a transaction.
- Domain models include `User`, `Vendor`, `Category`, `Product`, `ProductImage`, `ProductOffer`, and `DiscountRule`. Products reference a required `category_id` (the plan-003 string category was converted); the schema includes vendor/product/rule soft deletes, image storage metadata, and indexed product/vendor offers.
- `app/Services/Discounts` provides a stateless `DiscountEngine`, tagged `discount.rules` implementations for quantity, category, and loyalty rule types, and immutable integer-cent DTOs. Matching rules stack cumulatively per line and are capped at the line subtotal; money is serialized as decimal strings. The `loyalty` rule (audience all/selected customers, first-order condition) receives a `DiscountContext` with the authenticated customer and consults database-backed order history. A registered first-time customer can receive both welcome loyalty and quantity discounts on the same eligible order; the first-order loyalty discount does not repeat after an order is placed.
- `app/Services/Cart/CartQuoter` resolves explicit or lowest active offers and delegates all discount math to the engine, producing per-line and per-vendor totals for the stateless quote endpoint and single-call listing prices; it accepts an optional authenticated customer and the quote endpoint returns guest-facing loyalty `promotions`. `app/Services/Discounts/DiscountHintResolver` attaches quantity-discount `{min_quantity, label}` hints to listing offers. The public storefront cart persists IDs/quantities in `localStorage` (Redux Toolkit) and never computes prices client-side.
- `DatabaseSeeder` calls development-only sample seeders for the requested admin account, example vendor/catalog data, sample discount rules (including a first-order "Welcome discount" loyalty rule), and sample customers. It does not seed the admin in production.
- Orders and multi-vendor checkout at `/checkout` and `/order-confirmation/{id}` with customer session and guest access token support. Checkout features a responsive two-column layout, Steps indicator, per-field validation error displays, guest registration callout, vendor-grouped line item summary with applied discount rule labels, and 409 Conflict re-confirmation flow (`expected_total`).
- Order confirmation page renders order details, customer/shipping metadata, vendor sub-orders with rule breakdown, clearCart dispatch on completion, friendly 404/guest-prompt states, and direct links to `/account`.
- Customer account dashboard at `/account` integrates live order history from `/api/orders` with status badges, formatted totals, and direct links to order confirmation/detail.
- Admin Orders interface at `/admin/orders` and `/admin/orders/{id}` with search/filter card (vendor, customer, status, date range) with Apply/Reset and active chips, URL persistence, items count, vendor chips, sub-order status management, and conditional notification dispatch. Matching order history and spend/sales metrics appear on Admin Vendor and Customer detail pages.
- Design tokens (`resources/css/tokens.css`) and shared UI primitives (`resources/js/components/ui/`, `resources/css/ui.css`) powering checkout, confirmation, and admin order workflows without external component libraries.
- Blade and React/Vite frontend with Bootstrap/Sass and Tailwind/Vite assets. React Router and Redux Toolkit power the storefront SPA; there is no Inertia or Livewire.
- Storefront masthead is sticky across store routes; on screens at or below 900px, navigation, search, and account links are grouped in a keyboard-operable menu disclosure.
- PHPUnit: 150 tests and 694 assertions pass, covering admin authorization, vendor/product/category/discount CRUD, audience normalization and selected-customer pivot synchronization, upload validation, offer sync and lowest price, discount rule matching/stacking/rounding (including welcome loyalty plus quantity stacking through checkout and repeat-order eligibility), engine tag resolution, registration privilege boundaries, customer account access control, admin customer management and loyalty grant/revoke, the public storefront listing/detail/category APIs, the cart-quote endpoint, order checkout, COD placement, 409 price guards, guest token and owner order read access, storefront/auth SPA page mounts, intended auth redirects, remember-cookie login, password recovery page, and cart-quoter/hint-resolver unit behavior.
- The Sanctum, plan-004, plan-006, and plan-007 migrations (`users` phone/address, `discount_rules` loyalty fields, `discount_rule_user` pivot, `orders`, `sub_orders`, `order_items`) have been applied to the configured database.
- `php artisan migrate:fresh --seed` was verified on a disposable in-memory SQLite database; the configured database was migrated forward without a reset.

## AI and Project Context

- Laravel Boost MCP remains configured in root `.mcp.json` as `php artisan boost:mcp`.
- `boost.json` enables guidelines, MCP, and skills for Claude Code and Cursor. Existing `.claude/skills/`, `.claude/mcp.json`, and `.cursor/mcp.json` are preserved.
- Root `AGENTS.md` contains Boost-managed guidance inside markers plus the project-owned task workflow outside them. `CLAUDE.md` points to that workflow.
- There is no project-level `.ai/rules/` directory or index. No Boost rules were added.

## Deployment Preparation

- Initial Vercel community PHP runtime configuration and a Laravel serverless entry point are present under Plan 012. This configuration has not been deployed or verified against Vercel or the user's Aiven service.
- Production database target is Aiven MySQL; the project owner reports adding its environment variables to Vercel and identified `storage/certs/ca.pem` as the Aiven CA bundle. The Vercel entry point now uses this bundle by default; local PHP OpenSSL can parse it, but environment names, certificate trust/rotation, service region, live connectivity, migration state, and production data state remain unverified.
- Product images still use the local public disk. They are not durable on Vercel; do not rely on admin image uploads in production until persistent object storage is selected and configured.
- See [Vercel deployment preparation](../development/deployment-vercel.md), [Plan 012](../plans/012-vercel-aiven-production-deployment-plan.md), and [Plan 013: Aiven MySQL TLS investigation](../plans/013-aiven-mysql-ssl-investigation-plan.md).

See [known issues and unknowns](known-issues.md), [changelog](changelog.md), [the initial plan](../plans/001-project-foundation.md), and [Step 1 authentication plan](../plans/002-react-api-auth.md).
