# Feature Inventory

This inventory describes code verified in the repository, not an intended marketplace roadmap.

| Capability | Current implementation | Evidence |
| --- | --- | --- |
| React authentication page | `/` serves the Vite-mounted React registration/login screen | `routes/web.php`, `resources/views/auth-app.blade.php`, `resources/js/components/AuthApp.jsx` |
| API registration/login/logout | Sanctum stateful JSON endpoints; registration/login are throttled | `routes/api.php`, `app/Http/Controllers/Api/AuthController.php` |
| Authenticated API identity | `GET /api/user` returns the public user resource | `routes/api.php`, `app/Http/Resources/UserResource.php` |
| Web registration and login | Laravel UI `Auth::routes()` and its existing auth controllers/views remain available | `routes/web.php`, `app/Http/Controllers/Auth/` |
| Authenticated home | `/home` uses `HomeController`; constructor applies `auth` middleware | `routes/web.php`, `app/Http/Controllers/HomeController.php` |
| Password reset | Laravel UI reset form and submission routes/controllers | `routes/web.php`, auth controllers/views |
| React/Vite integration | Vite mounts the authentication component at the root page | `resources/js/app.js`, `vite.config.js` |
| Admin access | `is_admin` flag and admin middleware protect admin pages and APIs | `app/Models/User.php`, `app/Http/Middleware/EnsureUserIsAdmin.php`, `bootstrap/app.php` |
| Admin catalog UI | React/Vite dashboard, vendor CRUD, and product CRUD | `resources/js/components/AdminApp.jsx`, `resources/js/components/admin/` |
| Vendor records | Admin-managed vendor CRUD with soft deletes | `app/Models/Vendor.php`, `app/Http/Controllers/Api/Admin/VendorController.php` |
| Shared catalog and offers | Products, public-disk images, vendor-specific offers, and lowest active offer | `app/Models/Product.php`, `ProductImage.php`, `ProductOffer.php`, `app/Http/Controllers/Api/Admin/ProductController.php` |

Public product listing, cart, checkout, orders, payment, notifications, and discount rules remain unimplemented. See [requirements](requirements.md) and [API surface](../api/endpoints.md).