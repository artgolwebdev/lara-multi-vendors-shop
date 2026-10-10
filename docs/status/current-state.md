# Current State

Last verified: 2026-10-10.

## Runtime and Dependencies

- PHP: 8.4.23 in the current workspace.
- Laravel Framework: 13.35.0 (composer constraint `^13.17`).
- Laravel Boost: 2.10.3 (`^2.10`), configured for Laravel Boost MCP.
- Laravel Sanctum: 4.3.3 (`^4.0`), added for stateful SPA/API authentication.
- Database: Boost's active connection reports MySQL. `config/database.php` and `.env.example` default to SQLite; runtime selection depends on environment.
- Frontend installed versions: Vite 8.3.4, Laravel Vite plugin 3.2.0, React/React DOM 18.3.1, Bootstrap 5.3.8, Tailwind CSS and its Vite plugin 4.3.3, Sass 1.105.1, Axios 1.20.0, and Vite React plugin 6.1.2.

## Implemented Application

- A React authentication page at `/`, with registration/login/logout through same-origin Sanctum session API endpoints. Laravel UI web authentication/password-reset routes and authenticated `/home` remain available.
- Auth API routes: `POST /api/register`, `POST /api/login`, `POST /api/logout`, and authenticated `GET /api/user`.
- One application domain model (`User`); Laravel infrastructure/auth schema plus Sanctum `personal_access_tokens`. Vendor/catalog/order/payment models remain unimplemented.
- Blade and React/Vite frontend with Bootstrap/Sass and Tailwind/Vite assets. No Inertia or Livewire integration was found.
- Five tests. Latest `php artisan test --compact` passed: 5 tests and 20 assertions.
- The Sanctum migration has been applied to the configured MySQL database in this environment; repository defaults still select SQLite where environment configuration does not override them.

## AI and Project Context

- Laravel Boost MCP remains configured in root `.mcp.json` as `php artisan boost:mcp`.
- `boost.json` enables guidelines, MCP, and skills for Claude Code and Cursor. Existing `.claude/skills/`, `.claude/mcp.json`, and `.cursor/mcp.json` are preserved.
- Root `AGENTS.md` contains Boost-managed guidance inside markers plus the project-owned task workflow outside them. `CLAUDE.md` points to that workflow.
- There is no project-level `.ai/rules/` directory or index. No Boost rules were added.

See [known issues and unknowns](known-issues.md), [changelog](changelog.md), [the initial plan](../plans/001-project-foundation.md), and [Step 1 authentication plan](../plans/002-react-api-auth.md).