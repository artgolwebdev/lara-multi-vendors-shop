# Database Architecture

## Current Schema

The migrations create Laravel infrastructure/auth tables, a Sanctum token table, and the admin-managed catalog tables.

| Table | Purpose and notable keys |
| --- | --- |
| `users` | User identity; primary key `id`; unique `email`; nullable `email_verified_at`; password and remember token |
| `vendors` | Admin-managed vendor contact records; unique email; active flag; timestamps and soft deletes |
| `categories` | Admin-managed product categories; unique name; timestamps |
| `products` | Shared product catalog; unique slug and SKU; required `category_id` foreign key (restrict on delete); shared quantity; active flag; timestamps and soft deletes |
| `product_images` | Product image paths on the public disk, display order, primary-image flag; cascades on hard product deletion |
| `product_vendor` | Unique product/vendor offer with decimal price, optional vendor SKU, active flag, timestamps; composite index for product/active/price lookup |
| `discount_rules` | Admin-managed quantity/category/loyalty rules; string `type`, `discount_type`, and non-null `audience`, decimal `value`, nullable `min_quantity`, loyalty condition, nullable vendor/product/category filters and start/end dates, active flag, priority, timestamps, soft deletes, and active/window/priority indexes |
| `discount_rule_user` | Pivot linking selected-customer loyalty rules to users |
| `orders` | Customer/order snapshot, optional registered user or guest token, status/payment method, totals, address/contact snapshots, idempotency key, and placed timestamp |
| `sub_orders` | One vendor snapshot and fulfillment/notification state per vendor group in an order, with totals and notification timestamp |
| `order_items` | Order and sub-order line snapshots for product/vendor identity, price, quantity, discount and applied rule breakdown |
| `password_reset_tokens` | Email-keyed reset token storage |
| `personal_access_tokens` | Sanctum polymorphic personal API token storage |
| `sessions` | Session payloads; primary key `id`; nullable indexed `user_id`; indexed activity time |
| `cache`, `cache_locks` | Database cache and lock storage |
| `jobs`, `job_batches`, `failed_jobs` | Database queue, batch, and failed-job storage |
| `migrations` | Laravel migration tracking |

`User` uses Sanctum's token trait and includes `is_admin` defaulting to false. `Product` belongs to a `Category`, has many images/offers, and belongs to many vendors through `product_vendor`; offer eligibility for lowest price requires both the offer and vendor to be active. `DiscountRule` optionally filters by vendor, product, or category and exposes an active/date-window check. Product quantity is shared at the product level; vendor-specific stock is not modeled in this phase. Plans 001-004 migrations have been applied to the Boost-connected MySQL database; repository defaults in `config/database.php` and `.env.example` select SQLite. Treat the runtime connection as environment-specific; see [current state](../status/current-state.md).

The `products.category` string was replaced by `category_id` in plan 004; the conversion migration backfills categories from existing values before dropping the column. Checkout reuses the stateless cart quote logic and persists order, sub-order and item snapshots. Cart contents remain browser-side (localStorage); server-side stock is shared per product rather than per vendor. See [product requirements](../product/requirements.md).
