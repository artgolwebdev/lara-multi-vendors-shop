# Product Requirements

## Confirmed Task Scope

The current phase adds an admin-managed vendor and shared product catalog:

- Admin privileges are assigned outside requests; vendors are records, not user accounts.
- Admins manage vendors, products, product images, and per-vendor offers through React/Vite screens backed by protected Laravel JSON APIs.
- Products carry category and shared quantity. Offers carry vendor-specific price and active state; lowest price ignores inactive offers and inactive vendors.
- The later order workflow selects the lowest eligible offer per product, groups lines by vendor into sub-orders, queues one vendor notification per sub-order, and resolves discount rules dynamically.
- Orders, sub-orders, cart, jobs, discount rules, public shop pages, and vendor login are not part of this phase.

## Implementation Status

Step 1 is React/API registration, login, and logout using same-origin Sanctum session authentication. Plan 003 implements the admin-managed catalog foundation. Public product listing, cart, order processing, queued notifications, discounts, and vendor login remain unimplemented.

See [implemented features](features.md), [user flows](user-flows.md), [known unknowns](../status/known-issues.md), and the [project foundation plan](../plans/001-project-foundation.md).