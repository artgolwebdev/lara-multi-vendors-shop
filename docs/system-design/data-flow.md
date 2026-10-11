# Request and Data Flow

## Web Request

```mermaid
sequenceDiagram
    participant Browser
    participant Laravel as Laravel web stack
    participant Route as routes/web.php
    participant Controller
    participant View as Blade view
    participant DB as Configured database
    Browser->>Laravel: HTTP request
    Laravel->>Route: Match web route
    Route->>Controller: Invoke auth or home handler
    Controller->>DB: Read/write user or session data when needed
    Controller-->>View: Return response/view
    View-->>Browser: HTML with Vite asset references
```

The root route returns the React mount view; React calls the same-origin API. Sanctum stateful middleware applies session and CSRF behavior to trusted first-party API requests. Laravel UI auth routes remain available, and `/home` is protected by `HomeController`'s `auth` middleware. The exact database connection depends on environment settings: the current runtime connection is MySQL; repository defaults are SQLite.

Admin mount routes use `auth` followed by the admin middleware and return a minimal React host view. Admin React screens call JSON endpoints protected by `auth:sanctum` and the same admin check. Controllers validate requests, write catalog records through Eloquent, and return paginated API resources.

## React Authentication Request

```mermaid
sequenceDiagram
    participant React as React client
    participant API as Laravel API routes
    participant Sanctum
    participant DB as Configured database
    React->>API: GET /api/user
    API->>Sanctum: Authenticate session
    Sanctum-->>React: 401 when unauthenticated
    React->>API: GET /sanctum/csrf-cookie
    React->>API: POST /api/register or /api/login with XSRF token
    API->>DB: Create or authenticate user
    API-->>React: Public user resource and session cookie
    React->>API: POST /api/logout
    API-->>React: Invalidate session
```

## Agent Context Flow

```mermaid
flowchart TD
    Task[Task request] --> Instructions[AGENTS.md workflow]
    Instructions --> Context[docs README and current state]
    Context --> Plan[Backlog item and task plan]
    Plan --> Code[Inspect and change application]
    Code --> Boost[Boost guidance, skills, MCP tools]
    Code --> Verify[Tests and validation]
    Verify --> Record[Update plan, status, and relevant docs]
```

This is a documentation workflow, not application runtime behavior. See [the canonical workflow](../../AGENTS.md) and [plans](../plans/README.md).