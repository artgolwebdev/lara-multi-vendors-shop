# Integrations

## Verified Tooling Integrations

- Laravel Boost MCP is configured in root `.mcp.json` to run `php artisan boost:mcp`.
- `boost.json` enables guideline, MCP, and skill management for Claude Code and Cursor. The checked-in `.claude/skills/` and MCP config files are existing tooling configuration and are preserved.
- Vite, Laravel Vite, and the React plugin provide the frontend build pipeline.
- Blade/Sass references Bunny Fonts for Nunito in the current starter layout/styles.

## Not Found

No product API provider, payment gateway, external identity provider, object-storage integration, or deployment platform configuration was found in the inspected root files. Laravel's generic service/config options are not evidence of an active integration. See [current state](../status/current-state.md).