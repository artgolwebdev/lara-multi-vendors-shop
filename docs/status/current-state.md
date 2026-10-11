# Current State

Last verified: 2026-10-11.

## Runtime and Dependencies

- PHP: 8.4.23 in the current workspace.
- Laravel Framework: 13.35.0 (composer constraint `^13.17`).
- Laravel Boost: 2.10.3 (`^2.10`), configured for Laravel Boost MCP.
- Laravel Sanctum: 4.3.3 (`^4.0`), added for stateful SPA/API authentication.
- Database: Boost's active connection reports MySQL. `config/database.php` and `.env.example` default to SQLite; runtime selection depends on environment.
- Frontend installed versions: Vite 8.3.4, Laravel Vite plugin 3.2.0, React/React DOM 18.3.1, Bootstrap 5.3.8, Tailwind CSS and its Vite plugin 4.3.3, Sass 1.105.1, Axios 1.20.0, and Vite React plugin 6.1.2.

## Implemented Application

- A React authentication page at `/`, with registration/login/logout through same-origin Sanctum session API endpoints. Laravel UI web authentication/password-reset routes and authenticated `/home` remain available.
- `is_admin` defaults false, is excluded from mass assignment, and is rejected by web/API registration. `EnsureUserIsAdmin` protects all admin screens and JSON endpoints.
- Admin React screens provide dashboard counts, vendor CRUD, and product CRUD with image upload/removal and per-vendor pricing. APIs paginate catalog lists; offers sync in a transaction.
- Domain models include `User`, `Vendor`, `Product`, `ProductImage`, and `ProductOffer`; the schema includes vendor/product soft deletes, image storage metadata, and indexed product/vendor offers.
- `DatabaseSeeder` calls a development-only sample seeder for the requested admin account and example vendor/catalog data. It does not seed the admin in production.
- Blade and React/Vite frontend with Bootstrap/Sass and Tailwind/Vite assets. No Inertia, Livewire, or client-side router package is used.
- PHPUnit: 17 tests and 117 assertions pass, covering admin authorization, vendor/product CRUD, upload validation, offer sync and lowest price, and registration privilege boundaries.
- The Sanctum migration has been applied to the configured MySQL database in this environment; repository defaults still select SQLite where environment configuration does not override them.
- Plan 003 migrations passed on disposable SQLite; the configured MySQL database was not changed. Apply pending migrations to the intended database with `php artisan migrate`.

## AI and Project Context

- Laravel Boost MCP remains configured in root `.mcp.json` as `php artisan boost:mcp`.
- `boost.json` enables guidelines, MCP, and skills for Claude Code and Cursor. Existing `.claude/skills/`, `.claude/mcp.json`, and `.cursor/mcp.json` are preserved.
- Root `AGENTS.md` contains Boost-managed guidance inside markers plus the project-owned task workflow outside them. `CLAUDE.md` points to that workflow.
- There is no project-level `.ai/rules/` directory or index. No Boost rules were added.

See [known issues and unknowns](known-issues.md), [changelog](changelog.md), [the initial plan](../plans/001-project-foundation.md), and [Step 1 authentication plan](../plans/002-react-api-auth.md).