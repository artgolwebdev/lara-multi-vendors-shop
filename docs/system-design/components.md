# System Components

| Component | Responsibility today | Main files |
| --- | --- | --- |
| Web routing | React auth mount page, Laravel UI auth route registration, home route | `routes/web.php` |
| API authentication | Sanctum stateful register/login/logout/current-user JSON routes | `routes/api.php`, `app/Http/Controllers/Api/AuthController.php` |
| React authentication | Registration/login UI and session state | `resources/js/components/AuthApp.jsx`, `resources/views/auth-app.blade.php` |
| Web authentication | Laravel UI session login, registration, logout, password reset | `app/Http/Controllers/Auth/`, `resources/views/auth/` |
| Home page | Authenticated dashboard response | `app/Http/Controllers/HomeController.php`, `resources/views/home.blade.php` |
| User persistence | Authentication identity and password casts | `app/Models/User.php`, `database/migrations/0001_01_01_000000_create_users_table.php` |
| Blade presentation | Server-rendered welcome, auth, and home layouts | `resources/views/` |
| Frontend build | CSS/Sass/JS compilation and React plugin | `vite.config.js`, `resources/css/`, `resources/sass/`, `resources/js/` |
| Framework infrastructure | Cache, queue, session, and migration tables | `database/migrations/` |
| Laravel Boost | Laravel-specific agent skills, guidelines, and MCP integration | `boost.json`, `.mcp.json`, `.claude/skills/`, `.cursor/mcp.json` |

There are no implemented product-domain components yet (catalog, vendors, orders, or payments). See [architecture overview](../architecture/overview.md) and [features](../product/features.md).