# Database Architecture

## Current Schema

The inspected migrations create Laravel infrastructure tables and a users table; no marketplace tables or application relationships beyond the nullable session-to-user reference were found.

| Table | Purpose and notable keys |
| --- | --- |
| `users` | User identity; primary key `id`; unique `email`; nullable `email_verified_at`; password and remember token |
| `password_reset_tokens` | Email-keyed reset token storage |
| `sessions` | Session payloads; primary key `id`; nullable indexed `user_id`; indexed activity time |
| `cache`, `cache_locks` | Database cache and lock storage |
| `jobs`, `job_batches`, `failed_jobs` | Database queue, batch, and failed-job storage |
| `migrations` | Laravel migration tracking |

`User` is the only application model. The `users` migration does not declare Eloquent relationships. Boost's live schema snapshot reported MySQL for its active connection, while repository defaults in `config/database.php` and `.env.example` select SQLite. Treat the runtime connection as environment-specific; see [current state](../status/current-state.md).

Schema changes must be grounded in confirmed product requirements and Laravel Boost's migration guidance. No proposed marketplace schema is recorded.