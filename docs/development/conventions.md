# Development Conventions

## Verified Repository Patterns

- Keep web route definitions in `routes/web.php`; the current pages use Blade views and Laravel UI auth controllers.
- Keep application controllers in `app/Http/Controllers/` and application models in `app/Models/`.
- The `User` model uses PHP attributes for fillable/hidden fields and a `casts()` method.
- Migrations define the current database structure; database-backed cache, sessions, and queues are present in configuration/migrations.
- Vite entry points and frontend dependencies are defined in `vite.config.js`, `package.json`, and the corresponding `resources/` files.

These observations are not a replacement for framework rules. Follow the project's Boost-managed Laravel guidance and relevant skills, and inspect neighboring code before introducing a new pattern. Add a durable Boost project rule only for a codebase-supported convention; the current documentation workflow is maintained by `AGENTS.md`, not a Laravel rule.

See [architecture](../architecture/overview.md) and [testing conventions](testing.md).