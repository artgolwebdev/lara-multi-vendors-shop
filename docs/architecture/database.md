# Database Architecture

## Current Schema

The migrations create Laravel infrastructure/auth tables and a Sanctum token table. No marketplace tables or application relationships beyond the nullable session-to-user reference were found.

| Table | Purpose and notable keys |
| --- | --- |
| `users` | User identity; primary key `id`; unique `email`; nullable `email_verified_at`; password and remember token |
| `password_reset_tokens` | Email-keyed reset token storage |
| `personal_access_tokens` | Sanctum polymorphic personal API token storage |
| `sessions` | Session payloads; primary key `id`; nullable indexed `user_id`; indexed activity time |
| `cache`, `cache_locks` | Database cache and lock storage |
| `jobs`, `job_batches`, `failed_jobs` | Database queue, batch, and failed-job storage |
| `migrations` | Laravel migration tracking |

`User` is the only application domain model and uses Sanctum's token trait. The `users` migration does not declare marketplace relationships. Boost and the configured runtime connection reported MySQL for this task, while repository defaults in `config/database.php` and `.env.example` select SQLite. Treat the runtime connection as environment-specific; see [current state](../status/current-state.md).

The confirmed task defines a future catalog/offers schema, but no marketplace schema has been implemented yet. See [product requirements](../product/requirements.md).