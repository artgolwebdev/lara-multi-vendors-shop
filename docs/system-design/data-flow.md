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

The welcome route directly returns a view. Auth routes are registered by Laravel UI. `/home` is protected by `HomeController`'s `auth` middleware and returns the home view. The exact database connection depends on environment settings: the current Boost connection reported MySQL; repository defaults are SQLite.

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