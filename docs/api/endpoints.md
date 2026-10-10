# Current HTTP Endpoints

Verified with `php artisan route:list --except-vendor`.

| Method | URI | Name / handler |
| --- | --- | --- |
| `GET|HEAD` | `/` | Closure returning `welcome` |
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