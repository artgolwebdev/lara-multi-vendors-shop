# Current HTTP Endpoints

Verified with `php artisan route:list --except-vendor` and `php artisan route:list --path=api`.

| Method | URI | Name / handler |
| --- | --- | --- |
| `GET|HEAD` | `/` | Closure returning `auth-app` (React mount) |
| `GET|HEAD` | `/home` | `home`, `HomeController@index` (authenticated) |
| `GET|HEAD` | `/admin` | `admin.dashboard`, React admin dashboard mount (`auth`, admin) |
| `GET|HEAD` | `/admin/vendors` | React vendor list mount (`auth`, admin) |
| `GET|HEAD` | `/admin/vendors/create` | React vendor form mount (`auth`, admin) |
| `GET|HEAD` | `/admin/vendors/{vendor}` | React vendor details mount (`auth`, admin) |
| `GET|HEAD` | `/admin/vendors/{vendor}/edit` | React vendor edit mount (`auth`, admin) |
| `GET|HEAD` | `/admin/products` | React product list mount (`auth`, admin) |
| `GET|HEAD` | `/admin/products/create` | React product form mount (`auth`, admin) |
| `GET|HEAD` | `/admin/products/{product}/edit` | React product edit mount (`auth`, admin) |
| `GET|HEAD` | `/admin/categories` | React category list mount (`auth`, admin) |
| `GET|HEAD` | `/admin/categories/create` | React category form mount (`auth`, admin) |
| `GET|HEAD` | `/admin/categories/{category}/edit` | React category edit mount (`auth`, admin) |
| `GET|HEAD` | `/admin/discount-rules` | React discount-rule list mount (`auth`, admin) |
| `GET|HEAD` | `/admin/discount-rules/create` | React discount-rule form mount (`auth`, admin) |
| `GET|HEAD` | `/admin/discount-rules/{discountRule}/edit` | React discount-rule edit mount (`auth`, admin) |
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

These are web routes, not a REST API. For auth context see [authentication](authentication.md).

## API Routes

| Method | URI | Authentication | Handler |
| --- | --- | --- | --- |
| `POST` | `/api/register` | Public, throttled | `AuthController@register` |
| `POST` | `/api/login` | Public, throttled | `AuthController@login` |
| `GET` | `/api/user` | Sanctum session/token | `AuthController@user` (includes admin capability) |
| `POST` | `/api/logout` | Sanctum session/token | `AuthController@logout` |
| `GET` | `/api/admin/dashboard` | Admin session | `Admin\DashboardController` |
| `GET|POST` | `/api/admin/vendors` | Admin session | Vendor index/create |
| `GET|PUT|PATCH|DELETE` | `/api/admin/vendors/{vendor}` | Admin session | Vendor show/update/soft delete |
| `GET|POST` | `/api/admin/categories` | Admin session | Category index/create |
| `GET|PUT|PATCH|DELETE` | `/api/admin/categories/{category}` | Admin session | Category show/update/delete (409 while referenced) |
| `GET|POST` | `/api/admin/products` | Admin session | Product index/create |
| `GET|PUT|PATCH|DELETE` | `/api/admin/products/{product}` | Admin session | Product edit data/update/soft delete |
| `DELETE` | `/api/admin/products/{product}/images/{image}` | Admin session | Product image removal |
| `GET` | `/api/admin/discount-rules/options` | Admin session | Discount-rule form options (types, categories, vendors, products) |
| `POST` | `/api/admin/discount-rules/test` | Admin session | Runs the discount engine against a product/vendor offer without writing data |
| `PATCH` | `/api/admin/discount-rules/{discountRule}/status` | Admin session | Set or toggle a rule's active status |
| `GET|POST` | `/api/admin/discount-rules` | Admin session | Discount-rule index/create |
| `GET|PUT|PATCH|DELETE` | `/api/admin/discount-rules/{discountRule}` | Admin session | Discount-rule show/update/soft delete |

Admin web routes redirect guests to login and return 403 to non-admin users; admin APIs return 401 to guests and 403 to non-admins. Sanctum also registers `GET /sanctum/csrf-cookie` for CSRF cookie initialization. Public catalog, order, or payment endpoints are not implemented.