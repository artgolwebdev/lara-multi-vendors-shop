# 005: Product Listing and Shopping Cart

- ID: `005-product-listing-and-cart`
- Status: completed
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: 2026-10-11

> Implemented. See "Implementation Results" at the end for actuals and the
> deviations from this plan.

## Problem and Intended Outcome

Guests can browse the catalog and build a cart without registering or reloading
the page. Products, categories, per-vendor offers, discounts and all money come
from the backend; the browser only sends `{product_id, vendor_id, quantity}` and
renders what the API returns. Orders/checkout are out of scope.

## Scope

### In Scope
- A reusable backend price-quote service that resolves offers and delegates all
  discount math to the existing `DiscountEngine`.
- Public listing/detail APIs (`/api/products`, `/api/products/{product}`,
  `/api/categories`, `/api/categories/{category}`) and `POST /api/cart/quote`.
- SPA storefront (React Router + Redux) with home, shop, by-category, product
  and cart pages, plus a header cart indicator.
- Client-side cart persisted in `localStorage` (IDs and quantities only).
- Backend feature + unit tests; manual frontend checklist.

### Non-Goals
- Order creation, sub-orders, `NotifyVendorJob`, checkout, payment.
- `LoyaltyCustomerDiscountRule`, server-side carts, customer accounts for carts,
  wishlist, reviews.
- Vendor self-service, changing the admin area, or versioning the API.
- Vendor-specific stock (stock stays the shared `products.quantity`).

## References

- Requirements: `docs/product/requirements.md`, `features.md`, `user-flows.md`
- Architecture: `docs/architecture/{backend,frontend,database,security}.md`
- Prior plans: `docs/plans/003-admin-vendors-products-plan.md`,
  `docs/plans/004-discount-rules-plan.md`

## Existing Implementation Findings

Verified:
- `DiscountEngine` (`app/Services/Discounts/DiscountEngine.php`) is stateless,
  loads active/in-window rules, stacks every matching rule per line, caps at the
  line subtotal, and returns immutable `DiscountResult` DTOs. Money is integer
  cents internally via `Money` and serialized as decimal strings.
- `CartLine` (`productId, vendorId, quantity, unitPriceCents, categoryId`);
  `DiscountLineResult::toArray()` already exposes `unit_price`, `line_subtotal`,
  `discount_amount`, `applied_rule_id`, `applied_rule_name`, `applied_rules`,
  `line_total`.
- `Product::lowestOffer()` selects the minimum-price active offer from an active
  vendor; `Product.quantity` is the shared stock; `Product` has `category`,
  `images`, `offers.vendor`.
- API convention: flat `/api` routes, controllers under
  `app/Http/Controllers/Api/`, Form Requests under `app/Http/Requests/`, flat
  JSON resources under `app/Http/Resources/`, responses wrapped in `data`.
- Frontend: React 18 + Vite + Axios (`window.axios`), Bootstrap/Sass plus
  hand-written `auth.css`/`admin.css`. The React auth SPA is mounted at `/`
  (`auth-app`); admin at `/admin/*` (`admin-app`). **No client router and no
  Redux** are installed. There is no JS test runner.

Inferences:
- The discount engine runs one read-only `discount_rules` query per `calculate`
  call, so a page-level listing price computation should batch all offers into a
  single `calculate` call.

Open questions: none blocking (see decisions).

## Proposed Design

### 1. Backend pricing service (`CartQuoter`)

New namespace `app/Services/Cart/` (mirrors `app/Services/Discounts/`).

- `app/Services/Cart/Data/CartLineInput.php` — readonly DTO
  `{int productId, int quantity, ?int vendorId}`.
- `app/Services/Cart/Data/PriceQuoteLine.php` — readonly DTO holding the resolved
  line: product id/name/slug, primary image url, category id/name, selected
  vendor id/name, `unitPriceCents`, `lowestPriceCents`, lowest vendor id/name,
  `quantity`, `stock`, `lineSubtotalCents`, `discountAmountCents`,
  `appliedRules`, `lineTotalCents`, `isAvailable`, `issue`. `toArray()`
  serializes money with `Money::centsToDecimal`.
- `app/Services/Cart/Data/PriceQuote.php` — readonly DTO with `lines`, vendor
  groups, `subtotalCents`, `discountTotalCents`, `grandTotalCents`, `toArray()`.
  Vendor groups: `{vendor_id, vendor_name, subtotal, discount_total, total,
  line_indexes}` (this becomes sub-orders later).
- `app/Services/Cart/CartQuoter.php`:
  - `quote(Collection $lines): PriceQuote` — `$lines` is
    `Collection<int, CartLineInput>`.
    1. Batch-load products (`category` + primary image) and their active offers
       from active vendors.
    2. Resolve each line: explicit `vendor_id` → that vendor's active offer, else
       the lowest active offer. Throw `ValidationException` keyed
       `lines.{i}.vendor_id` when the product/vendor offer is missing or the
       offer/vendor is inactive, and `lines.{i}.product_id` when the product is
       missing/inactive.
    3. Throw `ValidationException` keyed `lines.{i}.quantity` when
       `quantity > product.quantity` (stock).
    4. Build `App\Services\Discounts\Data\CartLine` objects (unit price from the
       resolved offer) and call `DiscountEngine::calculate()` **once**.
    5. Map engine line results back onto `PriceQuoteLine` (adding product/vendor/
       lowest-offer context and the selected vendor's discount hint) and group
       totals by vendor.
  - `quoteListing(Collection $products): array` — returns
    `[productId => ['offers' => [...], 'default_vendor' => ...]]`. Builds one
    `CartLine` per `(product, active offer)` at quantity 1, calls the engine
    once, and attaches `price`, `discounted_price`, `discount_amount`,
    `applied_rule`, `is_lowest`, and `discount_hint` to each offer. Used by the
    listing/detail controllers so no pricing branch is duplicated.

Stock rule: `quantity` must be an integer ≥ 1 and ≤ `products.quantity`. The
service is the single source of truth and is designed so future order creation
can call `quote()` unchanged.

### 2. Discount hints (quantity rules only)

Quantity rules cannot be reflected at quantity 1, so the listing/detail response
carries a hint per offer. Add `app/Services/Discounts/DiscountHintResolver.php`:
- Loads active quantity rules once (`DiscountRule::TYPE_QUANTITY`, active,
  in-window) and, for a `(product_id, vendor_id)`, returns the highest-priority
  matching rule's hint as `{min_quantity, label}`, e.g.
  `{"min_quantity": 2, "label": "Buy 2+ and get 10% off"}` (percentage) or
  `"...save $1.00 each"` (fixed). Returns `null` when no rule matches.
- Matching uses the rule's public `product_id`/`vendor_id` filter fields only;
  all pricing still comes from the engine. (Alternative — adding
  `hint()` to `DiscountRuleInterface` — is listed in the decisions.)

### 3. Backend API

Public routes appended to `routes/api.php` (no auth; guests allowed):

| Method | URI | Handler |
| --- | --- | --- |
| `GET` | `/api/products` | `Api\ProductController@index` |
| `GET` | `/api/products/{product}` | `Api\ProductController@show` |
| `GET` | `/api/categories` | `Api\CategoryController@index` |
| `GET` | `/api/categories/{category}` | `Api\CategoryController@show` |
| `POST` | `/api/cart/quote` | `Api\CartQuoteController` |

`GET /api/products` query params: `search` (name or SKU), `category` (id),
`sort` (`price_asc|price_desc|name_asc|name_desc`, default `name_asc`),
`per_page` (capped), `page`. Only active products that have at least one active
offer from an active vendor are returned. Eager-load `category`, `images`,
`offers.vendor`; apply `price` sort via a subquery on the lowest active offer.

`app/Http/Controllers/Api/ProductController.php` builds the page, calls
`CartQuoter::quoteListing()` once, sets the computed array as a transient
attribute on each model, and returns
`ProductListingResource::collection($paginator)`. `show` does the same for one
product and adds full images/description via `ProductDetailResource`.

Request/response examples

`GET /api/products?search=bowl&category=2&sort=price_asc`:
```json
{
  "data": [
    {
      "id": 1,
      "name": "Stoneware Bowl",
      "slug": "stoneware-bowl",
      "sku": "BOWL-001",
      "stock": 25,
      "in_stock": true,
      "category": { "id": 2, "name": "Kitchen" },
      "image": { "id": 3, "url": "/storage/products/bowl.jpg", "is_primary": true },
      "default_vendor": { "id": 5, "name": "North Star" },
      "offers": [
        {
          "vendor_id": 5,
          "vendor_name": "North Star",
          "is_lowest": true,
          "price": "16.25",
          "discounted_price": "14.63",
          "discount_amount": "1.63",
          "applied_rule": { "id": 9, "name": "Kitchen 10%" },
          "discount_hint": { "min_quantity": 2, "label": "Buy 2+ and get 10% off" }
        }
      ]
    }
  ],
  "links": { "first": "...", "prev": null, "next": null, "last": "..." },
  "meta": { "current_page": 1, "per_page": 12, "total": 1 }
}
```

`POST /api/cart/quote` request:
```json
{ "lines": [ { "product_id": 1, "quantity": 2, "vendor_id": 5 } ] }
```
Response:
```json
{
  "data": {
    "lines": [
      {
        "product_id": 1, "product_name": "Stoneware Bowl", "product_slug": "stoneware-bowl",
        "image": "/storage/products/bowl.jpg",
        "category": { "id": 2, "name": "Kitchen" },
        "selected_vendor": { "id": 5, "name": "North Star" },
        "unit_price": "16.25",
        "lowest_price": "16.25",
        "lowest_vendor": { "id": 5, "name": "North Star" },
        "quantity": 2, "stock": 25,
        "line_subtotal": "32.50",
        "discount_amount": "3.25",
        "applied_rule": { "id": 9, "name": "Buy more save more" },
        "applied_rules": [ { "rule_id": 9, "rule_name": "Buy more save more", "amount": "3.25" } ],
        "line_total": "29.25"
      }
    ],
    "subtotal": "32.50",
    "discount_total": "3.25",
    "grand_total": "29.25",
    "vendors": [
      { "vendor_id": 5, "vendor_name": "North Star", "subtotal": "32.50",
        "discount_total": "3.25", "total": "29.25", "line_indexes": [0] }
    ]
  }
}
```

`app/Http/Requests/CartQuoteRequest.php` validates the shape:
- `lines` required array, `min:1`, `max:50` (empty cart and too many lines → 422).
- `lines.*.product_id` required integer `exists:products,id` (non-deleted).
- `lines.*.quantity` required integer `min:1` (zero/negative → 422).
- `lines.*.vendor_id` nullable integer `exists:vendors,id` (non-deleted).
- Business failures (unknown/inactive vendor offer, over stock, no active offer)
  raise `ValidationException` keyed by `lines.{i}.{field}`, so the frontend can
  show the problem on the exact line.

Resources added (flat, matching convention): `ProductListingResource`,
`ProductDetailResource`, `CategoryListingResource`, `CartQuoteResource`
(wraps `PriceQuote::toArray()`).

Prices are never read from the client: only IDs and quantities are accepted.

### 4. Frontend

New packages (see decisions): `react-router-dom`, `react-redux`,
`@reduxjs/toolkit`.

Routing / mounting
- New Blade host `resources/views/store-app.blade.php` (`#store-app`,
  `@vite(['resources/js/app.js'])`).
- `routes/web.php`: `/` → `store-app`; add `GET /shop`, `/categories`,
  `/categories/{category}`, `/products/{product}`, `/cart` → `store-app`; move
  the existing React auth SPA from `/` to `GET /account` → `auth-app`.
  `/admin/*`, Laravel UI auth and `/home` are unchanged.
- `resources/js/app.js`: mount `StoreApp` when `#store-app` exists (keep the
  existing `#auth-app` / `#admin-app` mounts).

State (Redux Toolkit)
- `resources/js/store/store.js` — `configureStore`, loads cart from
  `localStorage` as `preloadedState`, subscribes and persists the cart slice
  (key `goodgoods.cart.v1`).
- `resources/js/store/cartSlice.js` — `lines: [{ product_id, vendor_id,
  quantity }]`, plus `quote`, `quoteStatus`, `quoteError`, `fieldErrors`.
  Actions: `addLine`, `removeLine`, `setQuantity`, `setVendor`, `clearCart`,
  and `fetchQuote` (`createAsyncThunk` using `window.axios`, an `AbortController`
  and a monotonically increasing request id so the latest response wins).
  Selectors: `selectCartCount`, `selectCartLines`, `selectQuote`.
- Catalog fetching stays in page components with `window.axios` + local state
  (same pattern as the admin pages), including loading skeleton / empty / error
  with retry.

Components (`resources/js/components/store/`)
- `StoreApp.jsx` — `<Provider>` + `<BrowserRouter>`; routes `/`, `/shop`,
  `/categories`, `/categories/:categoryId`, `/products/:productId`, `/cart`.
- `StoreLayout.jsx` — header (wordmark, nav, search, `CartIndicator`), footer,
  `<Outlet/>`.
- `pages/HomePage.jsx`, `pages/ShopPage.jsx`, `pages/CategoriesPage.jsx`,
  `pages/CategoryPage.jsx`, `pages/ProductPage.jsx`, `pages/CartPage.jsx`.
- `ProductCard.jsx` (image, name, category, SKU, stock state, `VendorSelect`,
  price + struck-through original + rule badge + quantity hint, `AddToCart`).
- `VendorSelect.jsx` (defaults to lowest vendor, options show name + price,
  cheapest labelled "Best price"; switching updates the card from data already
  in the listing response — no new request).
- `CartLine.jsx` (image, name, vendor select re-quote, quantity control,
  remove, unit price, applied rule, line total, "Cheaper at Vendor X" hint).
- `QuantityControl.jsx`, `Skeletons.jsx`, `EmptyState.jsx`, `ErrorState.jsx`.
- `useDebouncedValue.js`, `formatMoney.js` (display formatting only).
- `resources/css/store.css` — new stylesheet reusing the `auth.css`/`admin.css`
  visual tokens (forest/amber palette, DM Sans/Manrope), imported in `app.js`.

Cart behaviour: every add/remove/quantity/vendor change dispatches `fetchQuote`
(debounced on typed quantity). While `quoteStatus === 'loading'` the totals area
shows a loading state; on failure a recoverable error with retry is shown;
per-line validation errors are mapped from `fieldErrors` onto the matching line.
The cart persists across refresh via `localStorage`. A disabled "Proceed to
checkout" button is shown.

Security: no pricing or discounts computed in the browser; the client only sends
IDs/quantities; public APIs are read-only plus the stateless quote endpoint.

## Implementation Steps

1. Add `app/Services/Discounts/DiscountHintResolver.php` + `DiscountHint` DTO.
2. Add `app/Services/Cart/Data/{CartLineInput,PriceQuoteLine,PriceQuote}.php` and
   `app/Services/Cart/CartQuoter.php`; bind nothing extra in
   `AppServiceProvider` (the engine is already a singleton).
3. Add `app/Http/Requests/CartQuoteRequest.php`.
4. Add public controllers `Api/ProductController.php`, `Api/CategoryController.php`,
   `Api/CartQuoteController.php` and resources `ProductListingResource`,
   `ProductDetailResource`, `CategoryListingResource`, `CartQuoteResource`.
5. Register the public routes in `routes/api.php`.
6. `npm install react-router-dom react-redux @reduxjs/toolkit`; add
   `store-app.blade.php`; update `routes/web.php` (storefront at `/` + page
   routes, auth SPA moved to `/account`); mount `StoreApp` in `app.js`; add
   `store.css`.
7. Implement the Redux cart store/slice with `localStorage` persistence and the
   debounced, abortable quote thunk.
8. Implement store components and pages (layout, cards, vendor select, quantity
   control, cart, skeletons/states).
9. Backend tests (below); run `vendor/bin/pint --dirty`, `npm run build`.
10. Update `docs/{product,architecture,api,status}` and `backlog.md`; record
    verification results and remaining work in this plan.

## Testing Strategy and Results

Backend feature tests (PHPUnit, `RefreshDatabase`):
- `tests/Feature/StorefrontProductListingTest.php` — lowest vendor is default;
  inactive vendors/offers excluded; search by name and SKU, category filter and
  all sorts; discounted price and quantity hint present; out-of-stock flag.
- `tests/Feature/StorefrontProductDetailTest.php` — detail shape and offers.
- `tests/Feature/StorefrontCategoryTest.php` — category list/counts and
  products-by-category.
- `tests/Feature/CartQuoteTest.php` — no-vendor uses lowest; chosen vendor uses
  its price and still reports the lowest; quantity rule threshold applies only
  for the same product **and** vendor and does not combine across vendors;
  vendor grouping totals; validation errors for invalid vendor, inactive
  offer/vendor, over stock, zero/negative quantity, empty cart; client-supplied
  prices are ignored; **query count is flat as product count grows**.
- `tests/Unit/Services/Cart/CartQuoterTest.php` — quote with a fake/real engine:
  offer resolution, stock rejection, mapping and vendor grouping.
- `tests/Unit/Services/Discounts/DiscountHintResolverTest.php` — hint matching
  and label formatting.

Frontend: no JS test runner exists, so no automated frontend tests. Manual
checklist:
- `php artisan migrate:fresh --seed` on a disposable DB; load `/`, `/shop`,
  a category page, a product page.
- Add the same product from two different vendors; confirm two lines and the
  correct per-vendor discounts.
- Change a line's vendor; confirm the price re-quotes (backend) and the quantity
  discount changes when the threshold moves with the vendor.
- Hit a quantity threshold; confirm the discount and rule name appear.
- Remove a line; confirm totals update. Refresh the page; confirm the cart
  persists from `localStorage`.
- Simulate a stale line (delete the offer/stock server-side) and confirm the
  error shows on that line and can be fixed/removed.

## Verification Checklist

- [x] `php artisan test --compact` — 97 passed, 490 assertions (storefront/cart suites included).
- [x] `vendor/bin/pint --dirty --format agent` (Pint passed) and `npm run build` (Vite built clean).
- [ ] Manual browser walkthrough as listed above — not performed in this environment; the SPA host routes are covered by `tests/Feature/StorefrontPagesTest.php`, but interactive cart/discount behaviour was not exercised in a browser.

## Implementation Results

Completed 2026-10-11.

- Backend: `CartQuoter`, `DiscountHintResolver` (+ `DiscountHint`), cart DTOs,
  `CartQuoteRequest`, `ProductListingResource`/`ProductDetailResource`/
  `CategoryListingResource`, `Api\ProductController`, `Api\CategoryController`,
  `Api\CartQuoteController`, and the public routes were added as designed. Public
  controllers are imported under the aliases `PublicProductController` /
  `PublicCategoryController` in `routes/api.php` to avoid colliding with the
  admin `Api\Admin\*` controllers.
- Frontend: storefront SPA (`StoreApp`, `StoreLayout`, `QuoteSync`), six pages,
  `ProductCard`, `VendorSelect`, `CartLine`, `QuantityControl`,
  `Skeletons`/`EmptyState`/`ErrorState`, `useApi`, `formatMoney`,
  `useDebouncedValue`; Redux store/slice with `localStorage` persistence and the
  debounced, abortable `fetchQuote` thunk; `resources/css/store.css`; auth SPA
  moved to `/account`.
- Tests added: `StorefrontProductListingTest`, `StorefrontProductDetailTest`,
  `StorefrontCategoryTest`, `CartQuoteTest`, `StorefrontPagesTest`,
  `tests/Unit/Services/Cart/CartQuoterTest`,
  `tests/Unit/Services/Discounts/DiscountHintResolverTest`.

### Deviations from this plan

- **No `CartQuoteResource`.** `Api\CartQuoteController` returns
  `['data' => $quote->toArray()]` directly; the other resources were added as
  planned.
- **Quote query-count test lives in the listing suite.** `CartQuoteRequest`'s
  `exists:products,id` / `exists:vendors,id` rules issue a per-line `count(*)`
  query, so the "flat query count as the catalog grows" assertion is on the
  listing endpoint (`StorefrontProductListingTest`), which batches pricing, and
  not on the quote endpoint.
- **Route `/products/{product}` uses the numeric id** (not `slug`) for
  consistency with the admin area; `ProductDetailResource` still returns `slug`.

### Still unverified

- The interactive manual walkthrough (add/remove/vendor-switch/quantity-threshold,
  stale-line recovery, and cart persistence across refresh) was not run in a
  browser here. The storefront host routes return 200 and mount `#store-app`
  (feature-tested), and `npm run build` succeeds, but the JS UI was not driven
  end-to-end.

## Decisions I Made / Questions for You

- **Where cart state lives:** client-side only, in Redux, persisted to
  `localStorage` as `{product_id, vendor_id, quantity}`. Guests can use it. No
  server cart in this phase. OK?
- **Stock handling:** the shared `products.quantity` is the only stock. The
  listing shows an `in_stock` flag and disables add-to-cart at 0; the quote
  rejects `quantity > stock` with a `lines.{i}.quantity` error. No vendor-level
  stock. OK?
- **How quote errors are shown:** the Form Request rejects structural problems
  (empty/oversized cart, zero/negative quantity) with 422; the service raises
  `ValidationException` keyed `lines.{i}.{field}` for unknown/inactive vendor,
  no active offer, and over-stock. The frontend maps those keys onto the matching
  cart line while keeping the last good quote for the others. Prefer this over a
  200 response with per-line `available/issue` flags?
- **Efficient listing prices:** eager-load the whole page's offers and run the
  discount engine **once** per request (`quoteListing`), plus one query for
  active quantity rules for hints. A test asserts the query count stays flat as
  the number of products grows.
- **Packages (needs approval):** add `react-router-dom`, `react-redux`,
  `@reduxjs/toolkit` for SPA routing and cart/quote state. No other new package;
  `localStorage` persistence is hand-rolled. Approve?
- **Routing change (needs confirmation):** `/` becomes the storefront SPA and the
  existing React auth SPA moves to `/account`; Laravel UI `/login`/`/register`
  and `/admin/*` stay as-is. Alternative: keep the auth SPA at `/` and put the
  storefront only at `/shop`. Which do you want?
- **Hints without touching the engine contract:** `DiscountHintResolver` reads
  active quantity-rule fields for hints; all pricing stays in `DiscountEngine`.
  Alternative: add `hint()` to `DiscountRuleInterface`. Preference?
- **Product URLs:** use numeric IDs (`/products/{product}`, `?category={id}`) for
  consistency with the admin area, even though products have slugs. OK?
