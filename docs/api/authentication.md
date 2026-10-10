# Web Authentication

Authentication is Laravel UI's session-based web flow using the `web` guard and Eloquent `User` provider. `Auth::routes()` registers login, registration, logout, and password reset/confirmation routes. The `home` route is protected by `HomeController`'s `auth` middleware. Successful registration and login redirect to `/home` in the controllers.

The repository includes email-verification controller/view files and a verification timestamp column, but the verified route list does not expose verification routes. Treat this as unconfirmed/inactive until routing and configuration are verified.

There is no documented API authentication mechanism because no API endpoints were found. See [security architecture](../architecture/security.md) and [route inventory](endpoints.md).