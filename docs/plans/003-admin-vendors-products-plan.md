# 003: Admin Vendors and Products

- ID: `003-admin-vendors-products`
- Status: completed
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: 2026-10-11

## Outcome and Superseded Direction

Add an authenticated admin area and admin-managed vendors, shared products, images, and vendor-specific offers. Vendors are records, not user accounts; vendors do not manage products. This supersedes the earlier vendors-as-users / vendor self-service direction referred to as plan 003. No earlier plan 003 file exists in the current checkout; this is the next available ID after completed plans 001 and 002 and records the changed direction.

Orders, sub-orders, cart, jobs, discount rules, public shop pages, and vendor login are out of scope.

## Existing Implementation Findings

- Verified: the root auth screen is React mounted from a Blade host view, uses Axios with same-origin Sanctum sessions, and is built by Vite. There is no Inertia integration, client-side router dependency, or existing catalog model.
- Verified: the `User` model uses an explicit `#[Fillable]` attribute. Middleware aliases are configured in `bootstrap/app.php`.
- Verified: tests use PHPUnit and `RefreshDatabase`; repository plans are numbered sequentially and indexed in `backlog.md`.
- Verified: the product requirements and architecture docs were updated to remove the superseded vendor-user direction.

## Decisions Made / Questions for Approval

- Proposed UI: React admin screens mounted by minimal Blade host views and built through the existing Vite entry point. Laravel web routes protect the screen URLs; `/api/admin/*` endpoints provide JSON CRUD. Use ordinary route navigation rather than adding a client router package. Add the Admin link to the signed-in React auth view only when the current-user response identifies an admin.
- Decision: use the simpler indexed string `category` field on `products`; do not add category CRUD/table in this phase. Discount rules will need a consistent category-key convention when implemented.
- Decision: `product_vendor` rows use decimal price and an Eloquent `ProductOffer` model; a composite index beginning with `product_id` supports active lowest-price queries.
- Approved: the development/sample seeder creates `admin@admin.com` with the requested password. The seeder will not run in production; the password will not be recorded in project documentation.
- Decision: retain `quantity` on shared products as specified. In this phase, an offer is eligible for lowest price when the offer and its vendor are active; vendor-specific stock is deferred.

## Ordered Implementation Steps

1. Add `database/migrations/<timestamp>_add_is_admin_to_users_table.php` (default false); cast `is_admin` on `app/Models/User.php` without adding it to `#[Fillable]`. Create `app/Http/Middleware/EnsureUserIsAdmin.php` and alias it in `bootstrap/app.php`. Protect admin screen routes with `auth` then admin middleware and API routes with `auth:sanctum` then admin middleware in `routes/web.php` and `routes/api.php`. Prohibit `is_admin` in `app/Http/Requests/Auth/RegisterRequest.php` and the API registration validation path; add access and request-assignment tests under `tests/Feature/`.
2. Create `database/migrations/<timestamp>_create_vendors_table.php`, `app/Models/Vendor.php`, its factory, vendor Form Requests, JSON resource, and `app/Http/Controllers/Api/Admin/VendorController.php`. Add the vendor React list/form/show screens in `resources/js/components/admin/VendorPages.jsx` and protected React mount host view `resources/views/admin-app.blade.php`. Fields: name, unique email, phone, address, city, country, contact person, notes, active state, timestamps, and soft deletes. Add vendor CRUD and unique-email feature tests.
3. Add migrations in this order: `create_products_table`, `create_product_images_table`, `create_product_vendor_table` under `database/migrations/`. Products have unique SKU and slug, indexed category key, quantity, active state, timestamps, and soft deletes. Images reference products and store path/order/primary state. Offers use unique `(product_id, vendor_id)`, positive price, optional vendor SKU, active state, timestamps, and lowest-price lookup indexes.
4. Create `app/Models/Product.php`, `ProductImage.php`, and the offer/pivot model with factories under `database/factories/`. Add `Product::vendors()`, `Product::offers()`, `Product::lowestOffer()`, and `lowestPrice`; lowest offer considers only active offers from active vendors. Add product Form Requests, JSON resources, and `app/Http/Controllers/Api/Admin/ProductController.php`. Build paginated product list and create/edit React components in `resources/js/components/admin/ProductPages.jsx`; support validated multiple public-disk images via `FormData`, image deletion, and repeatable unique-vendor price rows. Synchronize offer rows in one database transaction. Add product/image/offer and lowest-price feature tests.
5. Add the admin dashboard API controller and React dashboard screen with counts. Update `app/Http/Resources/UserResource.php` with an admin capability field and `resources/js/components/AuthApp.jsx` to show the Admin link only when true; mount admin screens from the React entry in `resources/js/app.js`. Add `database/seeders/AdminCatalogSeeder.php` and invoke it from `DatabaseSeeder.php` for one admin, vendors, products, and 2–3 differently priced offers per product; keep admin privilege out of request-controlled flows.
6. Update `docs/product/requirements.md`, `features.md`, `architecture/{backend,database,security}.md`, `api/endpoints.md`, and `status/current-state.md` to reflect the implemented admin-managed catalog and retain the out-of-scope boundary.

### Proposed Web Routes

- React mount pages under `/admin`, protected by `auth` and the admin middleware: dashboard, vendor index/create/show/edit, and product index/create/edit. Each returns the same minimal Blade host; React renders the screen for the current URL.
- JSON endpoints under `/api/admin`, protected by `auth:sanctum` and the admin middleware: dashboard counts; vendor resource CRUD; product resource CRUD (including the read endpoint used to populate the edit form); and `DELETE /products/{product}/images/{image}`.
- No new public catalog or unauthenticated API routes in this phase.

## Verification Results

- `php artisan migrate:fresh --seed --database=sqlite` passed against an isolated disposable SQLite database; the admin and sample catalog seeder completed. The configured MySQL database was not changed.
- Focused admin/catalog tests passed: 13 tests, 98 assertions.
- `php artisan test --compact` passed after cleanup: 17 tests, 117 assertions.
- `vendor/bin/pint --dirty --format agent` passed and formatted changed PHP files.
- `npm run build` passed; existing Sass/Tailwind deprecation and minifier warnings remain.
- Browser walkthrough passed: admin dashboard counts, vendor list/create/update/soft-delete, product list/create/edit and offer loading, and ordinary-user Admin-link visibility. Image upload/removal and validation are covered by feature tests.
- `git diff --check` passed after the README and cleanup edits.

## Approval Gate

Approved and implemented on 2026-10-11. Apply the pending migrations to the intended application database with `php artisan migrate`; migration execution against the configured MySQL database was not performed during this task.