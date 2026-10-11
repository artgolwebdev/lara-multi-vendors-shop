# Known Issues and Unknowns

This file separates verified implementation limits from questions. It does not claim that unrequested product features are bugs.

## Verified Limits

- React/API authentication, admin authorization, vendor CRUD, product CRUD, product images, and vendor offers are implemented.
- Product quantity is shared across offers; per-vendor stock is not represented and must be decided before order/inventory behavior is built.
- The public storefront, cart, orders/sub-orders, notification jobs, and discount engine remain unimplemented and have no tests yet.
- No deployment target or deployment automation was identified.
- Email verification files and a user timestamp column exist, but no verification routes appeared in the inspected route list.

## Open Questions

- Which database should local, test, and deployed environments use? The Boost-connected environment reports MySQL, while repository defaults select SQLite.
- What is the deployment target and how should production assets, migrations, sessions, queues, cache, and email be managed?
- Should email verification be enabled, or are the present files/column unused starter scaffolding?

The user has confirmed the phased marketplace scope. Remaining questions concern environment/deployment details and future per-vendor inventory behavior, not whether to build the requested marketplace. See [requirements](../product/requirements.md) and [backlog](../plans/backlog.md).