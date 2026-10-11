# 008: UX/UI Polish and Checkout / Confirmation Fixes

- ID: `008-ux-ui-and-checkout-fixes`
- Status: completed
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: 2026-10-11

> Implemented and verified. This is a fix-and-polish phase: no new business
> features, no pricing/discount/order/queue logic changes except the bug
> fixes listed below. Followed plan 005/006/007 conventions (React/Vite SPAs,
> hand-written CSS, integer-cent money, flat `/api` routes).

## 1. Audit Findings

### Bugs (confirmation root cause first)

1. **Order confirmation page crashes on render (blocker).**
   `resources/js/components/store/pages/OrderConfirmationPage.jsx:66` renders
   `{user ? … : …}`, but `user` is never declared or imported (no `selectUser`).
   React throws `ReferenceError: user is not defined` while rendering, so the page
   is blank/errored even though the data loads. The backend is fine: verified live
   that `GET /api/orders/{id}` returns 404 without a token and 200 with the guest
   token / session, with correct `order_number`, totals and `sub_orders`+`items`.
   Secondary confirmation gaps: no friendly 404 page, the cart is never cleared
   after success, and per-item applied-rule names are not shown.

2. **Account "My orders" calls a removed endpoint.**
   `AccountPanel.jsx:39` requests `/api/account/orders`, which no longer exists
   (removed when orders landed; only `/api/orders` exists). The request always
   falls into `.catch(() => setOrders([]))`, so "My orders" is always empty. There
   is also no link to the order detail/confirmation view.

3. **Checkout and confirmation pages are completely unstyled.**
   Nearly every class used by `CheckoutPage.jsx` and `OrderConfirmationPage.jsx`
   is missing from `resources/css/store.css`: `store-checkout`, `store-checkout-main`,
   `store-checkout-card`, `store-form-section`, `store-form-grid`, `store-form-field`,
   `store-checkout-summary`, `store-payment-card`, `store-alert`, `store-form-errors`,
   `store-confirmation*`, `store-meta-list`, `store-address`, `store-suborders`,
   `store-suborder-*`, `store-table*`, `store-item-*`. Only generic `store-card`,
   `store-button`, `store-totals`, `store-vendor-summary` exist. This is why
   checkout "looks broken" (spacing, buttons, colors).

4. **Checkout price-change (`409`) flow is dead.**
   (a) The client never sends `expected_total`, so the server's
   `PriceChangedException` guard can never fire from the UI.
   (b) Even when a 409 occurs, `CheckoutPage` only shows a message; it does not
   refresh the displayed quote or require an explicit re-confirm as plan 007 intended.

5. **Cart is never cleared after a successful order** (the inverse of "cleared too
   early"): lines persist in `localStorage` and re-quote if the user returns to
   `/cart` after ordering.

6. **Admin Orders pages are almost entirely unstyled and structurally off.**
   `resources/js/components/admin/OrderPages.jsx` uses classes that exist nowhere:
   `admin-section`, `admin-section-head`, `admin-subtitle`, `admin-card`,
   `admin-filters`, `admin-table-wrapper`, `admin-grid`, `admin-definition`,
   `admin-stack`, `admin-subtext`, `admin-discount`, `admin-link`,
   `admin-alert-danger`, and `status-pending|confirmed|shipped|delivered|cancelled`.
   Only `admin-table`, `admin-status`, `admin-pagination`, `admin-alert`/`-success`
   exist. The "Items" column reads `order.items_count`, which the resource never
   returns (always "-"); the Vendors column shows a count instead of chips/links.
   (`admin-toolbar`, used by the Customers search, is also missing — minor.)

7. **Admin vendor-detail and customer-detail never show their order lists**, even
   though `GET /api/admin/vendors/{vendor}/orders` and
   `GET /api/admin/customers/{customer}/orders` exist.

8. **Admin "Re-send notification" is always shown** and notification status is plain
   text; it should be a StatusBadge with time and the re-send action shown only for
   `pending`/`failed` sub-orders.

### Visual problems per page (from markup/CSS; no screenshot tool available here)

- **Checkout:** no step indicator; no two-column desktop layout; field errors
  dumped at the bottom of the summary rather than per field/line; no loading
  skeleton, out-of-stock-per-line, 409-with-updated-totals, empty-cart or generic
  error-with-retry states; no guest login/register prompt with return-to-checkout.
- **Confirmation:** crashes; and unstyled (all `store-confirmation-*` CSS absent).
- **Cart:** layout is acceptable; money/vendor summary alignment and quantity
  controls can be tightened; discount labels exist.
- **Product listing/cards:** already styled; polish only (mobile header hides nav
  and the search is cramped).
- **Header/footer/auth/account:** broadly consistent; account orders are broken.
- **Admin Orders index/detail:** unstyled with the structural issues above.

### Live verification performed

- `GET /api/products` → 200; guest `POST /api/orders` → 201 (`GG-2026-000006`);
  `GET /api/orders/6` no token → 404; with token → 200 with correct data;
  `NotifyVendorJob` logged. `/checkout`, `/order-confirmation/6`, `/cart`, `/shop`
  → 200; `/admin/orders` → 302 to login. Order feature tests: 17/17 pass.
  No uncaught application ERROR in `storage/logs/laravel.log` (only expected 409
  exception logs from tests).

## 2. Design Foundation (small, shared, reusable)

The SPAs use hand-written CSS, not Tailwind utilities. Tailwind 4 is installed but
only imported by `resources/css/app.css`, which no Blade view loads, so the
design system will use **CSS custom properties** (matching `admin.css`'s existing
`:root` tokens), not Tailwind classes.

### 2.1 Tokens — new `resources/css/tokens.css` (loaded first in `app.js`)

- **Color (WCAG AA checked; reuse the existing brand):** `--c-primary #315b4b`
  (+ `--c-primary-hover #244838`, `--c-primary-strong #244b3d`), `--c-accent #d08b47`,
  neutrals `--c-bg #f5f4f1`, `--c-surface #fffefa`, `--c-border #e4e2da`,
  `--c-ink #25251f`, `--c-muted #6f6e66`, and semantic `--c-success #287457`,
  `--c-warning #8a5a12`, `--c-danger #a2392f`, `--c-info #2f5d8a` each with a tint
  background. Muted text is darkened from the current `#93918a` to meet 4.5:1.
- **Spacing scale:** `--space-1..8` (4/8/12/16/20/24/32/48px).
- **Radius:** `--radius-sm 2px`, `--radius-md 4px`, `--radius-pill 999px`.
- **Shadow:** `--shadow-sm`, `--shadow-md` (reuse the current soft card shadow).
- **Type scale:** `--text-xs 11px` … `--text-3xl 44px`, `--font-body`
  (`DM Sans`) and `--font-display` (`Manrope`).
- **Focus ring:** `--focus-ring: 0 0 0 3px rgb(49 91 75 / 35%)`; min touch target
  `--tap: 40px`.
- No dark mode (none exists today).

### 2.2 Shared React primitives — new `resources/js/components/ui/`

Neutral `gg-*` classes in a new `resources/css/ui.css` so both storefront and admin
can reuse them. Thin wrappers; reuse existing helpers (`formatMoney`,
`AdminPageHeader`, `Pagination`, `StatusBadge` are extended, not duplicated).

- `Button` (variants: primary / secondary / ghost / danger; sizes sm/md; `loading`,
  `disabled`, `as` link), `Input`, `Select`, `Textarea`, `Field` (label, hint,
  error, `aria-invalid`/`aria-describedby`).
- `Card`, `PageHeader` (title, optional eyebrow/breadcrumbs, actions).
- `Badge`, `StatusBadge` (order/sub-order statuses and notification
  pending/sent/failed share one color map), `Alert` (info/success/warning/error).
- `EmptyState`, `Skeleton`, `Table` (responsive wrapper, right-aligned money cells),
  `Pagination`, `Money` (right-aligned, tabular numerals), `Steps` (Cart → Review →
  Confirmation).
- Accessibility in every primitive: labels, visible `:focus-visible` ring, keyboard
  operability, `aria-live` for async regions, min 40px targets on mobile.

### 2.3 Reused existing components

- `components/store/{EmptyState,ErrorState,Skeletons,formatMoney,VendorSelect,
  QuantityControl,CartLine,ProductCard,QuoteSync}` are kept and gradually pointed
  at the shared primitives.
- `components/admin/shared.jsx` (`ApiErrors`, `AdminPageHeader`, `Pagination`,
  `StatusBadge`) is refactored onto the shared primitives.

## 3. Implementation Steps

1. **Tokens + shared CSS.** Add `resources/css/tokens.css` and
   `resources/css/ui.css`; import both in `resources/js/app.js` before
   `store.css`/`admin.css`; express `store.css`/`admin.css`/`auth.css` colors,
   spacing, radii and shadows through the tokens (no visual rebrand).
2. **Shared primitives.** Add `resources/js/components/ui/*` and extend
   `admin/shared.jsx` to consume them.
3. **Confirmation fix (do first).**
   - `OrderConfirmationPage.jsx`: import `useSelector(selectUser)`; add a clear
     success header, order number, summary card, per-vendor sub-order cards, item
     rows with unit price, discount **and applied-rule name**, and subtotal; add
     "View my orders" (signed-in) / "Continue shopping" actions; friendly
     not-found/forbidden page with a sign-in link; clear the cart after success
     (dispatch `clearCart`) and a `Skeleton` while loading.
   - `AccountPanel.jsx`: call `/api/orders` (not `/api/account/orders`), render the
     real list, and add a "View" link to `/order-confirmation/{id}`.
4. **Checkout page.** `CheckoutPage.jsx` + a dedicated checkout section of
   `store.css`: `Steps` indicator; two-column desktop (form left, sticky summary
   right) / stacked mobile; summary grouped by vendor with item rows, unit price,
   discount rule label, vendor subtotal, grand total; primary button with loading
   and disabled states; per-field validation errors and per-line out-of-stock
   messages; **send `expected_total`** and on `409` replace the quote with
   `response.data.data` and require an explicit confirm; loading skeleton, empty
   cart state, generic error state with retry; guest login/register prompt
   (`/account?redirect=/checkout`, cart preserved in `localStorage`).
5. **Cart + listing polish.** `CartPage.jsx`, `CartLine.jsx`, `ProductCard.jsx`,
   `ShopPage.jsx`: alignment/spacing, money alignment, vendor summary, quantity
   controls, add-to-cart button states, responsive grid. No behavior change.
6. **Header/footer/auth/account polish.** `StoreLayout.jsx`, `AuthApp.jsx`,
   `AccountPanel.jsx`: consistent container width, spacing, headings, buttons,
   form styling; ensure no horizontal scroll from 360px up.
7. **Admin Orders index.** Rewrite `OrderPages.jsx` `OrderList` with the shared
   `PageHeader` + filter `Card` (vendor, customer, status, date range, order-number
   search) with an explicit **Apply**/**Reset**, active filter chips, and
   URL-persisted filters; responsive `Table` (money right-aligned, `StatusBadge`,
   vendor chips/links, readable dates, row hover/link to detail), `Pagination`,
   empty + loading states; drop the broken `items_count` column or provide it from
   the resource; body must not scroll sideways.
8. **Admin order detail + related lists.** Rework `OrderDetail` with a summary
   header (number, date, status, totals) and actions; two-column customer/delivery
   + totals; each vendor sub-order as its own card with items table (product, SKU,
   unit price, qty, discount + rule, line total), sub-order status control,
   notification `StatusBadge` with time, and "Re-send notification" only for
   `pending`/`failed`; links to customer/vendor pages. Add matching order lists to
   `VendorPages.jsx` (vendor detail) and `CustomerPages.jsx` (customer detail).
9. **Admin supporting.** Add orders count to the dashboard (optional, trivial);
   apply the shared components to other admin pages only where it is a trivial
   swap (e.g. fix the missing `admin-toolbar` class); list the rest as follow-up.
10. **Backend tweaks only where required.** Possibly expose `items_count` /
    `sub_orders` vendor list on `OrderResource` for the index (small additive
    resource change); no pricing/order/queue logic changes.
11. **Tests + docs.** Feature tests (section 5); run `php artisan test`,
    `vendor/bin/pint --dirty --format agent`, `npm run build`; update
    `docs/{architecture/frontend,status/current-state,status/known-issues,api/endpoints}`
    and the backlog; record actual results in this plan.

## 4. Page-by-Page Summary

| Page / file | Change |
| --- | --- |
| `store/pages/OrderConfirmationPage.jsx` | Fix `user` crash; full success/error/detail UI; clear cart; rule names |
| `account/AccountPanel.jsx` | `/api/orders`; real list + detail links |
| `store/pages/CheckoutPage.jsx` + `store.css` | Steps, 2-col layout, states, per-field errors, 409 confirm, guest prompt |
| `store/pages/CartPage.jsx`, `CartLine.jsx`, `ProductCard.jsx`, `ShopPage.jsx` | Alignment/spacing polish only |
| `StoreLayout.jsx`, `AuthApp.jsx` | Header/footer/form consistency, responsive |
| `admin/OrderPages.jsx` | Full rewrite on shared components (index + detail) |
| `admin/VendorPages.jsx`, `admin/CustomerPages.jsx` | Add matching order lists |
| `admin/shared.jsx`, `components/ui/*`, `tokens.css`, `ui.css` | Shared foundation |

## 5. Quality and Tests

- **Confirmation feature tests** (`tests/Feature/OrderReadAccessTest.php`, extend or
  add `OrderConfirmationTest`): registered owner `GET /api/orders/{id}` → 200 with
  correct `order_number`/totals/`sub_orders.items`; direct/refresh (plain second GET)
  → 200; another customer → 404 (repo convention `abort(404)`); guest without token
  → 404; guest with valid token → 200 (regression).
- **Deep-link regression**: extend `StorefrontPagesTest` to assert `/checkout` and
  `/order-confirmation/{id}` return 200 and mount `#store-app` (refresh works).
- **Checkout regression**: keep/extend `OrderCheckoutTest` (409 with
  `expected_total`, idempotency, COD) — no behavior change, but assert the
  `expected_total` path the UI now uses.
- **Frontend**: no JS test runner exists → manual QA checklist below. Make sure
  `npm run build` passes and there are no console errors on touched pages.
- **Accessibility**: labels on every field, AA contrast, visible focus, meaningful
  button text.

### Manual QA checklist (375 / 768 / 1280px)

- Guest: browse shop → add to cart → `/checkout` → place order → confirmation
  loads; refresh works; "Continue shopping" clears cart.
- Guest without a saved token opening a confirmation URL sees a friendly page.
- Customer: login → `/account` "My orders" lists the order → open detail;
  "My discounts" hides the first-order rule after one order.
- Wrong customer/order → friendly 404.
- Admin: Orders index filters (vendor/customer/status/date/search) with Apply/Reset
  and chips; detail status control, notification badge, re-send only when relevant;
  vendor/customer detail order lists.
- No horizontal scrolling and no overlapping elements at all three widths.

## 6. Decisions I Made / Questions for You

1. **Styling approach:** design tokens as CSS variables + neutral `gg-*` shared
   components in a new `ui.css`, because the SPAs are hand-written CSS and Tailwind
   is not loaded on any page. Existing `store-*`/`admin-*` classes stay for pages
   outside this phase's scope. OK, or do you want Tailwind utilities instead?
2. **Palette:** reuse the existing forest green + amber brand and neutrals, only
   darkening low-contrast muted text to reach WCAG AA. No new brand.
3. **Shared components:** keep and extend existing `store` helpers and
   `admin/shared.jsx` rather than creating parallel duplicates.
4. **Follow-up pages (not touched here):** Products, Discounts, Vendors forms and
   the login/register *Laravel UI* Blade pages (only the `/account` React SPA is
   polished) will adopt the shared components in a later pass.
5. **Guest confirmation vs "guest redirected to login":** the repo intentionally
   supports guest confirmation via `guest_token`. I will keep that and show a
   friendly sign-in page only when a guest has no valid token. Confirm this, or do
   you want guests blocked from the confirmation page entirely?
6. **`items_count` column:** Added small additive `items_count` on `OrderResource` and eager counted on `OrderController` query.
7. **No new packages:** Zero new packages installed; native CSS custom properties and lightweight React primitives only.

## 7. Actual Verification Outcomes

- **PHPUnit:** 142 tests and 645 assertions passed cleanly (`php artisan test --compact`), covering order reading, refresh/isolation access rules, guest access with token, storefront deep linking (`/checkout` and `/order-confirmation/{id}`), 409 price mismatch guard, and admin listing enhancements.
- **Pint:** Passed with zero violations (`vendor/bin/pint --dirty --format agent`).
- **Asset Build:** Vite build succeeded without errors (`npm run build`).
- **Order Confirmation:** Fixed render crash caused by missing `user` reference; added clear confirmation hero, order details, totals breakdown, customer and delivery cards, items table with applied-rule tags, and clearCart dispatch.
- **Customer Account Orders:** Fixed `/api/orders` call and added live order list with order number, placed date, vendor count, status pill, total, and "View" direct confirmation links.
- **Checkout:** Two-column desktop and responsive stacked mobile layout, Steps breadcrumbs, vendor-grouped item summary, per-field validation error displays, guest registration prompt, and handled 409 price change state with explicit re-confirmation.
- **Admin Orders:** Rewrote order index with search/vendor/customer/status/date filters, active filter chips, URL persistence, items count, and vendor links. Redesigned order detail with sub-order status updating, conditional notification dispatch, and items tables.
- **Admin Vendor & Customer Detail:** Added live orders and sales/spend totals to both detail views.
