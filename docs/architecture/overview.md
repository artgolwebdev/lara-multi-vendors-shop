# Architecture Overview

## Current Shape (Verified)

This is a server-rendered Laravel application. The request entry point is Laravel routing in `routes/web.php`; route handlers return Blade views or delegate the authenticated home route to a controller. Laravel UI provides the web authentication route/controller scaffolding. Vite builds the CSS, Sass, and JavaScript entry points. There is no verified SPA framework, Inertia, Livewire, domain service layer, or application API in the current route list.

```mermaid
flowchart LR
    Browser --> Laravel[Laravel 13 web application]
    Laravel --> Routes[Web routes]
    Routes --> Auth[Laravel UI auth controllers]
    Routes --> Home[HomeController]
    Auth --> Views[Blade auth views]
    Home --> Views
    Routes --> Welcome[Welcome Blade view]
    Views --> Vite[Vite-built CSS and JavaScript]
    Welcome --> Vite
    Laravel --> DB[(Configured database)]
    Vite --> React[React example component]
```

## Evidence and Boundaries

- Verified package/runtime versions and the live schema snapshot are recorded in [current state](../status/current-state.md).
- The active route surface and API distinction are in [API and routes](../api/README.md).
- Detailed layers are described in [backend](backend.md), [frontend](frontend.md), [database](database.md), and [security](security.md).
- Future domain architecture is not proposed here because product requirements are not established.