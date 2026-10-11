# Architecture Overview

## Current Shape (Verified)

This Laravel application serves React authentication and admin catalog pages through Blade mount views. React uses same-origin JSON APIs with Sanctum stateful sessions. Admin JSON routes add a dedicated admin middleware check. Vite builds the CSS, Sass, and JavaScript entry points. There is no Inertia or Livewire integration.

```mermaid
flowchart LR
    Browser --> Laravel[Laravel 13 application]
    Laravel --> WebRoutes[Web routes]
    WebRoutes --> ReactPage[React auth mount page]
    WebRoutes --> AdminPage[Protected React admin mount]
    ReactPage --> Vite[Vite assets]
    AdminPage --> Vite
    ReactPage --> ApiRoutes[Stateful API routes]
    ApiRoutes --> ApiAuth[Sanctum AuthController]
    ApiRoutes --> AdminApi[Admin catalog APIs]
    AdminApi --> Catalog[Vendor, product, image, offer models]
    Catalog --> DB
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