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

- A React storefront SPA at `/` with `/shop`, `/categories`, `/categories/{category}`, `/products/{product}`, and `/cart`, backed by public `/api/products`, `/api/products/{product}`, `/api/categories`, `/api/categories/{category}`, and `POST /api/cart/quote`. A React authentication page is mounted at `/account`, with registration/login/logout through same-origin Sanctum session API endpoints. Laravel UI web authentication/password-reset routes and authenticated `/home` remain available.
- `is_admin` defaults false, is excluded from mass assignment, and is rejected by web/API registration. `EnsureUserIsAdmin` protects all admin screens and JSON endpoints.
- Admin React screens provide dashboard counts, vendor CRUD, category CRUD, product CRUD with image upload/removal and per-vendor pricing, and discount-rule CRUD with computed status, enable/disable, and an engine test panel. APIs paginate catalog lists; offers sync in a transaction.
- Domain models include `User`, `Vendor`, `Category`, `Product`, `ProductImage`, `ProductOffer`, and `DiscountRule`. Products reference a required `category_id` (the plan-003 string category was converted); the schema includes vendor/product/rule soft deletes, image storage metadata, and indexed product/vendor offers.
- `app/Services/Discounts` provides a stateless `DiscountEngine`, tagged `discount.rules` implementations for quantity and category rule types, and immutable integer-cent DTOs. Matching rules stack cumulatively per line and are capped at the line subtotal; money is serialized as decimal strings.
- `app/Services/Cart/CartQuoter` resolves explicit or lowest active offers and delegates all discount math to the engine, producing per-line and per-vendor totals for the stateless quote endpoint and single-call listing prices. `app/Services/Discounts/DiscountHintResolver` attaches quantity-discount `{min_quantity, label}` hints to listing offers. The public storefront cart persists IDs/quantities in `localStorage` (Redux Toolkit) and never computes prices client-side.
- `DatabaseSeeder` calls development-only sample seeders for the requested admin account, example vendor/catalog data, and sample discount rules. It does not seed the admin in production.
- Blade and React/Vite frontend with Bootstrap/Sass and Tailwind/Vite assets. React Router and Redux Toolkit power the storefront SPA; there is no Inertia or Livewire.
- PHPUnit: 97 tests and 490 assertions pass, covering admin authorization, vendor/product/category/discount CRUD, upload validation, offer sync and lowest price, discount rule matching/stacking/rounding in unit tests, engine tag resolution, registration privilege boundaries, the public storefront listing/detail/category APIs, the cart-quote endpoint (vendor selection, per-vendor grouping, stock/quantity validation, listing query-count flatness), storefront SPA page mounts, and cart-quoter/hint-resolver unit behavior.
- The Sanctum and plan-004 migrations (`categories`, product `category_id` conversion, `discount_rules`) have been applied to the configured MySQL database in this environment; the sample discount rules were seeded there. Repository defaults still select SQLite where environment configuration does not override them.
- `php artisan migrate:fresh --seed` was verified on a disposable in-memory SQLite database; the configured MySQL database was migrated forward without a reset.

## AI and Project Context

- Laravel Boost MCP remains configured in root `.mcp.json` as `php artisan boost:mcp`.
- `boost.json` enables guidelines, MCP, and skills for Claude Code and Cursor. Existing `.claude/skills/`, `.claude/mcp.json`, and `.cursor/mcp.json` are preserved.
- Root `AGENTS.md` contains Boost-managed guidance inside markers plus the project-owned task workflow outside them. `CLAUDE.md` points to that workflow.
- There is no project-level `.ai/rules/` directory or index. No Boost rules were added.

See [known issues and unknowns](known-issues.md), [changelog](changelog.md), [the initial plan](../plans/001-project-foundation.md), and [Step 1 authentication plan](../plans/002-react-api-auth.md).