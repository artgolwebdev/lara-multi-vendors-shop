# Known Issues and Unknowns

This file separates verified implementation limits from questions. It does not claim that unrequested product features are bugs.

## Verified Limits

- React/API authentication, admin authorization, vendor/category/product CRUD, product images, vendor offers, admin-managed discount rules, public storefront, customer accounts, and order checkout/management are implemented.
- The discount engine calculates quantity, category, and loyalty discounts in integer cents and returns a snapshot-ready line breakdown. `CartQuoter` reuses that engine for the storefront quote and checkout validates against it. Orders and sub-orders are persisted server-side with discount and address snapshots.
- Product quantity is shared across offers; per-vendor stock is not represented and is tracked at product level.
- Customer checkout and guest token order placement are implemented with cash-on-delivery (COD) payment. Online external payment gateways (e.g. Stripe, PayPal) remain out of scope / unimplemented.
- The latest recorded full PHPUnit run is 150 tests and 694 assertions (plan 010); the current audit did not rerun it. The frontend production build has passed with existing Sass deprecation and Tailwind/Lightning CSS warnings.
- Checkout vendor selection defaults to `customer_choice`; `lowest_price` is configurable. For assignments requiring automatic cheapest-offer selection, use or default to the lowest-price policy.
- Notifications are queued and simulated by logging. Local auto-processing can drain jobs after the response; non-local environments need an operational queue worker.
- Discount stacking applies every matching rule cumulatively per line (capped at the line subtotal).
- A Vercel app alias currently serves the PHP/Laravel function, but a production rollout has not been verified end to end; no CI/CD deployment automation is documented.
- Initial Vercel runtime scaffolding now exists, but deployment and Aiven MySQL connectivity are unverified. Product image uploads still use local storage and are not durable on Vercel; persistent object storage must be selected before production image uploads are enabled. See [Vercel deployment preparation](../development/deployment-vercel.md) and [Plan 012](../plans/012-vercel-aiven-production-deployment-plan.md).
- The provided Vercel Preview deployment URL is behind Vercel Authentication: unauthenticated `/up` redirects to SSO and `/api/categories` returns Vercel 401. The user-provided app alias reaches PHP 8.5.2/Laravel; `/up` returns the built-in health view for browser requests and JSON when requested. `/api/categories` returns 500 with a database `QueryException`; a response trace was present. Ensure Production `APP_DEBUG=false` immediately and use authorized Vercel logs to diagnose the DB/TLS error. See [Plan 014](../plans/014-vercel-deployment-investigation-plan.md).
- Email verification files and a user timestamp column exist, but no verification routes appeared in the inspected route list.
- Vercel Production migration hook is implemented but has not yet run against Aiven. The current workspace targets local MySQL, and the Vercel CLI is not installed. See [Vercel deployment preparation](../development/deployment-vercel.md).
- The latest supplied Vercel build reached the successful Vite build step but failed because Vercel expected a root `dist/` directory. `vercel.json` now overrides framework detection to `Other` and declares Laravel's `public/` output directory. A new Preview deployment is still required to verify the output correction, PHP routing, and asset delivery; see [Plan 015](../plans/015-vercel-dist-output-build-failure-plan.md).

## Open Questions

- Which database should local, test, and deployed environments use? The Boost-connected environment reports MySQL, while repository defaults select SQLite.
- Does the Vercel Production build expose `VERCEL_ENV=production` and have access to Aiven with the configured CA so the migration hook can run?
- How should production assets, sessions, queues, cache, and email be managed?
- Should email verification be enabled, or are the present files/column unused starter scaffolding?

The user has confirmed the phased marketplace scope. Remaining questions concern environment/deployment details and future per-vendor inventory behavior, not whether to build the requested marketplace. See [requirements](../product/requirements.md) and [backlog](../plans/backlog.md).
