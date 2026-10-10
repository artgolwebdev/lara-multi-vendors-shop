# Current State

Last verified: 2026-10-10.

## Runtime and Dependencies

- PHP: 8.4.23 in the current workspace.
- Laravel Framework: 13.35.0 (composer constraint `^13.17`).
- Laravel Boost: 2.10.3 (`^2.10`), configured for Laravel Boost MCP.
- Database: Boost's active connection reports MySQL. `config/database.php` and `.env.example` default to SQLite; runtime selection depends on environment.
- Frontend installed versions: Vite 8.3.4, Laravel Vite plugin 3.2.0, React/React DOM 18.3.1, Bootstrap 5.3.8, Tailwind CSS and its Vite plugin 4.3.3, Sass 1.105.1, Axios 1.20.0, and Vite React plugin 6.1.2.

## Implemented Application

- A Laravel welcome page, Laravel UI session authentication/password reset, and an authenticated `/home` page.
- One application model (`User`) and only framework/auth/cache/session/queue schema; no vendor/catalog/order/payment models or API endpoints were found.
- Blade server-rendered pages with Bootstrap/Sass and Tailwind/Vite assets, plus one conditional React example component. No Inertia or Livewire integration was found.
- Two example tests. Baseline `php artisan test --compact` passed: 2 tests and 2 assertions.

## AI and Project Context

- Laravel Boost MCP remains configured in root `.mcp.json` as `php artisan boost:mcp`.
- `boost.json` enables guidelines, MCP, and skills for Claude Code and Cursor. Existing `.claude/skills/`, `.claude/mcp.json`, and `.cursor/mcp.json` are preserved.
- Root `AGENTS.md` contains Boost-managed guidance inside markers plus the project-owned task workflow outside them. `CLAUDE.md` points to that workflow.
- There is no project-level `.ai/rules/` directory or index. No Boost rules were added.

See [known issues and unknowns](known-issues.md), [changelog](changelog.md), and [the initial plan](../plans/001-project-foundation.md).