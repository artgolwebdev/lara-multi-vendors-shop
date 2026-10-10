# Web Authentication

The React client at `/` uses Laravel Sanctum's stateful SPA authentication with the existing `web` session guard. `POST /api/register` and `POST /api/login` establish a session; `POST /api/logout` invalidates it; `GET /api/user` returns the authenticated user's public fields. Register and login require the Sanctum CSRF cookie and are throttled. Axios sends same-origin credentials and the XSRF token.

Laravel UI's `Auth::routes()` remains enabled for web login, registration, logout, and password reset/confirmation. The authenticated `/home` route remains protected by `HomeController`'s `auth` middleware.

The repository includes email-verification controller/view files and a verification timestamp column, but the verified route list does not expose verification routes. Treat this as unconfirmed/inactive until routing and configuration are verified.

Sanctum's personal access token support is installed for future API clients, but Step 1's React app uses session cookies rather than issuing tokens. See [security architecture](../architecture/security.md) and [route inventory](endpoints.md).