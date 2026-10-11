# Backend Architecture

## Verified Structure

- `routes/web.php` serves the storefront React mount view at `/` and its page routes, the auth mount at `/account`, retains `Auth::routes()`, and defines named `home` at `/home`.
- `routes/api.php` defines JSON registration, login, logout, and current-user endpoints, public catalog/cart-quote/order endpoints, protected customer account/order endpoints, and protected admin catalog/customer/order endpoints. Sanctum stateful middleware enables same-origin session authentication.
- Laravel UI's controllers under `app/Http/Controllers/Auth/` provide the authentication flows. `RegisterController` validates name, email uniqueness, and confirmed password, then creates a user.
- `HomeController` returns `resources/views/home.blade.php` and applies `auth` middleware in its constructor.
- `App\Models\User` uses Eloquent, `HasFactory`, `Notifiable`, and Sanctum `HasApiTokens`; model attributes use `Fillable` and `Hidden` attributes and casts. `is_admin` is cast but deliberately not fillable.
- `Vendor`, `Category`, `Product`, `ProductImage`, `ProductOffer`, and `DiscountRule` model the admin-managed catalog. Vendors, products, and discount rules use soft deletes; `Product::lowestOffer()` selects an active offer from an active vendor; `DiscountRule::isActiveAt()` and `status()` evaluate the active window.
- `EnsureUserIsAdmin` is registered as the `admin` middleware alias. Web mount routes use `auth` then `admin`; JSON admin routes use `auth:sanctum` then `admin`.
- `AuthController` coordinates API session authentication. Admin Form Requests validate writes; API resources serialize catalog and discount data and `UserResource` exposes only public identity plus the admin capability flag.
- Product create/update synchronizes vendor offers in a database transaction and stores validated images on the public disk.
- `AppServiceProvider` tags `QuantityDiscountRule`, `CategoryDiscountRule`, and `LoyaltyCustomerDiscountRule` as `discount.rules` and binds a singleton `DiscountEngine`. The engine is stateless, performs a read-only active-rule query, and selects implementations via `DiscountRuleInterface::supports()`, so it never branches on rule types. `app/Services/Discounts` also holds integer-cent money helpers and immutable cart-line/result DTOs.
- `DiscountRuleController` provides category/rule CRUD, options, and status toggling; `DiscountTestController` resolves a product/vendor active offer and runs the engine without writing data.
- `app/Services/Cart/CartQuoter` resolves explicit or lowest active offers, delegates all discount math to `DiscountEngine::calculate()`, groups totals by vendor, rejects unknown/inactive offers and over-stock quantities with line-keyed `ValidationException`s, and exposes `quoteListing()` so listing/detail pages price every offer in a single engine call.
- `app/Services/Discounts/DiscountHintResolver` reads active quantity rules to attach `{min_quantity, label}` hints on listing offers without changing the pricing contract. Public controllers `Api\ProductController`, `Api\CategoryController`, and `Api\CartQuoteController` serve the read-only catalog and stateless quote endpoints (`CartQuoteRequest` validates the payload shape).
- `bootstrap/app.php` configures web and console routes, JSON exception rendering for API-looking requests, and a health path. The configured JSON behavior does not itself establish API endpoints.
- `OrderController` validates guest/customer checkout and order reads; `PlaceOrderService` applies the vendor-selection policy and shared-stock locks, creates the order/sub-order/item snapshots in a transaction, and dispatches one `NotifyVendorJob` per sub-order after commit. `OrderResource` and related resources serialize the result.
- `NotifyVendorJob` simulates vendor notification by writing a structured log entry and updating notification state. `QueueAutoProcessor` optionally drains the queue after the response in local environments; other environments need a configured queue worker.

## Request Boundary

Web requests use Laravel's web middleware. API requests use the API middleware group with Sanctum's stateful frontend middleware for first-party React session cookies. The existing Laravel UI web authentication routes remain available. Public catalog, quote, guest/customer checkout, customer order reads, and admin order-management APIs are implemented. Payment is limited to cash on delivery; there is no external payment gateway or vendor account/tenant flow. See [components](../system-design/components.md) and [security](security.md).
