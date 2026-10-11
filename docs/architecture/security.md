# Security Architecture

## Verified Controls

- Laravel UI web authentication uses Laravel's session guard configured in `config/auth.php`.
- The React API uses Sanctum's stateful session authentication, CSRF protection, and throttling on registration and login; logout invalidates the session.
- Every admin web mount route applies `auth` and the `admin` middleware; admin JSON routes apply `auth:sanctum` and `admin`. Non-admins receive 403 responses.
- `UserResource` exposes the user's ID, name, email, and boolean admin capability. `is_admin` is not fillable, and both web and API registration prohibit the field.
- The development catalog seeder sets the requested admin account with a privileged model operation and exits in production. The credential is defined in the seeder only, not in documentation.
- Product image uploads validate image content, allowed MIME types, and file size before storing on the public disk. Product and offer writes use validated fields and a transaction.
- Sanctum token support is installed, but the first-party React flow does not issue personal access tokens.
- `/home` applies the `auth` middleware; login/register controllers use `guest` middleware and logout uses `auth` middleware.
- Registration validates required name/email/password, unique email, and password confirmation. `User` uses Laravel's hashed password cast and explicitly fillable/hidden attribute lists.
- The web layout includes a CSRF token and the logout form includes `@csrf`.
- Password reset routes/controllers and token storage are present.

## Limits and Questions

No vendor accounts, vendor/user tenancy, payment handling, or product-specific authorization rules are implemented. Admin status is a manually controlled database flag, except for the guarded non-production sample seeder. Email-verification files and a database field exist, but the route list does not show verification endpoints. Do not infer enforcement from the presence of those files or columns.

Secrets belong in environment configuration and must never be copied into documentation, plans, or logs. The repository's `.env.example` exposes variable names and blank/example values only; this documentation records names and purposes, never `.env` contents. For framework-level security implementation, use the installed Laravel Boost skills and documentation.