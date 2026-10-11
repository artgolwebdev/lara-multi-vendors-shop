# Known Issues and Unknowns

This file separates verified implementation limits from questions. It does not claim that unrequested product features are bugs.

## Verified Limits

- React/API authentication, admin authorization, vendor/category/product CRUD, product images, vendor offers, admin-managed discount rules, public storefront, customer accounts, and order checkout/management are implemented.
- The discount engine calculates quantity, category, and loyalty discounts in integer cents and returns a snapshot-ready line breakdown. `CartQuoter` reuses that engine for the storefront quote and checkout validates against it. Orders and sub-orders are persisted server-side with discount and address snapshots.
- Product quantity is shared across offers; per-vendor stock is not represented and is tracked at product level.
- Customer checkout and guest token order placement are implemented with cash-on-delivery (COD) payment. Online external payment gateways (e.g. Stripe, PayPal) remain out of scope / unimplemented.
- The storefront SPA and admin SPA are verified by host-route feature tests, complete PHPUnit test suite (142 tests, 645 assertions), and `npm run build`.
- Discount stacking applies every matching rule cumulatively per line (capped at the line subtotal).
- No production deployment target or CI/CD deployment automation was identified.
- Email verification files and a user timestamp column exist, but no verification routes appeared in the inspected route list.

## Open Questions

- Which database should local, test, and deployed environments use? The Boost-connected environment reports MySQL, while repository defaults select SQLite.
- What is the deployment target and how should production assets, migrations, sessions, queues, cache, and email be managed?
- Should email verification be enabled, or are the present files/column unused starter scaffolding?

The user has confirmed the phased marketplace scope. Remaining questions concern environment/deployment details and future per-vendor inventory behavior, not whether to build the requested marketplace. See [requirements](../product/requirements.md) and [backlog](../plans/backlog.md).