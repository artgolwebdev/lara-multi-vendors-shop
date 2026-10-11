# Backend Architecture

## Verified Structure

- `routes/web.php` serves the React mount view at `/`, retains `Auth::routes()`, and defines named `home` at `/home`.
- `routes/api.php` defines JSON registration, login, logout, and current-user endpoints. Sanctum stateful middleware enables same-origin session authentication.
- Laravel UI's controllers under `app/Http/Controllers/Auth/` provide the authentication flows. `RegisterController` validates name, email uniqueness, and confirmed password, then creates a user.
- `HomeController` returns `resources/views/home.blade.php` and applies `auth` middleware in its constructor.
- `App\Models\User` uses Eloquent, `HasFactory`, `Notifiable`, and Sanctum `HasApiTokens`; model attributes use `Fillable` and `Hidden` attributes and casts. `is_admin` is cast but deliberately not fillable.
- `Vendor`, `Category`, `Product`, `ProductImage`, `ProductOffer`, and `DiscountRule` model the admin-managed catalog. Vendors, products, and discount rules use soft deletes; `Product::lowestOffer()` selects an active offer from an active vendor; `DiscountRule::isActiveAt()` and `status()` evaluate the active window.
- `EnsureUserIsAdmin` is registered as the `admin` middleware alias. Web mount routes use `auth` then `admin`; JSON admin routes use `auth:sanctum` then `admin`.
- `AuthController` coordinates API session authentication. Admin Form Requests validate writes; API resources serialize catalog and discount data and `UserResource` exposes only public identity plus the admin capability flag.
- Product create/update synchronizes vendor offers in a database transaction and stores validated images on the public disk.
- `AppServiceProvider` tags `QuantityDiscountRule` and `CategoryDiscountRule` as `discount.rules` and binds a singleton `DiscountEngine`. The engine is stateless, performs a read-only active-rule query, and selects implementations via `DiscountRuleInterface::supports()`, so it never branches on rule types. `app/Services/Discounts` also holds integer-cent money helpers and immutable cart-line/result DTOs.
- `DiscountRuleController` provides category/rule CRUD, options, and status toggling; `DiscountTestController` resolves a product/vendor active offer and runs the engine without writing data.
- `bootstrap/app.php` configures web and console routes, JSON exception rendering for API-looking requests, and a health path. The configured JSON behavior does not itself establish API endpoints.

## Request Boundary

Web requests use Laravel's web middleware. API requests use the API middleware group with Sanctum's stateful frontend middleware for first-party React session cookies. The existing Laravel UI web authentication routes remain available. Order, checkout, cart, and public catalog APIs are not implemented. See [components](../system-design/components.md) and [security](security.md).