# Architecture Overview

## Current Shape (Verified)

This Laravel application serves a React authentication page from `/` and retains Blade/Laravel UI web pages. React uses the same-origin JSON authentication API with Sanctum stateful sessions. Vite builds the CSS, Sass, and JavaScript entry points. Vendor/catalog domain features are not implemented yet; there is no Inertia or Livewire integration.

```mermaid
flowchart LR
    Browser --> Laravel[Laravel 13 application]
    Laravel --> WebRoutes[Web routes]
    WebRoutes --> ReactPage[React auth mount page]
    ReactPage --> Vite[Vite assets]
    ReactPage --> ApiRoutes[Stateful API routes]
    ApiRoutes --> ApiAuth[Sanctum AuthController]
    WebRoutes --> Auth[Laravel UI auth controllers]
    WebRoutes --> Home[HomeController]
    Auth --> Views[Blade auth views]
    Home --> Views
    Views --> Vite
    Laravel --> DB[(Configured database)]
```

## Evidence and Boundaries

- Verified package/runtime versions and the live schema snapshot are recorded in [current state](../status/current-state.md).
- The active route surface and API distinction are in [API and routes](../api/README.md).
- Detailed layers are described in [backend](backend.md), [frontend](frontend.md), [database](database.md), and [security](security.md).
- Confirmed marketplace requirements and implementation status are recorded in [product requirements](../product/requirements.md).