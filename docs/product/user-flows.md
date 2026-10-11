# User Flows

## Storefront Shopping Flow

```mermaid
flowchart TD
    Guest[Guest] --> Home[GET / storefront SPA]
    Home --> Shop[GET /api/products search/sort/page]
    Home --> Categories[GET /api/categories]
    Categories --> Category[GET /api/categories/{id} and its products]
    Shop --> Detail[GET /api/products/{product}]
    Detail --> Add[Add {product_id, vendor_id, quantity} to cart]
    Add --> Persist[Persist lines to localStorage]
    Persist --> Quote[POST /api/cart/quote debounced]
    Quote --> Quoter[CartQuoter resolves offers and runs the engine]
    Quoter --> Totals[Per-line discounts and per-vendor totals]
```

Guests can browse and build a cart without registering. The browser stores only product/vendor IDs and quantities and never computes prices; the storefront posts the cart to the stateless quote endpoint, which resolves the selected or lowest active offer per line, applies discount rules, and returns line and per-vendor totals. The cart survives a page refresh via `localStorage`.

## Checkout and Order Flow

```mermaid
flowchart TD
    Cart[Cart with server quote] --> Review[Checkout review and contact/delivery]
    Review --> Submit[POST /api/orders]
    Submit --> Validate[Validate and re-quote in transaction]
    Validate --> Guard{Expected total matches?}
    Guard -->|No| Reconfirm[409 with latest quote; customer reviews again]
    Guard -->|Yes| Persist[Create order, vendor sub-orders and line snapshots]
    Persist --> Queue[Dispatch NotifyVendorJob after commit per sub-order]
    Queue --> Confirm[201 order resource and confirmation page]
    Queue --> Log[Simulated vendor.notification log]
```

Checkout supports guests and signed-in customers. The server rechecks offers, discounts and shared product stock; guest order reads require the returned guest token. The vendor policy defaults to customer choice and can be configured to select the lowest active offer. Notifications are simulated in the Laravel log; processing requires the local auto-processor or a queue worker.

## Current React/API Authentication Flow

```mermaid
flowchart TD
    Visitor[Visitor] --> Account[GET /account React auth page]
    Account --> Register[POST /api/register]
    Account --> Login[POST /api/login]
    Register -->|session cookie| SignedIn[Authenticated React state]
    Login -->|session cookie| SignedIn
    SignedIn --> CurrentUser[GET /api/user]
    SignedIn --> Logout[POST /api/logout]
    Logout --> Visitor
```

The React auth page at `/account` first requests `/api/user`; the expected unauthenticated response displays sign-in. Registration and login obtain the Sanctum CSRF cookie before submitting. Logout invalidates the session. Laravel UI web auth and password reset routes remain available separately.

## Existing Web Flow

```mermaid
flowchart TD
    Visitor[Visitor] --> Welcome[Laravel UI auth pages]
    Welcome --> Login[Login form]
    Welcome --> Register[Registration form]
    Login -->|valid session| Home[GET /home authenticated dashboard]
    Register -->|account created| Home
    Home -->|POST /logout| Welcome
    Login --> Reset[Password reset request and token flow]
```

The route list confirms the Laravel UI login/register, logout, password confirmation/reset, and authenticated home routes. Laravel UI supplies this web flow. Email verification controller and view files exist, but the route list does not expose verification routes.

## Admin Catalog Flow

```mermaid
flowchart TD
    Admin[Admin signs in] --> Mount[GET /admin protected mount]
    Mount --> React[React admin workspace]
    React --> Dashboard[GET /api/admin/dashboard]
    React --> Vendors[Vendor CRUD API]
    React --> Categories[Category CRUD API]
    React --> Products[Product CRUD API]
    Products --> Upload[Validated public-disk image uploads]
    Products --> Offers[Transactional vendor offer sync]
    React --> Discounts[Discount-rule CRUD API]
    Discounts --> TestPanel[POST /api/admin/discount-rules/test]
    TestPanel --> Engine[Stateless discount engine breakdown]
```

Admin route mounts require an authenticated admin. Guests are redirected to login, and authenticated non-admins receive 403. Registration cannot set admin status. Vendors are managed as records and never sign in. Category deletion is rejected while products or discount rules reference the category. The discount test panel resolves the selected product/vendor's active offer and runs the engine without writing any data.

Public product browsing, cart, checkout, order confirmation, customer order history and admin order management are implemented. Vendor accounts and external payment providers are not implemented; see [product requirements](requirements.md).
