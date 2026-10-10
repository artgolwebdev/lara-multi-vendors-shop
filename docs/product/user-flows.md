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

## Product Flows Not Yet Defined

Vendor and buyer catalog/order journeys remain unimplemented; see [product requirements](requirements.md) for the confirmed task scope.