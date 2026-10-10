# API and HTTP Surface

The inspected route list contains 13 web routes, all registered through `routes/web.php` and Laravel UI. No `routes/api.php` or product API endpoints were found. The `/api/*` JSON exception-rendering condition in `bootstrap/app.php` is not an API implementation.

Use [endpoints](endpoints.md) for the current route inventory and [authentication](authentication.md) for session behavior. Recheck `php artisan route:list --except-vendor` before documenting route changes.