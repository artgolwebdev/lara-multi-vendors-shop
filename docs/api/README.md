# API and HTTP Surface

Web routes are registered through `routes/web.php` and Laravel UI. `routes/api.php` now contains the Step 1 authentication API; no vendor or product API endpoints are implemented yet. API routes receive Laravel's `/api` prefix and Sanctum stateful middleware supports same-origin React sessions.

Use [endpoints](endpoints.md) for the current route inventory and [authentication](authentication.md) for session behavior. Recheck `php artisan route:list --except-vendor` before documenting route changes.