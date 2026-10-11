# Current HTTP Endpoints

Verified with `php artisan route:list --except-vendor` and `php artisan route:list --path=api`.

| Method | URI | Name / handler |
| --- | --- | --- |
| `GET|HEAD` | `/` | `store.home`, React storefront mount (`store-app`) |
| `GET|HEAD` | `/shop` | `store.shop`, React storefront mount (`store-app`) |
| `GET|HEAD` | `/categories` | `store.categories`, React storefront mount (`store-app`) |
| `GET|HEAD` | `/categories/{category}` | `store.category`, React storefront mount (`store-app`) |
| `GET|HEAD` | `/products/{product}` | `store.product`, React storefront mount (`store-app`) |
| `GET|HEAD` | `/cart` | `store.cart`, React storefront mount (`store-app`) |
| `GET|HEAD` | `/account` | `account`, React auth mount (`auth-app`) |
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

| `GET|HEAD` | `/checkout` | `store.checkout`, React storefront mount (`store-app`) |
| `GET|HEAD` | `/order-confirmation/{order}` | `store.order-confirmation`, React storefront mount (`store-app`) |
| `GET|HEAD` | `/admin/orders` | React admin orders list mount (`auth`, admin) |
| `GET|HEAD` | `/admin/orders/{order}` | React admin order details mount (`auth`, admin) |
| `GET|HEAD` | `/admin/customers` | React admin customers list mount (`auth`, admin) |
| `GET|HEAD` | `/admin/customers/{customer}` | React admin customer details mount (`auth`, admin) |

These are web routes, not a REST API. For auth context see [authentication](authentication.md).

## API Routes

| Method | URI | Authentication | Handler |
| --- | --- | --- | --- |
| `POST` | `/api/register` | Public, throttled | `AuthController@register` |
| `POST` | `/api/login` | Public, throttled | `AuthController@login` |
| `GET` | `/api/user` | Sanctum session/token | `AuthController@user` (includes admin capability) |
| `POST` | `/api/logout` | Sanctum session/token | `AuthController@logout` |
| `GET` | `/api/account/profile` | Sanctum session | `AccountController@profile` |
| `PUT` | `/api/account/profile` | Sanctum session | `AccountController@updateProfile` |
| `PUT` | `/api/account/password` | Sanctum session | `AccountController@updatePassword` |
| `GET` | `/api/account/discounts` | Sanctum session | `AccountController@discounts` |
| `GET` | `/api/orders` | Sanctum session | `OrderController@index` (customer's orders) |
| `POST` | `/api/orders` | Public / customer | `OrderController@store` (checkout submission) |
| `GET` | `/api/orders/{order}` | Customer session or guest token | `OrderController@show` (order detail/confirmation) |
| `GET` | `/api/admin/dashboard` | Admin session | `Admin\DashboardController` |
| `GET|POST` | `/api/admin/vendors` | Admin session | Vendor index/create |
| `GET|PUT|PATCH|DELETE` | `/api/admin/vendors/{vendor}` | Admin session | Vendor show/update/soft delete |
| `GET` | `/api/admin/vendors/{vendor}/orders` | Admin session | Vendor orders index |
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
| `GET` | `/api/admin/customers` | Admin session | Customer index with search/pagination |
| `GET` | `/api/admin/customers/{customer}` | Admin session | Customer show detail |
| `GET` | `/api/admin/customers/{customer}/loyalty-rules` | Admin session | Customer eligible loyalty rules |
| `POST|DELETE` | `/api/admin/customers/{customer}/loyalty-rules/{discountRule}` | Admin session | Attach/detach selected customer rule |
| `GET` | `/api/admin/customers/{customer}/orders` | Admin session | Customer orders index |
| `GET` | `/api/admin/orders` | Admin session | Orders index with filters |
| `GET` | `/api/admin/orders/{order}` | Admin session | Order detail with sub-orders and items |
| `PATCH` | `/api/admin/sub-orders/{subOrder}/status` | Admin session | Update sub-order fulfillment status |
| `POST` | `/api/admin/sub-orders/{subOrder}/notify` | Admin session | Re-send vendor sub-order notification |
| `GET` | `/api/products` | Public | `Api\ProductController@index` (search/category/sort/per_page) |
| `GET` | `/api/products/{product}` | Public | `Api\ProductController@show` |
| `GET` | `/api/categories` | Public | `Api\CategoryController@index` (active-product counts) |
| `GET` | `/api/categories/{category}` | Public | `Api\CategoryController@show` |
| `POST` | `/api/cart/quote` | Public | `Api\CartQuoteController` (stateless price quote) |

Admin web routes redirect guests to login and return 403 to non-admin users; admin APIs return 401 to guests and 403 to non-admins. Sanctum also registers `GET /sanctum/csrf-cookie` for CSRF cookie initialization. Public catalog, cart-quote, and order placement/read endpoints are implemented.