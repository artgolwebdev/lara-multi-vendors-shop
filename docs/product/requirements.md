# Product Requirements

## Confirmed Task Scope

The user confirmed a phased multi-vendor e-commerce task. These are requested capabilities, not claims that they are implemented:

- Phase 1: users register/login/logout; users may become vendors; vendors upload products; the public welcome page lists products to guests and signed-in users.
- Catalog and offers are separate: products are shared catalog records; vendor offers hold vendor-specific price and stock.
- Public product results include the lowest available price and vendor count.
- The eventual order workflow selects the lowest offer per product, groups lines by vendor into sub-orders, queues one vendor notification per sub-order, and resolves discount rules dynamically.
- A React frontend served through Vite in this repository calls the Laravel API and presents loading/error states.
- Feature and unit tests cover the implemented workflows.

## Implementation Status

Only Step 1 is complete so far: React/API registration, login, and logout using same-origin Sanctum session authentication. Vendor onboarding, catalog, offers, public product listing, vendor dashboard, order processing, queued notifications, and discounts remain unimplemented. The implementation sequence is tracked in the user's task request; work stops after each step until the user says `next`.

See [implemented features](features.md), [user flows](user-flows.md), [known unknowns](../status/known-issues.md), and the [project foundation plan](../plans/001-project-foundation.md).