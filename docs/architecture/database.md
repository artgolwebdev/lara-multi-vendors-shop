# Database Architecture

## Current Schema

The migrations create Laravel infrastructure/auth tables, a Sanctum token table, and the admin-managed catalog tables.

| Table | Purpose and notable keys |
| --- | --- |
| `users` | User identity; primary key `id`; unique `email`; nullable `email_verified_at`; password and remember token |
| `vendors` | Admin-managed vendor contact records; unique email; active flag; timestamps and soft deletes |
| `products` | Shared product catalog; unique slug and SKU; indexed category string; shared quantity; active flag; timestamps and soft deletes |
| `product_images` | Product image paths on the public disk, display order, primary-image flag; cascades on hard product deletion |
| `product_vendor` | Unique product/vendor offer with decimal price, optional vendor SKU, active flag, timestamps; composite index for product/active/price lookup |
| `password_reset_tokens` | Email-keyed reset token storage |
| `personal_access_tokens` | Sanctum polymorphic personal API token storage |
| `sessions` | Session payloads; primary key `id`; nullable indexed `user_id`; indexed activity time |
| `cache`, `cache_locks` | Database cache and lock storage |
| `jobs`, `job_batches`, `failed_jobs` | Database queue, batch, and failed-job storage |
| `migrations` | Laravel migration tracking |

`User` uses Sanctum's token trait and includes `is_admin` defaulting to false. `Product` has many images/offers and belongs to many vendors through `product_vendor`; offer eligibility for lowest price requires both the offer and vendor to be active. Product quantity is shared at the product level; vendor-specific stock is not modeled in this phase. Boost and the configured runtime connection reported MySQL for this task, while repository defaults in `config/database.php` and `.env.example` select SQLite. Treat the runtime connection as environment-specific; see [current state](../status/current-state.md).

Orders, sub-orders, carts, and discount-rule tables remain future work. See [product requirements](../product/requirements.md).