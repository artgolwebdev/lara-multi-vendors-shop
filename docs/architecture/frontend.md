# Frontend Architecture

## Current Stack (Verified)

- Blade renders minimal SPA mount hosts; each page route returns a host view and React renders the app. The storefront SPA is mounted at `/`, `/shop`, `/categories`, `/categories/{category}`, `/products/{product}`, and `/cart` (`store-app`); the auth SPA is mounted at `/account` (`auth-app`).
- `resources/js/app.js` mounts each React experience when its element id (`#store-app`, `#auth-app`, `#admin-app`) is present. React calls same-origin `/api` endpoints through Axios.
- Bootstrap 5 is imported through Sass. Tailwind CSS 4 is imported from `resources/css/app.css` and processed through the Tailwind Vite plugin.
- React 18 is installed with the Vite React plugin. Axios sends Sanctum session cookies and XSRF headers.
- The public storefront is a React SPA using React Router (`resources/js/components/store/StoreApp.jsx`) with Redux Toolkit for cart state (`resources/js/store/{store.js,cartSlice.js}`). Cart lines (`{product_id, vendor_id, quantity}`) persist in `localStorage` under `goodgoods.cart.v1`; every change dispatches an abortable, debounced `fetchQuote` thunk that posts to `POST /api/cart/quote`. No price is ever computed in the browser.
- Store pages (`components/store/pages/*`) fetch the public catalog with `window.axios` and local loading/empty/error state. `ProductCard`, `VendorSelect`, `CartLine`, and `QuantityControl` render backend-provided prices, discounts, rules, and quantity hints.
- `/admin/*` routes mount a React admin workspace from a minimal Blade host. React selects dashboard, vendor, category, product, and discount-rule screens from the browser path and uses same-origin Axios requests to protected `/api/admin/*` endpoints.
- The discount-rule screen shows a computed status, conditional quantity/category fields, a readable summary, and a test panel that submits a product/vendor/quantity and renders the engine's line, rule, and total breakdown.
- Admin forms provide client-side workflow states while Laravel Form Requests remain the validation authority. API validation errors and session-stored success feedback are rendered in React.
- `vite.config.js` builds the CSS, Sass, and JS entry points. There is no Inertia or Livewire integration in the inspected application.

The concrete current versions are listed in [current state](../status/current-state.md). See [setup](../development/setup.md) for the build command and [components](../system-design/components.md) for request ownership. Checkout, orders, and payment UI remain unimplemented.