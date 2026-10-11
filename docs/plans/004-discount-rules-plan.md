# 004: Dynamic Discount Rules

- ID: `004-discount-rules`
- Status: completed
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: 2026-10-11

## Problem and Intended Outcome

Add admin-managed quantity and category discounts and a deterministic engine that returns a snapshot-ready line breakdown. This phase does not create carts, orders, sub-orders, or notifications.

## Existing Implementation Findings

- Verified: plans 001-003 are complete; this is the next sequential plan number.
- Verified: there is no cart/order DTO, discount model, service, contract, or service-specific test suite yet.
- Verified: `product_vendor.price` is `DECIMAL(10,2)` and `ProductOffer` casts it to a two-decimal string. Plan 003 stores `products.category` as an indexed string; there is no categories table. This plan supersedes that category choice.
- Verified: `AppServiceProvider` is the only application provider and currently has no bindings. The admin UI is React/Vite mounted by protected web routes and calls protected JSON APIs.
- Verified: tests use PHPUnit; factories and `RefreshDatabase` are established patterns. No package or money library is installed for this feature.

## Proposed Implementation Steps

1. Refresh the unadopted plan 003 catalog migration sequence as `vendors`, `categories`, `products`, `product_images`, `product_vendor`, then `discount_rules`. Add `categories` before products with unique `name` and timestamps; change products from string `category` to required `category_id`. Define `discount_rules` with `name`, nullable `description`, string `type`/`discount_type` constants, `DECIMAL(10,2) value`, nullable `min_quantity`, nullable `vendor_id`/`product_id`/`category_id` foreign keys, nullable `starts_at`/`ends_at`, `is_active`, `priority`, timestamps, and soft deletes. Use `nullOnDelete` for vendor/product filters and restrict category deletion while referenced; add active/date/priority and scope indexes. The user permits a full refresh: use `migrate:fresh --seed` only on disposable data. If a shared database has already been migrated or contains catalog rows, preserve it with a forward/backfill migration instead of rewriting applied migrations.
2. Add `app/Models/Category.php`, factory, `CategoryResource`, Form Requests, and admin API controller. Add a Categories item to the React admin navigation and category index/form screen. Prevent category deletion while products or rules reference it. Update `Product`, product requests/resources, `ProductFactory`, product form, and `AdminCatalogSeeder` to store and select category IDs; seed categories before products.
3. Add `app/Models/DiscountRule.php`, factory, and a development-only seeder invoked after categories/catalog seeding. Seed a product-specific quantity rule, a general quantity rule, an expired category rule, and an inactive rule. Category discount rules reference `category_id`.
4. Add `DiscountRuleInterface.php`, `QuantityDiscountRule.php`, `CategoryDiscountRule.php`, `DiscountEngine.php`, and the best-per-line stacking strategy under `app/Services/Discounts/` (the app currently has no services/contracts folder). The interface exposes `supports(DiscountRule $rule): bool` and `apply(Collection $cartLines, DiscountRule $rule): array`. Add minimal `final readonly` cart-line and result DTOs under `app/Services/Discounts/Data/`; do not create cart/order persistence. Tag implementations as `discount.rules` in `AppServiceProvider` and resolve them dynamically, so the engine does not branch on rule types.
5. Define inputs as `product_id`, `vendor_id`, `quantity`, `unit_price_cents`, and `category_id`. Quantity rules aggregate by `(product_id, vendor_id)` before matching `min_quantity`, then discount every unit in qualifying groups; filters constrain eligible groups. Category rules match `category_id` and may filter by vendor. Load only non-deleted, active, in-window rules (`starts_at <= now`, `ends_at >= now`), ordered by descending priority and ascending ID.
6. Keep persisted offer/rule values as decimal strings; convert to integer cents for calculation and never use floats. Percentage discounts round half-up once per line; fixed discounts are per-unit cents times quantity. Cap discounts at line subtotal. Return immutable line fields `product_id`, `vendor_id`, `quantity`, `unit_price`, `line_subtotal`, `discount_amount`, `applied_rule_id`, `applied_rule_name`, and `line_total`, plus totals; serialize money as decimal strings.
7. Add admin Form Requests with conditional validation (`min_quantity` for quantity rules, `category_id` for category rules, percentage 1-100, and `ends_at` after `starts_at`), `DiscountRuleResource`, and admin API controllers. Add protected React mounts under `/admin/categories` and `/admin/discount-rules`; add category CRUD, discount-rule resource CRUD, options, test evaluation, and status-toggle API routes under `/api/admin`. Extend `AdminApp`/`AdminLayout` with Categories and Discounts. Product and discount forms use the admin category dropdown. The discounts index shows name, type, value, computed status, and priority with enable/disable, edit, and delete actions. Status is Inactive when disabled, otherwise Scheduled before `starts_at`, Expired after `ends_at`, or Active. The form shows conditional fields and a readable summary. The test panel resolves a selected product/vendor's active offer and invokes the real engine without writing catalog/order data.
8. Add PHPUnit unit tests under `tests/Unit/Services/Discounts/` for rule matching, activation/date filtering, grouping, filters, stacking/tie-breaking, fixed/percentage arithmetic, rounding, caps, and dynamic tags. Add feature tests for category CRUD/references, product category selection, discount CRUD/conditional validation, authorization, options, and test-panel breakdown. Update product/feature requirements, backend/database/security/frontend architecture, API routes, user flows, known issues, current state, and changelog docs.

## Proposed Routes

- Protected React mounts: `GET /admin/categories`, `/admin/categories/create`, `/admin/categories/{category}/edit`, `GET /admin/discount-rules`, `/admin/discount-rules/create`, and `/admin/discount-rules/{discountRule}/edit`; all use `auth` and `admin` middleware.
- Protected JSON resources under `/api/admin`: `Route::apiResource('categories', ...)` and `Route::apiResource('discount-rules', ...)`, both using `auth:sanctum` and `admin` middleware.
- Additional discount APIs: `GET /api/admin/discount-rules/options`, `POST /api/admin/discount-rules/test`, and `PATCH /api/admin/discount-rules/{discountRule}/status`, registered before the resource show route.

## Verification Checklist

- [x] Run `php artisan migrate:fresh --seed` only against an isolated disposable database; verify the rule samples and catalog dependencies seed successfully. Verified on an in-memory SQLite database; the full migration chain and both the catalog and discount-rule seeders completed.
- [x] Run focused discount unit/feature tests and then `php artisan test --compact`. Full suite: 54 tests, 276 assertions, all passing.
- [x] Run `vendor/bin/pint --dirty --format agent` and `npm run build`. Pint fixed style issues; the Vite build completed successfully.
- [ ] Manual admin walkthrough: create/edit/toggle/delete each rule type; verify conditional fields, statuses, category/vendor/product selections, summary copy, validation errors, and the test panel's line/totals breakdown. Not performed in this environment (no browser session); covered by feature tests instead.
- [x] Verify guest redirect, non-admin 403, admin access, and that the test panel returns a breakdown without mutating products, offers, or rules. Covered by `AdminAccessTest` and `AdminDiscountRulesTest`.

## Decisions I Made / Questions for You

- Resolved: stacking applies every matching rule **cumulatively per line**, in priority-descending, ID-ascending order, with the combined discount capped at the line subtotal. This supersedes the originally proposed best-per-line strategy at the user's direction; `applied_rules` in each line result exposes the ordered per-rule breakdown.
- Confirmed: discount types are `percentage` and `fixed`; `fixed` is a per-unit amount in offer currency.
- Decision: replaced `products.category` with a `category_id` foreign key and added admin category CRUD. Unique category name; deletion is blocked while products or rules reference the category; product and discount forms share the category dropdown.
- Decision: the engine is stateless and performs a read-only active-rule query; individual rule implementations are deterministic transformations receiving explicit cart lines and rules, so unit tests run without database reads.
- Confirmed: integer cents throughout, percentage rounded half-up once per line, each rule capped by the line's remaining subtotal, and money serialized as decimal strings.