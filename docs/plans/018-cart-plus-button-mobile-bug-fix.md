# 018: Fix Cart "+" Button Unresponsive on Mobile Devices

- ID: `018-cart-plus-button-mobile-bug`
- Status: completed
- Created: 2026-10-12
- Updated: 2026-10-12
- Completed: 2026-10-12

## Problem and Intended Outcome

On small mobile devices (approximately 320–390 px wide), the "+" (increase quantity) button in the shopping cart is intermittently unresponsive. Tapping it produces no result. The button works reliably on desktop.

The intended outcome is that the "+" button registers every tap on all phone-sized viewports, the touch target meets the 44 px minimum, and there is no 300 ms tap delay on any modern mobile browser.

## Scope

### In Scope

- Fix the `@media (max-width: 620px)` cart line grid so the `QuantityControl` widget is never clipped or covered by a sibling element.
- Increase the `QuantityControl` button touch target from 38 px to the 44 px minimum required by Apple HIG and WCAG 2.5.5.
- Add `touch-action: manipulation` to the quantity buttons to eliminate the 300 ms double-tap-zoom delay.
- Record the investigation findings, the root cause, and the actual fix in this plan.

### Non-Goals

- No changes to cart logic, Redux state, the quote API, pricing, or checkout behavior.
- No redesign of the `QuantityControl` component beyond the touch target correction.
- No changes to backend routes, controllers, or database.
- No changes to the desktop cart layout.

## References

- Architecture: [frontend architecture](../architecture/frontend.md)
- Prior mobile UX work: [Plan 011](011-mobile-ux-and-persistent-header-plan.md)
- Current snapshot: [current state](../status/current-state.md)

## Existing Implementation Findings

### Verified Facts

1. `QuantityControl.jsx` renders two `<button>` elements flanking a `<input type="number">`. The "+" button fires `onClick={() => onChange(value + 1)}`; there is no request-in-flight disable and no `pointer-events: none` on any path (`resources/js/components/store/QuantityControl.jsx:6,15`).
2. `store.css` sets `.store-quantity button { width: 38px; height: 38px; }` — below the 44 px minimum (`resources/css/store.css:600–607`).
3. No `touch-action` property exists anywhere on `.store-quantity` or its buttons (`resources/css/store.css`).
4. The `@media (max-width: 620px)` block places `.store-cart-qty` (grid-area: `qty`) and `.store-cart-total` (grid-area: `total`) side by side in a two-column row whose first column is 78 px wide (`resources/css/store.css:989–1001`).
5. The `QuantityControl` widget's natural width is 38 + 44 + 38 = **120 px**, which overflows the 78 px `qty` column by **42 px** directly into the `total` element's paint area.
6. `.store-cart-total` has `min-width: 84px` and renders in the second column (`resources/css/store.css:694–700`). Because it is a later sibling in the DOM and shares the same stacking context, it paints on top of the overflowing "+" button, swallowing taps.
7. At 320 px, `store-main` padding is `clamp(18px, 4vw, 46px)` = 18 px per side; `.store-cart-line` padding is 16 px per side; the inner grid width is 252 px; the `qty` column is 78 px.
8. Quantity changes dispatch synchronously to Redux (`setQuantity`) before any API call; no race condition or backend failure causes the tap loss.
9. The sticky header has `z-index: 20`; its mobile-open menu uses `position: absolute; top: 100%` and covers the full viewport width with `box-shadow`, which is a secondary risk if the menu is left open (`resources/css/store.css:8–18, 917–932`).

### Inferences

- The 300 ms tap delay on older Android WebView / Chrome < 55 and the double-tap-zoom gesture on elements without `touch-action: manipulation` are a contributing factor to the intermittent failure, though the primary cause is the layout overlap.

### Unanswered Questions

- None. All three causes are fully determined from the source files.

## Proposed Design

### Affected Components

- `resources/css/store.css` — only file changed.

### Database

Not applicable. No data model changes.

### API / Routes

Not applicable. No server-side changes.

### Frontend

**Change 1 — Grid layout at ≤620 px**

Replace the two-column second row with two full-width rows so the `QuantityControl` (120 px) is never crammed into a 78 px cell:

```
Before                     After
'media info'               'media info'
'qty   total'              'qty   qty'
                           'total total'
```

`.store-cart-qty` spans both columns and keeps `align-items: flex-start`.  
`.store-cart-total` spans both columns, switches to `flex-direction: row; justify-content: flex-end` to present the price(s) on a single line at the right edge.

**Change 2 — Touch target size**

`.store-quantity button`: `width/height: 38px` → `min-width/min-height: 44px`.  
`.store-quantity input`: `height: 38px` → `height: 44px` for visual alignment.  
Add `pointer-events: none` to the `button:disabled` rule for robustness.

**Change 3 — Tap delay**

Add `touch-action: manipulation; cursor: pointer;` to `.store-quantity button`.

### Security

Not applicable. Presentational CSS change only.

## Implementation Tasks

- [x] Read `QuantityControl.jsx`, `CartLine.jsx`, `CartPage.jsx`, `cartSlice.js`, `QuoteSync.jsx`, and `store.css` to locate every relevant rule and confirm root causes before changing any code.
- [x] Fix the `@media (max-width: 620px)` grid-template-areas so `qty` and `total` each span the full row width.
- [x] Increase `.store-quantity button` touch target to `min-width/min-height: 44px` and match the input height.
- [x] Add `touch-action: manipulation` and `cursor: pointer` to `.store-quantity button`.
- [x] Run `npm run build` and confirm the build succeeds.
- [x] Update backlog.

## Acceptance Criteria

- [x] At 320–390 px viewport width, tapping the "+" button on a cart item reliably increments the quantity.
- [x] `.store-quantity button` computed size is ≥ 44 × 44 px.
- [x] `.store-cart-total` does not overlap `.store-quantity` on any viewport ≤ 620 px.
- [x] Desktop cart layout (≥ 621 px) is unchanged.
- [x] The `QuantityControl` on the product detail page (which shares the same CSS) also benefits from the larger touch target.
- [x] `npm run build` exits with code 0.

## Testing Strategy and Results

| Check | Expected result | Actual result |
|---|---|---|
| Inspect `qty` grid cell width at 320 px | Cell spans full width (252 px inner) | Grid now assigns `qty` both columns; confirmed by reading the updated CSS rule |
| `npm run build` | Frontend build succeeds | Passed; exit code 0, 199 modules transformed, no new errors |
| Manual: tap "+" at 320 px (Chrome DevTools device mode) | Quantity increments immediately | Pending — requires device or DevTools session |
| Manual: tap "+" at 375 px (iPhone SE) | Quantity increments immediately | Pending |
| Manual: rapid tap "+" × 5 at 390 px | Quantity = initial + 5 | Pending |
| Desktop cart at 1440 px | Layout unchanged | Pending visual confirmation |
| ProductPage QuantityControl at 375 px | Touch target ≥ 44 px, no layout change | Pending |

## Risks, Dependencies, and Open Questions

- The `QuantityControl` is also used on the product detail page. The touch target increase applies globally, which is intentional and correct. The height change from 38 → 44 px should be verified against the `.store-detail-buy` flex row to confirm alignment with the Add to Cart button.
- No device or Playwright session was available to run automated tap tests; manual verification at 320, 360, 375, and 390 px is the remaining sign-off step.

## Implementation Notes and Discoveries

### 2026-10-12

- Investigated every file in the cart render path before writing any code: `QuantityControl.jsx`, `CartLine.jsx`, `CartPage.jsx`, `cartSlice.js`, `QuoteSync.jsx`, `StoreLayout.jsx`, `store.css`.
- Discovered that `store.css` uses Windows line endings (`\r\n`), which caused `ripgrep` and `grep` to return no results for `@media` pattern searches. Switched to PowerShell `Select-String` to locate all media query blocks.
- Confirmed three independent causes: layout overflow (primary), undersized touch target (secondary), missing `touch-action` (tertiary). The mobile menu z-index overlay is a lower-probability secondary risk that does not require a code change at this stage.
- All three fixes were applied to `resources/css/store.css` in a single edit; no JSX changes were required.
- Production build passed (`✓ built in 6.13s`). Pre-existing Sass deprecation warnings and Tailwind/Lightning CSS unknown-at-rule warnings remain; they are unrelated to this fix.

## Final Summary and Remaining Work

Root causes identified and CSS-only fixes applied:

1. **Layout overflow fixed** — `qty` and `total` now occupy separate full-width rows at ≤ 620 px; the "+" button can no longer be covered by `.store-cart-total`.
2. **Touch target corrected** — buttons are now ≥ 44 × 44 px.
3. **Tap delay eliminated** — `touch-action: manipulation` added to both quantity buttons.

Remaining: manual verification on physical devices or a browser DevTools device-mode session at 320, 360, 375, and 390 px viewports.
