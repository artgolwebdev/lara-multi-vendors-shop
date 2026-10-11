# Architecture Overview

## Current Shape (Verified)

This Laravel application serves React storefront, authentication, and admin catalog pages through Blade mount views. React uses same-origin JSON APIs with Sanctum stateful sessions. Admin JSON routes add a dedicated admin middleware check. The public storefront reads the catalog and prices a guest cart through stateless public endpoints. Vite builds the CSS, Sass, and JavaScript entry points. There is no Inertia or Livewire integration.

```mermaid
flowchart LR
    Browser --> Laravel[Laravel 13 application]
    Laravel --> WebRoutes[Web routes]
    WebRoutes --> StorePage[React storefront mount pages]
    WebRoutes --> ReactPage[React auth mount page]
    WebRoutes --> AdminPage[Protected React admin mount]
    StorePage --> Vite[Vite assets]
    ReactPage --> Vite
    AdminPage --> Vite
    StorePage --> PublicApi[Public catalog and cart-quote API]
    PublicApi --> CartQuoter[CartQuoter and discount engine]
    ReactPage --> ApiRoutes[Stateful API routes]
    ApiRoutes --> ApiAuth[Sanctum AuthController]
    ApiRoutes --> AdminApi[Admin catalog and discount APIs]
    AdminApi --> Catalog[Vendor, category, product, image, offer, discount-rule models]
    AdminApi --> Discounts[Stateless discount engine]
    CartQuoter --> Discounts
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