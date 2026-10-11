# System Components

| Component | Responsibility today | Main files |
| --- | --- | --- |
| Web routing | React auth mount page, Laravel UI auth route registration, home route | `routes/web.php` |
| API authentication | Sanctum stateful register/login/logout/current-user JSON routes | `routes/api.php`, `app/Http/Controllers/Api/AuthController.php` |
| React authentication | Registration/login UI and session state | `resources/js/components/AuthApp.jsx`, `resources/views/auth-app.blade.php` |
| Admin authorization | Admin flag, middleware alias, protected admin web and API routes | `app/Http/Middleware/EnsureUserIsAdmin.php`, `bootstrap/app.php`, `routes/` |
| Admin React workspace | Dashboard, vendor/category/product/discount-rule CRUD, image and offer controls, discount test panel | `resources/js/components/AdminApp.jsx`, `resources/js/components/admin/`, `resources/css/admin.css` |
| Catalog API | Dashboard counts, paginated vendor/product resources, validated writes | `app/Http/Controllers/Api/Admin/`, `app/Http/Requests/Admin/`, `app/Http/Resources/` |
| Discount API | Rule CRUD, form options, status toggle, and a no-write engine test endpoint | `app/Http/Controllers/Api/Admin/DiscountRuleController.php`, `DiscountTestController.php` |
| Discount engine | Stateless integer-cent calculator with tagged rule implementations and immutable DTOs | `app/Services/Discounts/`, `app/Providers/AppServiceProvider.php` |
| Catalog persistence | Vendor/category/product/image/offer/discount-rule models, relationships, lowest-offer query | `app/Models/Vendor.php`, `Category.php`, `Product.php`, `ProductImage.php`, `ProductOffer.php`, `DiscountRule.php` |
| Web authentication | Laravel UI session login, registration, logout, password reset | `app/Http/Controllers/Auth/`, `resources/views/auth/` |
| Home page | Authenticated dashboard response | `app/Http/Controllers/HomeController.php`, `resources/views/home.blade.php` |
| User persistence | Authentication identity and password casts | `app/Models/User.php`, `database/migrations/0001_01_01_000000_create_users_table.php` |
| Blade presentation | Server-rendered welcome, auth, and home layouts | `resources/views/` |
| Frontend build | CSS/Sass/JS compilation and React plugin | `vite.config.js`, `resources/css/`, `resources/sass/`, `resources/js/` |
| Framework infrastructure | Cache, queue, session, and migration tables | `database/migrations/` |
| Laravel Boost | Laravel-specific agent skills, guidelines, and MCP integration | `boost.json`, `.mcp.json`, `.claude/skills/`, `.cursor/mcp.json` |

Order, checkout, payment, and notification components remain unimplemented. See [architecture overview](../architecture/overview.md) and [features](../product/features.md).