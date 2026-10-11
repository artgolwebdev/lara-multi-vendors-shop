# Feature Inventory

This inventory describes code verified in the repository, not an intended marketplace roadmap.

| Capability | Current implementation | Evidence |
| --- | --- | --- |
| React authentication page | `/login`, `/register`, and `/account` serve the Vite-mounted Goodgoods sign-in/registration experience and customer dashboard | `routes/web.php`, `app/Http/Controllers/Auth/`, `resources/views/auth-app.blade.php`, `resources/js/components/AuthApp.jsx` |
| API registration/login/logout | Sanctum stateful JSON endpoints; registration/login are throttled | `routes/api.php`, `app/Http/Controllers/Api/AuthController.php` |
| Authenticated API identity | `GET /api/user` returns the public user resource | `routes/api.php`, `app/Http/Resources/UserResource.php` |
| Web registration and login | Laravel UI web POST routes remain available; GET login/registration mount the React auth app | `routes/web.php`, `app/Http/Controllers/Auth/` |
| Authenticated home | `/home` uses `HomeController`; constructor applies `auth` middleware | `routes/web.php`, `app/Http/Controllers/HomeController.php` |
| Password reset | Laravel UI reset routes/controllers with Goodgoods-styled request and reset forms | `routes/web.php`, auth controllers/views |
| React/Vite integration | Vite mounts each React app (`#store-app`, `#auth-app`, `#admin-app`) from minimal Blade hosts | `resources/js/app.js`, `vite.config.js` |
| Admin access | `is_admin` flag and admin middleware protect admin pages and APIs | `app/Models/User.php`, `app/Http/Middleware/EnsureUserIsAdmin.php`, `bootstrap/app.php` |
| Admin catalog UI | React/Vite dashboard, vendor CRUD, and product CRUD | `resources/js/components/AdminApp.jsx`, `resources/js/components/admin/` |
| Vendor records | Admin-managed vendor CRUD with soft deletes | `app/Models/Vendor.php`, `app/Http/Controllers/Api/Admin/VendorController.php` |
| Shared catalog and offers | Products, public-disk images, vendor-specific offers, and lowest active offer | `app/Models/Product.php`, `ProductImage.php`, `ProductOffer.php`, `app/Http/Controllers/Api/Admin/ProductController.php` |
| Categories | Admin-managed category CRUD with unique names and reference-safe deletion | `app/Models/Category.php`, `app/Http/Controllers/Api/Admin/CategoryController.php`, `resources/js/components/admin/CategoryPages.jsx` |
| Discount rules | Admin CRUD for quantity, category, and loyalty rules with computed status, enable/disable, audience validation, and selected-customer targeting | `app/Models/DiscountRule.php`, `app/Http/Controllers/Api/Admin/DiscountRuleController.php`, `resources/js/components/admin/DiscountRulePages.jsx` |
| Discount engine | Stateless integer-cent engine with tagged rule implementations, cumulative stacking of matching discounts per line, first-order loyalty eligibility, and a no-write admin test panel | `app/Services/Discounts/`, `app/Http/Controllers/Api/Admin/DiscountTestController.php`, `app/Providers/AppServiceProvider.php` |
| Public storefront | React Router SPA at `/`, `/shop`, `/categories`, `/categories/{category}`, `/products/{product}`, and `/cart` | `resources/js/components/store/`, `routes/web.php` |
| Public catalog API | Read-only product listing/detail and category list/detail with search, filter, sort, and pagination | `app/Http/Controllers/Api/ProductController.php`, `CategoryController.php` |
| Guest cart and price quote | `localStorage` cart of IDs/quantities re-priced by a stateless endpoint; per-line discounts and per-vendor totals | `resources/js/store/cartSlice.js`, `app/Services/Cart/CartQuoter.php`, `app/Http/Controllers/Api/CartQuoteController.php` |
| Quantity discount hints | `{min_quantity, label}` hints attached to listing offers for rules that only apply above quantity 1 | `app/Services/Discounts/DiscountHintResolver.php` |
| Checkout and orders | Guest/customer checkout with server-side requote, price-change confirmation, idempotency, shared-stock decrement, and order snapshots | `app/Services/Orders/PlaceOrderService.php`, `app/Http/Controllers/Api/OrderController.php` |
| Vendor sub-orders and notifications | One sub-order per selected vendor group; after-commit queued job logs simulated notification and records status | `app/Models/SubOrder.php`, `app/Jobs/NotifyVendorJob.php` |
| Order UI and management | Checkout/confirmation pages, customer order history, admin search/filter and sub-order status/notification controls | `resources/js/components/store/pages/CheckoutPage.jsx`, `OrderConfirmationPage.jsx`, `resources/js/components/admin/OrderPages.jsx` |

Payment is currently cash on delivery; external payment providers are not integrated. Vendor notifications are log-only simulations and vendor accounts are not implemented. See [requirements](requirements.md) and [API surface](../api/endpoints.md).
