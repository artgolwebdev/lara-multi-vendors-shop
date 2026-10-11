# Known Issues and Unknowns

This file separates verified implementation limits from questions. It does not claim that unrequested product features are bugs.

## Verified Limits

- React/API authentication, admin authorization, vendor/category/product CRUD, product images, vendor offers, admin-managed discount rules, and the public storefront (listing, category, product, and guest cart with an online price quote) are implemented.
- The discount engine calculates quantity and category discounts in integer cents and returns a snapshot-ready line breakdown. `CartQuoter` reuses that engine for the storefront quote, but no cart, order, or sub-order is persisted server-side yet; the guest cart lives only in browser `localStorage` and the admin test panel does not write catalog or rule data.
- Product quantity is shared across offers; per-vendor stock is not represented and must be decided before order/inventory behavior is built.
- Orders/sub-orders, checkout, payment, notification jobs, and vendor login remain unimplemented.
- The storefront SPA is verified by its host-route feature test and `npm run build`, but no automated JS test runner exists; the interactive cart/discount walkthrough is only covered manually.
- Discount stacking applies every matching rule cumulatively per line (capped at the line subtotal) rather than selecting one best rule; this supersedes the plan's originally proposed best-per-line strategy.
- No deployment target or deployment automation was identified.
- Email verification files and a user timestamp column exist, but no verification routes appeared in the inspected route list.

## Open Questions

- Which database should local, test, and deployed environments use? The Boost-connected environment reports MySQL, while repository defaults select SQLite.
- What is the deployment target and how should production assets, migrations, sessions, queues, cache, and email be managed?
- Should email verification be enabled, or are the present files/column unused starter scaffolding?

The user has confirmed the phased marketplace scope. Remaining questions concern environment/deployment details and future per-vendor inventory behavior, not whether to build the requested marketplace. See [requirements](../product/requirements.md) and [backlog](../plans/backlog.md).