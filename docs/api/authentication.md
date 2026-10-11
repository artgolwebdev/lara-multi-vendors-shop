# Web Authentication

The Goodgoods React authentication app is mounted at `/login`, `/register`, and `/account`. `/login` and `/register` select their respective modes from the path; `/account` retains its tabbed guest screen and signed-in customer dashboard. The app uses Laravel Sanctum's stateful SPA authentication with the existing `web` session guard. `POST /api/register` and `POST /api/login` establish a session; login accepts an optional boolean `remember` value; `POST /api/logout` invalidates the session; `GET /api/user` returns the authenticated user's public fields. Register and login require the Sanctum CSRF cookie and are throttled. Axios sends same-origin credentials and the XSRF token. Login sends users to a same-origin `redirect` query target or Laravel's stored intended URL.

Laravel UI's `Auth::routes()` remains enabled for web login, registration, logout, and password reset/confirmation. Web GET login/registration mount the React app; web POST routes continue through Laravel UI controllers. Password reset email and reset forms use the Goodgoods Blade layout. The authenticated `/home` route remains protected by `HomeController`'s `auth` middleware.

The repository includes email-verification controller/view files and a verification timestamp column, but the verified route list does not expose verification routes. Treat this as unconfirmed/inactive until routing and configuration are verified.

Sanctum's personal access token support is installed for future API clients, but Step 1's React app uses session cookies rather than issuing tokens. See [security architecture](../architecture/security.md) and [route inventory](endpoints.md).
