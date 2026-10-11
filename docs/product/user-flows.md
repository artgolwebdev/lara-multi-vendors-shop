# User Flows

## Current React/API Authentication Flow

```mermaid
flowchart TD
    Visitor[Visitor] --> Root[GET / React auth page]
    Root --> Register[POST /api/register]
    Root --> Login[POST /api/login]
    Register -->|session cookie| SignedIn[Authenticated React state]
    Login -->|session cookie| SignedIn
    SignedIn --> CurrentUser[GET /api/user]
    SignedIn --> Logout[POST /api/logout]
    Logout --> Visitor
```

The React page first requests `/api/user`; the expected unauthenticated response displays sign-in. Registration and login obtain the Sanctum CSRF cookie before submitting. Logout invalidates the session. Laravel UI web auth and password reset routes remain available separately.

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

Public product browsing, cart, buyer ordering, and vendor notifications remain unimplemented; see [product requirements](requirements.md).