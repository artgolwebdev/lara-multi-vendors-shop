# Backend Architecture

## Verified Structure

- `routes/web.php` defines `/`, `Auth::routes()`, and named `home` at `/home`.
- Laravel UI's controllers under `app/Http/Controllers/Auth/` provide the authentication flows. `RegisterController` validates name, email uniqueness, and confirmed password, then creates a user.
- `HomeController` returns `resources/views/home.blade.php` and applies `auth` middleware in its constructor.
- `App\Models\User` is the only application model found. It uses Eloquent, `HasFactory`, and `Notifiable`; model attributes use `Fillable` and `Hidden` attributes and casts for hashed passwords and verification timestamps.
- `AppServiceProvider` has no application bindings or boot behavior. No application service/action/resource/policy layer was found.
- `bootstrap/app.php` configures web and console routes, JSON exception rendering for API-looking requests, and a health path. The configured JSON behavior does not itself establish API endpoints.

## Request Boundary

Requests are handled through Laravel's web route stack. Authentication is session based. Application-specific controllers should follow the existing structure and Laravel Boost guidance; introduce additional layers only when a confirmed feature needs them. See [components](../system-design/components.md) and [security](security.md).