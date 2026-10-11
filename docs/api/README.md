# API and HTTP Surface

Web routes are registered through `routes/web.php` and Laravel UI. `routes/api.php` contains the authentication API, the admin catalog APIs, and the admin category and discount-rule APIs. API routes receive Laravel's `/api` prefix and Sanctum stateful middleware supports same-origin React sessions.

Use [endpoints](endpoints.md) for the current route inventory and [authentication](authentication.md) for session behavior. Recheck `php artisan route:list --except-vendor` before documenting route changes.