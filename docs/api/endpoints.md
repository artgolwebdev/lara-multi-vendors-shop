# Current HTTP Endpoints

Verified with `php artisan route:list --except-vendor` and `php artisan route:list --path=api`.

| Method | URI | Name / handler |
| --- | --- | --- |
| `GET|HEAD` | `/` | Closure returning `auth-app` (React mount) |
| `GET|HEAD` | `/home` | `home`, `HomeController@index` (authenticated) |
| `GET|HEAD` | `/login` | `login`, `Auth\LoginController@showLoginForm` |
| `POST` | `/login` | `Auth\LoginController@login` |
| `POST` | `/logout` | `logout`, `Auth\LoginController@logout` |
| `GET|HEAD` | `/password/confirm` | `password.confirm`, confirmation form |
| `POST` | `/password/confirm` | Confirmation submission |
| `POST` | `/password/email` | `password.email`, reset email request |
| `GET|HEAD` | `/password/reset` | `password.request`, reset request form |
| `POST` | `/password/reset` | `password.update`, reset submission |
| `GET|HEAD` | `/password/reset/{token}` | `password.reset`, token form |
| `GET|HEAD` | `/register` | `register`, registration form |
| `POST` | `/register` | Registration submission |

These are web routes, not a REST API. There are no verified vendor, catalog, order, or payment endpoints. For auth context see [authentication](authentication.md).

## API Routes

| Method | URI | Authentication | Handler |
| --- | --- | --- | --- |
| `POST` | `/api/register` | Public, throttled | `AuthController@register` |
| `POST` | `/api/login` | Public, throttled | `AuthController@login` |
| `GET` | `/api/user` | Sanctum session/token | `AuthController@user` |
| `POST` | `/api/logout` | Sanctum session/token | `AuthController@logout` |

Sanctum also registers `GET /sanctum/csrf-cookie` for CSRF cookie initialization. No vendor, catalog, order, or payment API routes are implemented.