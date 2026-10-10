# Backend Architecture

## Verified Structure

- `routes/web.php` serves the React mount view at `/`, retains `Auth::routes()`, and defines named `home` at `/home`.
- `routes/api.php` defines JSON registration, login, logout, and current-user endpoints. Sanctum stateful middleware enables same-origin session authentication.
- Laravel UI's controllers under `app/Http/Controllers/Auth/` provide the authentication flows. `RegisterController` validates name, email uniqueness, and confirmed password, then creates a user.
- `HomeController` returns `resources/views/home.blade.php` and applies `auth` middleware in its constructor.
- `App\Models\User` is the only application domain model found. It uses Eloquent, `HasFactory`, `Notifiable`, and Sanctum `HasApiTokens`; model attributes use `Fillable` and `Hidden` attributes and casts for hashed passwords and verification timestamps.
- `AuthController` coordinates API session authentication; Form Requests validate registration/login and `UserResource` limits serialized identity fields.
- `AppServiceProvider` has no application bindings or boot behavior. `UserResource` is the only application API resource; no service/action/policy layer was found.
- `bootstrap/app.php` configures web and console routes, JSON exception rendering for API-looking requests, and a health path. The configured JSON behavior does not itself establish API endpoints.

## Request Boundary

Web requests use Laravel's web middleware. API requests use the API middleware group with Sanctum's stateful frontend middleware for first-party React session cookies. The existing Laravel UI web authentication routes remain available. Vendor/catalog APIs are not yet implemented. See [components](../system-design/components.md) and [security](security.md).