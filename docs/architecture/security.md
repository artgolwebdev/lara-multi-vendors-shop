# Security Architecture

## Verified Controls

- Laravel UI web authentication uses Laravel's session guard configured in `config/auth.php`.
- `/home` applies the `auth` middleware; login/register controllers use `guest` middleware and logout uses `auth` middleware.
- Registration validates required name/email/password, unique email, and password confirmation. `User` uses Laravel's hashed password cast and explicitly fillable/hidden attribute lists.
- The web layout includes a CSRF token and the logout form includes `@csrf`.
- Password reset routes/controllers and token storage are present.

## Limits and Questions

No roles, policies, vendor/user tenancy, API token authentication, payment handling, or product-specific authorization rules were found. Email-verification files and a database field exist, but the route list does not show verification endpoints. Do not infer enforcement from the presence of those files or columns.

Secrets belong in environment configuration and must never be copied into documentation, plans, or logs. The repository's `.env.example` exposes variable names and blank/example values only; this documentation records names and purposes, never `.env` contents. For framework-level security implementation, use the installed Laravel Boost skills and documentation.