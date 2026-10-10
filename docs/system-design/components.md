# System Components

| Component | Responsibility today | Main files |
| --- | --- | --- |
| Web routing | Public welcome, auth route registration, home route | `routes/web.php` |
| Authentication | Laravel UI session login, registration, logout, password reset | `app/Http/Controllers/Auth/`, `resources/views/auth/` |
| Home page | Authenticated dashboard response | `app/Http/Controllers/HomeController.php`, `resources/views/home.blade.php` |
| User persistence | Authentication identity and password casts | `app/Models/User.php`, `database/migrations/0001_01_01_000000_create_users_table.php` |
| Blade presentation | Server-rendered welcome, auth, and home layouts | `resources/views/` |
| Frontend build | CSS/Sass/JS compilation and React plugin | `vite.config.js`, `resources/css/`, `resources/sass/`, `resources/js/` |
| Framework infrastructure | Cache, queue, session, and migration tables | `database/migrations/` |
| Laravel Boost | Laravel-specific agent skills, guidelines, and MCP integration | `boost.json`, `.mcp.json`, `.claude/skills/`, `.cursor/mcp.json` |

There are no verified product-domain components (catalog, vendors, orders, or payments). See [architecture overview](../architecture/overview.md) and [features](../product/features.md).