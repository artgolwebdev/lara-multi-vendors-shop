# 011: Mobile UX/UI Refinement and Persistent Store Header

- ID: `011-mobile-ux-persistent-header`
- Status: in_progress
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: not completed

## Problem and Intended Outcome

The storefront header currently scrolls away with the page. At widths up to 900px it wraps into multiple rows, while the search is further constrained below 620px. The existing storefront, authentication, and admin experiences have responsive rules, but they use different breakpoints and layouts and have not been reviewed together as a complete mobile workflow.

Improve the mobile experience across the customer storefront, account/auth screens, and admin workspace; smooth the most visible layout and interaction issues; and keep the storefront header available at the top while scrolling. Preserve the current Goodgoods visual identity and existing application behavior.

Plan 008 established shared design tokens and completed a first responsive polish pass. This plan is a focused follow-up for persistent store navigation and a viewport-based usability pass, not a replacement design system.

## Scope

### In Scope

- Keep the storefront masthead visible at the top while scrolling on all storefront routes, including shop, product, cart, checkout, and order confirmation.
- Rework the narrow-screen header arrangement so the wordmark, search, cart count, account actions, and primary navigation remain usable without crowding or horizontal page overflow. Add a compact accessible navigation toggle if the responsive design requires one.
- Review mobile layouts and interaction affordances for storefront listing/detail/cart/checkout/confirmation pages, account/auth screens, and the admin workspace.
- Fix demonstrated mobile overflow, clipped controls, unreadable dense content, insufficient touch targets, and layouts that require avoidable horizontal scrolling.
- Check sticky-header interaction with keyboard focus, page anchors, and browser viewport changes so content remains visible and usable.
- Keep the existing brand, CSS custom properties, React components, and hand-written CSS approach. Prefer existing shared UI primitives where appropriate.
- Record manual viewport checks and update current architecture/status documentation when implementation is complete.

### Non-Goals

- No catalog, pricing, discount, cart, checkout, order, authentication, or authorization behavior changes.
- No redesign/rebrand, new product features, or new frontend dependencies.
- No change to the admin desktop information architecture. Admin mobile navigation may be refined for usability, but the storefront header persistence requirement does not automatically make the admin topbar fixed.
- No backend/API/database changes.

## References

- Requirements: [feature inventory](../product/features.md), [user flows](../product/user-flows.md)
- Architecture: [frontend architecture](../architecture/frontend.md), [system components](../system-design/components.md)
- Prior UX implementation: [Plan 008](008-ux-ui-and-checkout-fixes-plan.md)
- Authentication surfaces: [Plan 009](009-login-register-system-design-plan.md)
- Current snapshot: [current state](../status/current-state.md)

## Existing Implementation Findings

### Verified Facts

1. `StoreLayout.jsx` renders the same `.store-header` on the storefront routes; it contains the wordmark, primary nav, search, cart indicator, and account actions (`resources/js/components/store/StoreLayout.jsx`).
2. `.store-header` has no positioning rule today (`resources/css/store.css:8–16`), so it scrolls with the document.
3. The store header switches to a wrapping multi-row flex layout at `max-width: 900px`; search width is constrained again at `max-width: 620px` (`resources/css/store.css:847–897`).
4. The admin sidebar is sticky on desktop and becomes a static, horizontally scrolling navigation row at `max-width: 640px`; the admin topbar remains in normal flow (`resources/css/admin.css`).
5. Authentication uses a two-column layout that stacks at `max-width: 760px` (`resources/css/auth.css:15–16`, `105–117`).
6. The project already has design tokens and shared UI primitives from Plan 008. Store, admin, and auth CSS continue to provide their respective page-specific responsive rules.

### Inferences

- The storefront header's wrapping layout and narrow search are likely contributors to a crowded mobile first screen; browser testing is needed to confirm exact breakpoints and device-specific behavior.
- Responsive improvements may be CSS-only on content pages, but a compact primary-nav control would require a small accessible state interaction in `StoreLayout.jsx`.

### Unanswered Questions

- Should the persistent header requirement apply only to the storefront masthead (recommended) or also to the admin topbar on narrow screens?
- Should mobile navigation open as an inline expanded row or a compact disclosure/menu? Decide during implementation after inspecting real-device screenshots; preserve keyboard and screen-reader access either way.

## Proposed Design

### Affected Components

- `resources/js/components/store/StoreLayout.jsx`
- `resources/css/store.css`
- Store pages under `resources/js/components/store/pages/` and their page-specific styles in `store.css`
- `resources/js/components/AuthApp.jsx` and `resources/css/auth.css`
- `resources/js/components/admin/AdminLayout.jsx` and `resources/css/admin.css`
- `resources/css/tokens.css` and `resources/css/ui.css` only if an existing shared primitive needs a small reusable responsive adjustment
- `docs/architecture/frontend.md`, `docs/product/user-flows.md`, `docs/status/current-state.md`, and `docs/status/changelog.md` after implementation

### Storefront Header

- Use a persistent top positioning strategy for `.store-header`; prefer `position: sticky; top: 0` so the header remains in normal layout flow and does not create a separate content-offset calculation.
- Set a deliberate stacking order and opaque surface so page content does not bleed through while scrolling.
- Rebalance the mobile header hierarchy and spacing. Keep search, cart state, and account access discoverable; avoid reducing controls below usable touch sizes.
- If a navigation toggle is introduced, use a real button with an accessible name, `aria-expanded`, keyboard operation, visible focus, and a clear expanded/collapsed state. Avoid a hover-only menu.
- Ensure the persistent header does not obscure focused controls or meaningful content when navigating or scrolling.

### Responsive Refinement

- Audit customer-facing pages at narrow phone, large phone, tablet, and desktop widths. Prioritize layout, readable type, clear spacing, full-width primary actions where useful, and content ordering on small screens.
- Review form fields and validation messages, product/vendor selectors, cart totals, order summaries, admin tables/filters, and horizontal navigation for overflow and practical touch interaction.
- Keep admin mobile changes scoped to its own sidebar/topbar/content rules; do not couple admin navigation to the storefront menu.
- Reuse current design tokens and primitives. Add no package and avoid broad CSS refactors unrelated to a confirmed usability issue.

### Database / API / Security

Not applicable. This work is presentational and interaction-focused; no server contracts or permissions should change.

## Implementation Tasks

- [x] Capture the current layout and behavior at the available browser viewport; record verified issues before changing styles. (The CUA browser viewport was approximately 506px wide; exact requested widths are not exposed by its browser API.)
- [x] Implement the persistent storefront header and test scroll, stacking, and content/focus visibility at the available browser width.
- [x] Refine the mobile header composition and navigation interaction, including keyboard and accessible state behavior if a toggle is used.
- [x] Review storefront product, category, cart, checkout, and confirmation responsive rules; fix only verified issues.
- [x] Review account/auth at the available phone-width browser viewport and admin responsive rules; no additional code changes were supported by the inspection.
- [x] Perform available manual responsive and keyboard checks; record actual viewport coverage and remaining known limitations.
- [x] Run the frontend production build and relevant PHP feature tests; PHP files were unchanged, so Pint was not needed.
- [x] Update frontend architecture/current-state notes and record actual results in this plan and the changelog.

## Acceptance Criteria

- [ ] The storefront header stays visible at the top during vertical page scrolling on all storefront routes and does not cover page content or focus targets.
- [ ] At 360px and 390px widths, the storefront header's navigation, search, cart indicator, and account entry are usable with no page-level horizontal overflow.
- [x] The collapsible navigation works with keyboard input, exposes its open state, and has visible focus styling (screen-reader behavior inferred from its native button semantics and ARIA state; no assistive-technology session was available).
- [ ] Storefront listing, product detail, cart, checkout, and confirmation screens have no clipped primary controls or overlapping content at the checked phone/tablet widths.
- [ ] Auth/account and admin screens remain usable at narrow widths, with their main actions reachable and no unintended page-level horizontal overflow.
- [ ] Desktop behavior and current application flows remain intact; no server-side behavior or API payload changes are introduced.
- [x] Manual responsive checklist and build/test results are recorded accurately in this plan.

## Testing Strategy and Results

Implementation and verification are in progress.

| Check | Expected result | Actual result |
|---|---|---|
| Manual baseline review | Identify visible mobile issues | At approximately 506px, header used three rows, compressed search, and crowded account/cart actions. Product and cart layouts already stack; admin tables use a contained horizontal scroller. |
| Store header scroll and focus behavior | Header stays at top without hiding focused content | Passed at approximately 506px; scrolled shop and header remained at top. Focus ring visible on menu toggle. |
| Keyboard navigation and mobile menu state | All header actions reachable and menu state announced | Passed: menu exposed expanded/collapsed state, showed nav/search/account links, and closed with Escape. |
| `npm run build` | Production frontend build succeeds | Passed; existing Sass deprecation and Tailwind/Lightning CSS unknown-at-rule warnings remain. |
| `php artisan test --compact tests/Feature/StorefrontPagesTest.php` | Existing route/auth behavior remains green | Passed: 5 tests, 41 assertions. PHPUnit emitted a permission warning writing `.phpunit.result.cache`; tests still passed. |

## Risks, Dependencies, and Open Questions

- A persistent header consumes vertical space, especially on short mobile viewports; keep the compact state efficient and avoid multi-row expansion unless the user opens navigation.
- A sticky header can obscure in-page scroll targets or keyboard-focused elements; explicitly check focus/scroll positioning.
- The current breakpoints differ between storefront (900/620px), admin (900/640px), and auth (760px). Do not unify them mechanically; choose responsive rules based on component needs.
- Browser/device behavior, including mobile browser chrome and zoom, must be checked during implementation; CSS viewport-only inspection is not enough for final sign-off.
- Recommended default: pin only the storefront header; keep admin navigation responsive but otherwise unchanged unless inspection shows a mobile usability issue.

## Implementation Notes and Discoveries

### 2026-10-11

- Confirmed the storefront header is the main visible phone-width issue; the approximately 506px baseline shows a crowded, three-row header and truncated search affordance.
- Added a sticky, opaque storefront header and a compact mobile top row with cart count and a real menu button exposing `aria-expanded` and `aria-controls`. The menu includes primary navigation, full-width search, and account actions; it closes on route/search changes and Escape.
- Existing listing/detail/cart/checkout/auth/admin responsive rules were reviewed. Content pages mostly already stack, auth already stacks, and admin tables are horizontally contained; no further layout change is currently justified by the observed evidence.
- Production build passed after rerunning outside the sandbox because the first Vite config resolution failed with Windows `spawn EPERM`. Focused storefront feature tests passed (5 tests, 41 assertions); PHPUnit could not update its result-cache file due to permissions.
- The browser control surface exposed only an approximately 506px viewport; the requested exact widths (360, 390, 768, 1024, 1440px) could not be set. The authenticated admin UI was not opened; its responsive CSS and protected route setup were reviewed instead.

## Final Summary and Remaining Work

The header implementation, focused browser checks, build, feature tests, and documentation updates are complete. Plan 011 remains in progress until exact target viewport checks and authenticated admin UI review can be completed.
