# Product Requirements

## Confirmed Task Scope

The current phase adds an admin-managed vendor and shared product catalog:

- Admin privileges are assigned outside requests; vendors are records, not user accounts.
- Admins manage vendors, categories, products, product images, per-vendor offers, and discount rules through React/Vite screens backed by protected Laravel JSON APIs.
- Products reference a managed category and carry shared quantity. Offers carry vendor-specific price and active state; lowest price ignores inactive offers and inactive vendors.
- The discount engine evaluates active, in-window quantity and category rules against supplied cart lines, stacking all matching rules cumulatively per line and returning a snapshot-ready breakdown. The admin test panel invokes it without persisting catalog, offer, or rule data.
- The later order workflow selects the lowest eligible offer per product, groups lines by vendor into sub-orders, queues one vendor notification per sub-order, and persists discount snapshots.
- Orders, sub-orders, cart, jobs, public shop pages, and vendor login are not part of the admin phase.

## Implementation Status

Step 1 is React/API registration, login, and logout using same-origin Sanctum session authentication. Plan 003 implements the admin-managed catalog foundation. Plan 004 implements categories and the admin-managed discount rules with the calculation engine. Plan 005 implements the public storefront (listing, category, and product pages with a guest cart) and the stateless cart-quote endpoint. Order processing, queued notifications, checkout, and vendor login remain unimplemented.

See [implemented features](features.md), [user flows](user-flows.md), [known unknowns](../status/known-issues.md), and the [project foundation plan](../plans/001-project-foundation.md).