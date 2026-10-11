# Product Requirements

## Original Phase Scope and Current Implementation

The current phase adds an admin-managed vendor and shared product catalog:

- Admin privileges are assigned outside requests; vendors are records, not user accounts.
- Admins manage vendors, categories, products, product images, per-vendor offers, and discount rules through React/Vite screens backed by protected Laravel JSON APIs.
- Products reference a managed category and carry shared quantity. Offers carry vendor-specific price and active state; lowest price ignores inactive offers and inactive vendors.
- The discount engine evaluates active, in-window quantity and category rules against supplied cart lines, stacking all matching rules cumulatively per line and returning a snapshot-ready breakdown. The admin test panel invokes it without persisting catalog, offer, or rule data.
- The implemented order workflow quotes offers and discounts, groups selected lines by vendor into sub-orders, queues one simulated vendor notification per sub-order, and persists price/discount snapshots.
- Public shop pages, browser-side cart, guest/customer checkout, order confirmation, customer order history, and admin order management are implemented.
- Vendor login is not implemented; vendors remain admin-managed contact records. Checkout's vendor selection is configurable (`customer_choice` by default or `lowest_price`); see [known issues](../status/known-issues.md) for the assignment-specific implication.

## Implementation Status

Step 1 is React/API registration, login, and logout using same-origin Sanctum session authentication. Plans 003–006 implement the catalog, discount engine, public storefront, customer accounts, and loyalty. Plan 007 implements checkout, multi-vendor orders, queued log-only notifications, and admin order management; later plans add UI and auth refinements. Vendor login and online payment remain unimplemented.

See [implemented features](features.md), [user flows](user-flows.md), [known unknowns](../status/known-issues.md), and the [project foundation plan](../plans/001-project-foundation.md).
