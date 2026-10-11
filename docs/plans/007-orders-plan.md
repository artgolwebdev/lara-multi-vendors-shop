# 007: Orders, Sub-Orders and Vendor Notifications

- ID: `007-orders`
- Status: proposed (revised: guest checkout + cash on delivery)
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: not completed

> Do not implement until approved. This plan follows plan 005/006 conventions
> (integer-cent math, decimal-string money, flat `/api` routes, React/Vite).

## Problem and Intended Outcome

A **guest or signed-in customer** can turn the existing guest cart into a
persisted order: the backend prices each line with
`CartQuoter`/`DiscountEngine`, groups lines into one sub-order per vendor,
stores full snapshots, and queues one `NotifyVendorJob` per sub-order. Checkout
collects customer contact + delivery address (autofilled from the profile for
signed-in users), and the only payment method is **cash on delivery**. Admins,
vendors and the customer (or a guest, via an access token) can then see the
order. No online payment, shipping or real notifications.

## Scope

### In Scope
- `orders`, `sub_orders`, `order_items` tables + models + factories.
- `PlaceOrderService` (single creation path) for **guests and customers** with
  vendor selection policy, stock locking, snapshots, idempotency, price-change
  protection.
- **Guest checkout**: no account required; customer contact + delivery address
  are collected at checkout and stored as snapshots; signed-in users get the
  form autofilled from their profile. A per-order `guest_token` lets a guest
  view their own confirmation.
- **Cash on delivery** as the only `payment_method` (stored, no gateway).
- `NotifyVendorJob` (simulated log notification) + queue auto-process flag.
- Customer/guest order APIs; admin order/vendor/customer order screens; checkout
  and confirmation pages; account "My orders" (registered users).
- Real `CustomerOrderHistory` so the loyalty first-order rule works end to end
  (guests have no history, so loyalty only applies to registered customers).
- Seeder + the backend tests listed below.

### Non-Goals
Online payment gateways/cards (cash on delivery only for now), shipping, tax,
real email/notifications, cancellation, refunds, restocking, vendor
login/portal, invoices/PDFs, customer order editing, coupons.

## References

- Requirements `docs/product/requirements.md`; architecture
  `docs/architecture/{backend,database,frontend,security}.md`; prior plans
  `docs/plans/004-discount-rules-plan.md`, `005-product-listing-and-cart-plan.md`,
  `006-customers-and-loyalty-discounts-plan.md`.

## Existing Implementation Findings (verified)

- `CartQuoter::quote($lines, $customer)` resolves an explicit or lowest active
  offer, delegates all math to the stateless `DiscountEngine`, and already
  returns per-line amounts plus vendor groups (`PriceQuote.vendors` with
  `lineIndexes`). It throws `ValidationException` keyed `lines.{i}.{field}`.
- Money: `Money::decimalToCents`/`centsToDecimal`; persisted values are
  `decimal(10,2)` cast to strings; never floats.
- `LoyaltyCustomerDiscountRule` calls `CustomerOrderHistory::completedOrdersCount`
  (stub returns 0), bound in `AppServiceProvider` to `DefaultCustomerOrderHistory`.
- `DashboardController`, admin CRUD, `CustomerController`, `VendorController` are
  the admin API pattern. Admin React screens are path-dispatched in `AdminApp`.
- Storefront is a React Router SPA (`StoreApp`); cart is Redux + `localStorage`,
  re-priced by `POST /api/cart/quote`; no price is computed client-side.
- Account area is a separate React SPA at `/account` (`AuthApp` → `AccountPanel`)
  with no router. The `CartPage` checkout button is currently disabled; the cart
  is stored in `localStorage`, so it survives an optional login redirect.
- Auth: `auth:sanctum` guards account/admin APIs and `bootstrap/app.php` aliases
  `admin`. `POST /api/orders` and the confirmation read must work **without** a
  bearer token for guests.
- Queue: `QUEUE_CONNECTION=database`; `jobs`/`job_batches`/`failed_jobs`
  migrations already exist. `composer dev` runs `php artisan dev`, which starts
  `serve`, `queue:listen --tries=1 --timeout=0`, and Vite.
- Tests: PHPUnit + `RefreshDatabase`, `QUEUE_CONNECTION=sync` in `phpunit.xml`.
  Laravel's testing transaction manager executes `afterCommit` callbacks when the
  service's inner transaction commits, so after-commit jobs are testable.

## Proposed Design

### 1. Database (migrations in this order)

`2026_10_11_120000_create_orders_table.php`,
`2026_10_11_120001_create_sub_orders_table.php`,
`2026_10_11_120002_create_order_items_table.php`.

- `orders`: `id`; `order_number` string unique; `user_id` FK nullable
  `nullOnDelete` (**null = guest order**); `guest_token` string(64) nullable
  unique (guest confirmation access); `status` string(20) index;
  `payment_method` string(30) default `cash_on_delivery`; `subtotal`,
  `discount_total`, `total` `decimal(10,2)`; customer snapshot
  `customer_name`, `customer_email`, `customer_phone`, `customer_address`;
  delivery snapshot `delivery_name`, `delivery_email`, `delivery_phone`,
  `delivery_address`; `notes` nullable; `placed_at` nullable + index;
  `idempotency_key` string(64) nullable **unique** (globally unique so it works
  for guests and users alike; UUIDs make collisions negligible). Index
  `(status, placed_at)`, `(user_id)`, `(customer_email)`.
- `sub_orders`: `id`; `order_id` FK cascade; `vendor_id` FK nullable
  `nullOnDelete`; vendor snapshot `vendor_name`, `vendor_email`,
  `vendor_phone`; `status` string(20) index; `subtotal`, `discount_total`,
  `total` `decimal(10,2)`; `notified_at` nullable; `notification_status`
  string(20) index; timestamps. Index `(order_id, vendor_id)`,
  `(vendor_id, status)`, `(notification_status)`.
- `order_items`: `id`; `order_id` FK cascade; `sub_order_id` FK cascade;
  `product_id` FK nullable `nullOnDelete`; `vendor_id` FK nullable
  `nullOnDelete`; product snapshot `product_name`, `product_sku`,
  `product_category`; `unit_price` `decimal(10,2)`; `quantity` unsignedInteger;
  `discount_amount` `decimal(10,2)` default 0; `applied_rule_id` unsignedBigInteger
  nullable (plain column, no FK, snapshot-safe), `applied_rule_name`,
  `applied_rule_type`, `applied_rules` json nullable; `line_total`
  `decimal(10,2)`; timestamps. Indexes on `order_id`, `sub_order_id`,
  `product_id`, `vendor_id`.

Snapshots are denormalised columns copied at creation; later product/vendor/rule
changes never touch existing rows.

### 2. Models, enums, history

- `app/Services/Orders/Enums/OrderStatus.php` (`Pending`, `Confirmed`, `Shipped`,
  `Delivered`, `Cancelled`) with `rank()` (Pending 0 … Delivered 3, Cancelled
  excluded) and `deriveFromSubOrders(iterable $statuses)`: drop `Cancelled`; if
  none remain, `Cancelled`; else the lowest-rank remaining status. *(Nested under
  `app/Services/Orders` → see decisions.)*
- `app/Services/Orders/Enums/NotificationStatus.php` (`Pending`, `Sent`, `Failed`).
- `app/Services/Orders/Enums/PaymentMethod.php` (single case `CashOnDelivery`),
  the default/forced value for `orders.payment_method`.
- `app/Models/Order.php`, `SubOrder.php`, `OrderItem.php`: fillable, casts
  (money `decimal:2`, `status`/`notification_status`/`payment_method` enums,
  `placed_at`/`notified_at` datetime, `applied_rules` array), relationships
  `Order hasMany SubOrder/OrderItem`, `SubOrder belongsTo Order/Vendor hasMany
  OrderItem`, `OrderItem belongsTo Order/SubOrder/Product/Vendor`. `SubOrder`
  uses `#[ObservedBy(SubOrderObserver::class)]`, whose `saved`/`deleted` recomputes
  `Order::syncStatusFromSubOrders()`. Add `User hasMany Order`.
- `app/Services/Discounts/Implementations/DatabaseCustomerOrderHistory.php`
  counts a registered customer's orders where `status !== cancelled`; guests
  never have history (and never match a loyalty rule because the discount
  context has no customer); bind it in `AppServiceProvider` and delete
  `DefaultCustomerOrderHistory`.

### 3. PlaceOrderService (`app/Services/Orders/`)

`PlaceOrderService::placeOrder(?User $customer, array $contact, Collection $lines,
array $delivery, ?string $expectedTotal = null, ?string $idempotencyKey = null,
?string $notes = null): Order`

`$customer` is null for a guest. `$contact` = `{name, email, phone, address}` —
required for a guest, defaults from the profile for a registered user (and may be
overridden). `$delivery` = `{name, email, phone, address}`.

1. **Idempotency pre-check**: if `idempotencyKey` is set and an order with that
   key exists and belongs to the same caller, return it — a signed-in caller must
   match `order.user_id`, and an unauthenticated caller must find `order.user_id =
   null` (the client-generated UUID key is the guest's capability). Otherwise
   throw `IdempotencyConflictException` (409) so a reused key from another caller
   cannot leak an order.
2. `DB::transaction`:
   a. Lock the customer row (`User::whereKey($id)->lockForUpdate()`) when
      `$customer` is set; guests skip this.
   b. Apply `config('shop.vendor_selection')` to each line: `customer_choice`
      passes the chosen `vendor_id` (default `null` → lowest); `lowest_price`
      forces `null`. Call `CartQuoter::quote()` **once** inside the transaction.
      Under `customer_choice` the quoter rejects an inactive vendor or a vendor
      that does not offer the product (422, `lines.{i}.vendor_id`).
   c. **Price-change guard**: if `expectedTotal` is set and differs from the
      quote `grand_total`, throw `PriceChangedException` (409, carries the quote),
      creating nothing.
   d. Aggregate quantity per product, `Product::whereIn(ids)->lockForUpdate()`,
      re-validate stock; on failure throw `OutOfStockException` (422, keyed
      `lines.{i}.quantity`). Decrement `products.quantity`.
   e. Create `Order` (customer `$contact` + delivery snapshots, totals,
      `placed_at = now()`, `status = pending`, `payment_method =
      cash_on_delivery`, `idempotency_key`; `guest_token = Str::random(40)` and
      `user_id = null` when `$customer` is null), then set `order_number =
      'GG-'.year.'-'.zero-padded id`.
   f. For each `PriceQuote.vendors` group create a `SubOrder` (vendor snapshot,
      group totals); for each quote line create an `OrderItem` in its group's
      sub-order with product/category snapshot, `unit_price`, `quantity`,
      `discount_amount`, primary `applied_rule_id/name/type`, full
      `applied_rules` JSON, `line_total`.
   g. Dispatch one `NotifyVendorJob($subOrder->id)->afterCommit()` per sub-order.
      Return the order (load sub-orders/items).
3. Wrap the unique-key race: catch `UniqueConstraintViolationException` and
   return the existing order. Re-run the loyalty check by re-quoting after the
   customer lock, so a parallel first order cannot spend the loyalty discount
   twice (the loser gets a 409 on `expected_total`).

Domain exceptions in `app/Services/Orders/Exceptions/`: `PriceChangedException`
(`render()` → 409 with `{message, data: quote}`), `OutOfStockException`
(`render()` → 422 with `errors`), and `IdempotencyConflictException`
(`render()` → 409). *(Nested under `app/Services/Orders` → see decisions.)*

### 4. NotifyVendorJob (`app/Jobs/NotifyVendorJob.php`)

- `implements ShouldQueue`; constructor `public function __construct(public int
  $subOrderId) {}` (primitive only); `$tries = 3`; `backoff(): array => [5, 15, 30]`.
- `handle()`: load `SubOrder` with `vendor` + `items`; if
  `notification_status === Sent`, return (idempotent, no duplicate log). Otherwise
  `Log::info('vendor.notification', [...])` with vendor id/name/email, order
  number, sub-order id, each item (name, SKU, quantity, unit price, discount),
  and subtotal/discount/total; then set `notified_at = now()`,
  `notification_status = Sent`.
- `failed(Throwable $e)`: set `notification_status = Failed`, `Log::error(...)`.
- Request path: `POST /api/admin/sub-orders/{subOrder}/notify` resets an
  unsent sub-order to `Pending` and re-dispatches the job (button shows only for
  `pending`/`failed`).

### 5. Queue auto-process (option A — recommended)

Add `config/shop.php`:
`vendor_selection => env('SHOP_VENDOR_SELECTION', 'customer_choice')`,
`queue_auto_process => env('SHOP_QUEUE_AUTO_PROCESS', env('APP_ENV', 'production') === 'local')`,
`queue_auto_process_timeout => env('SHOP_QUEUE_AUTO_PROCESS_TIMEOUT', 30)`.
Add the keys (commented, empty) to `.env` and `.env.example`.

`app/Services/Orders/QueueAutoProcessor.php`: when the flag is on, the order
controller (and the admin re-notify controller) calls
`$this->queueAutoProcessor->drainAfterResponse()`, which registers
`app()->terminating(fn () => ...)`. The callback runs `Artisan::call('queue:work',
['--stop-when-empty' => true, '--max-time' => timeout, '--tries' => 3])` inside
a try/catch. Terminating callbacks run from `Kernel::terminate()` after
`$response->send()`, and feature tests also call `terminate()`.

Comparison (recommend **A**):
- **A** real queued jobs in `jobs`, retries/`failed()` work, no worker to start.
  Limitation: under single-process `php artisan serve` the response may not flush
  to the socket until PHP finishes, so the customer can wait briefly; and while
  the drain runs the built-in server serves no other request. `composer dev`
  already runs `queue:listen`, so there the flag is a harmless extra drain.
- **B** `dispatchAfterResponse()` is simple but bypasses the `jobs` table
  (no retries/failed state, flag-off behaviour impossible) → rejected.
- **C** scheduled `queue:work` needs `schedule:work` running → does not meet
  "nothing to start".

With the flag off, jobs stay in `jobs` until a real worker runs; document this
and `php artisan queue:work` in the README.

### 6. API contracts

Orders (`OrderController`):
- `POST /api/orders` — **public (guest or authenticated)**. The controller
  resolves the customer with `$request->user('sanctum')` (null for a guest), so
  no bearer token is required.
- `GET /api/orders` — `auth:sanctum`, registered customers only, paginated
  newest-first (guests have no list).
- `GET /api/orders/{order}` — public; allowed when the authenticated user owns
  the order **or** the request supplies the matching `guest_token` (`?guest_token=`
  or `X-Guest-Token`, compared with `hash_equals`). Anything else → 404.
- Remove the `AccountController@orders` stub and its route; the account area
  calls `/api/orders`.

`POST /api/orders` request (`StoreOrderRequest`; only these fields accepted —
client prices and `payment_method` are dropped, the server forces
`cash_on_delivery`):
```json
{
  "customer": { "name": "Casey", "email": "casey@example.com", "phone": "+1 555 0100", "address": "12 Market Street" },
  "delivery": { "name": "Casey", "email": "casey@example.com", "phone": "+1 555 0100", "address": "12 Market Street" },
  "lines": [ { "product_id": 1, "quantity": 2, "vendor_id": 5 } ],
  "payment_method": "cash_on_delivery",
  "expected_total": "44.10",
  "idempotency_key": "b0f2c1e4-...",
  "notes": null
}
```
For a signed-in user `customer` may be omitted and defaults from the profile
(`GET /api/user`); the checkout form autofills it but the user may override.
`customer.name`/`customer.email`/`customer.phone` and `delivery.address` are
required for guests.

201 response:
```json
{ "data": {
  "id": 12, "order_number": "GG-2026-000012", "status": "pending",
  "payment_method": "cash_on_delivery",
  "subtotal": "49.00", "discount_total": "4.90", "total": "44.10",
  "placed_at": "2026-10-11T12:00:00+00:00", "guest_token": null,
  "customer": { "name": "Casey", "email": "casey@example.com", "phone": "+1 555 0100", "address": "12 Market Street" },
  "delivery": { "name": "Casey", "email": "casey@example.com", "phone": "+1 555 0100", "address": "12 Market Street" },
  "sub_orders": [ {
    "id": 30, "vendor": { "id": 5, "name": "North Star", "email": "hello@northstar.test" },
    "status": "pending", "subtotal": "20.00", "discount_total": "0.00", "total": "20.00",
    "notification_status": "pending", "notified_at": null,
    "items": [ { "id": 88, "product_id": 1, "product_name": "Stoneware Bowl", "product_sku": "BOWL-001",
      "quantity": 2, "unit_price": "10.00", "discount_amount": "0.00",
      "applied_rule": null, "applied_rules": [], "line_total": "20.00" } ]
  } ]
} }
```
For a guest order the response carries a non-null `guest_token`; the SPA stores
it (sessionStorage, keyed by order id) to fetch the confirmation.

409 (price changed, nothing created):
```json
{ "message": "Some prices or discounts changed since you reviewed your order.",
  "data": { "lines": [], "vendors": [], "subtotal": "50.00", "discount_total": "0.00", "grand_total": "50.00" } }
```

409 (idempotency key already used by another customer):
`{ "message": "This idempotency key is already in use." }`

Admin (`auth:sanctum` + `admin`):
`GET /api/admin/orders` (`vendor_id`, `customer_id`, `status`, `date_from`,
`date_to`, `search` order number, `per_page`; combinable), `GET
/api/admin/orders/{order}`, `PATCH /api/admin/sub-orders/{subOrder}/status`
(recomputes main status), `POST /api/admin/sub-orders/{subOrder}/notify`,
`GET /api/admin/vendors/{vendor}/orders` (+ `total_sales`),
`GET /api/admin/customers/{customer}/orders` (+ `lifetime_total`). A vendor
filter returns only orders containing that vendor and adds the vendor's share.

Resources: `OrderResource` (includes `payment_method`; emits `guest_token` only
in the create response for guests), `SubOrderResource`, `OrderItemResource`
(flat, reuse for admin with customer/vendor loaded).

### 7. Frontend

Store SPA (React Router, `store.css`):
- `CartPage`: enable "Proceed to checkout" for **everyone**; guests go straight to
  `/checkout` (the cart lives in `localStorage`). Optionally offer a "sign in for
  faster checkout" link (`/account?redirect=/checkout`) without blocking guests.
- `pages/CheckoutPage.jsx` (public): collect customer contact (name, email,
  phone) and delivery (name, email, phone, address); when signed in, prefill from
  `/api/user` (still editable), otherwise show a "sign in to autofill" hint. Show
  a fixed **Cash on delivery** payment method (no choice). Fetch the quote,
  generate one idempotency key (`crypto.randomUUID()`); render the per-vendor
  summary (items, selected vendor, unit price, discount + rule name incl. loyalty
  label, vendor subtotal, grand total); submit with a loading state (button
  disabled; key guards retries). Errors: 422 field/line errors and 409 → replace
  the shown quote, message "prices changed, confirm again", re-confirm with the
  same key (nothing was created). On success store the returned `guest_token` in
  `sessionStorage['order_token:{id}']` and navigate to the confirmation.
- `pages/OrderConfirmationPage.jsx` (`/order-confirmation/:orderId`): fetch
  `GET /api/orders/{id}` (appending `?guest_token=` from sessionStorage when
  present), show order number/status/payment method/totals and each vendor
  sub-order with items; clear the cart after success.
- `StoreApp.jsx` routes + `routes/web.php` `/checkout`,
  `/order-confirmation/{order}` → `store-app` (public).

Account SPA (`AuthApp`/`AccountPanel`, no router): replace the orders placeholder
with the real paginated list (number, date, total, status, vendor count) and an
inline detail view (sub-orders, items, discounts, statuses); "My discounts"
keeps working (after a first order the first-order rule disappears). Guests have
no account area — they only see the one confirmation page reachable via the
`guest_token`.

Admin SPA: `AdminLayout` nav "Orders"; `AdminApp` dispatch for `/admin/orders`;
new `components/admin/OrderPages.jsx` (index with URL-persisted, combinable
filters + pagination; detail with customer/delivery, per-vendor sub-orders and
items, notification status/time, sub-order status control, main totals; links to
customer/vendor pages). Extend `VendorPages` vendor detail and `CustomerPages`
customer detail with their order lists; `CustomerPages` list order count becomes
real automatically via the new history binding. Add admin styles.

### 8. Seeders and tests

- Factories `OrderFactory`, `SubOrderFactory`, `OrderItemFactory`;
  `DemoOrderSeeder` (dev-only) seeding customers with orders across vendors and
  statuses including one `failed` notification; call it from `DatabaseSeeder`.
- Tests (PHPUnit, `RefreshDatabase`; force `config(['queue.default' => 'database'])`
  for `jobs`-table assertions):
  - Vendor grouping: three vendors → 1 order, 3 sub-orders, correct items/totals.
  - `lowest_price` policy vs `customer_choice` (chosen vendor price; reject a
    vendor that does not offer the product).
  - Discounts: quantity, category, loyalty; stored totals equal the quote.
  - Loyalty first-order applies once, not on the second order, and not twice in
    parallel submissions.
  - Guest checkout: unauthenticated `POST /api/orders` creates an order with
    `user_id = null` and a returned `guest_token`; no loyalty discount is applied.
  - Guest confirmation: `GET /api/orders/{id}?guest_token=...` works, a wrong or
    missing token → 404, and `GET /api/orders` without auth → 401.
  - Payment: `payment_method` is forced to `cash_on_delivery`; a client-supplied
    `payment_method` is ignored.
  - Registered checkout: `customer` defaults from the profile and an override is
    stored.
  - Idempotency: same key returns the same order for the same customer; a key
    reused by another customer → 409.
  - `Queue::fake()`: one `NotifyVendorJob` per sub-order, none for a failed order.
  - Database queue: jobs dispatche*d only after commit (success) and no `jobs`
    row / no log on rollback.
  - Job: log spy contains expected data, `notified_at` set, retry does not
    duplicate, `failed()` marks `failed`.
  - Auto-process on → log + `notified_at` without a worker; off → job pending,
    no log.
  - Stock decrement and out-of-stock rejection with nothing created.
  - 409 price change; idempotency key returns the same order; client prices ignored.
  - Authorization: guest 401, customer sees own orders only, admin routes 403 for
    customers.
  - Snapshots survive later product-price/vendor-name changes.
  - Admin filters (vendor/customer/status/date) and vendor/customer order lists.
- Frontend: no JS runner → manual checklist.

## Implementation Tasks

- [ ] 1. Migrations `orders`, `sub_orders`, `order_items` (order above).
- [ ] 2. `app/Services/Orders/Enums/{OrderStatus,NotificationStatus,PaymentMethod}`;
      `Order`, `SubOrder`, `OrderItem` models (+ `SubOrderObserver`,
      `User hasMany Order`).
- [ ] 3. `DatabaseCustomerOrderHistory`; rebind `CustomerOrderHistory`; delete stub.
- [ ] 4. `app/Services/Orders/Exceptions/{PriceChangedException,OutOfStockException,IdempotencyConflictException}`.
- [ ] 5. `app/Services/Orders/` DTOs + `PlaceOrderService` (guest + customer);
      `config/shop.php`; `.env`/`.env.example` keys.
- [ ] 6. `app/Jobs/NotifyVendorJob.php`; `QueueAutoProcessor`.
- [ ] 7. Public `OrderController` + `StoreOrderRequest` + resources; routes
      (public create/confirmation, authed list).
- [ ] 8. Admin `OrderController` + sub-order status/notify endpoints; vendor/
      customer order endpoints; resources.
- [ ] 9. Web routes + `StoreApp` routes; `CheckoutPage`,
      `OrderConfirmationPage`; enable cart checkout; `store.css`.
- [ ] 10. `AccountPanel` orders list + inline detail.
- [ ] 11. Admin `OrderPages`, nav/dispatch, vendor/customer order sections, styles.
- [ ] 12. Factories + `DemoOrderSeeder`; tests; `pint`; `npm run build`; docs.
- [ ] 13. Update README (queue flag), `docs/{product,architecture,api,status}`,
      changelog, backlog; record results in this plan.

## Verification Checklist

- [ ] `php artisan migrate:fresh --seed` on a disposable DB succeeds.
- [ ] `php artisan test --compact` green; focused order suites green.
- [ ] Place a multi-vendor order as a **guest** (no login) in the browser; the
      confirmation page loads via the `guest_token`, and the DB row has
      `user_id = null` and `payment_method = cash_on_delivery`.
- [ ] Place a multi-vendor order as a customer **without** starting a queue
      worker; a `NotifyVendorJob` log entry per vendor appears in
      `storage/logs/laravel.log` shortly after the response and `notified_at` is set.
- [ ] Set `SHOP_QUEUE_AUTO_PROCESS=false`; the job stays in `jobs` and no log entry
      exists until `php artisan queue:work` runs.
- [ ] Admin: order index filters (vendor/customer/status/date) and detail; change a
      sub-order status and confirm the main status updates; re-notify a failed one.
- [ ] Vendor detail and customer detail show their order lists and totals.
- [ ] Customer "My orders" list + detail; "My discounts" hides the first-order rule
      after one order.
- [ ] `vendor/bin/pint --dirty --format agent` and `npm run build` clean.

## Resolved Decisions (from your answers)

1. **Guest checkout is required**: `POST /api/orders` is public and works with or
   without a bearer token; `orders.user_id` is null for guests. Customers + payment
   are **cash on delivery only** for now (`payment_method`, no gateway).
2. **Checkout collects customer contact + delivery address**; signed-in users get
   the form autofilled from their profile (editable), guests type it in.
3. **Queue auto-process = option A** (terminating drain via `queue:work
   --stop-when-empty --max-time`). Works with `php artisan serve`/`composer dev`
   and in tests; documented limitation: single-process serve may make the customer
   wait briefly and blocks the built-in server during the drain; `composer dev`
   already runs `queue:listen`.
4. **Enums and exceptions nest under `app/Services/Orders/`**
   (`app/Services/Orders/Enums`, `app/Services/Orders/Exceptions`) — no new base
   folders.
5. **Main order status = lowest rank wins**: the lowest rank among non-cancelled
   sub-orders (`pending < confirmed < shipped < delivered`); `cancelled` if all are
   cancelled. A `SubOrderObserver` keeps it in sync.

## Earlier Decisions (unchanged) and Open Questions

6. **Which statuses count for "first order"**: all placed orders except
   `cancelled` (pending/confirmed/shipped/delivered count). OK?
7. **Snapshots**: denormalised columns on orders/sub_orders/order_items; product/
   vendor/rule FKs nullable with `nullOnDelete`; the primary applied rule is
   stored in `applied_rule_id/name/type` **and** the full stack in `applied_rules`
   JSON. OK?
8. **Order number**: `GG-YYYY-` + zero-padded id (deterministic, unique). OK?
9. **`expected_total`** is the server-issued quote total echoed for change
   detection, never a trusted price; a mismatch returns 409 and creates nothing.
10. **404 vs 403** for another customer's order / a bad `guest_token`: I use 404
    (existing admin `CustomerController` uses `abort(404)` for hidden records).
11. **Guest confirmation access**: a per-order `guest_token` returned only in the
    create response, sent back as `?guest_token=` (or `X-Guest-Token`) for
    `GET /api/orders/{order}`. Alternative: a short-lived signed URL. OK?
12. **Idempotency key uniqueness**: a single globally-unique nullable column (works
    for guests and users); a reused key from a different customer returns 409.
    Alternative: scope per user/guest. OK?
