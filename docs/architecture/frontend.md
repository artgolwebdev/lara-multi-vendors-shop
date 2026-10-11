# Frontend Architecture

## Current Stack (Verified)

- Blade renders the home and Laravel UI authentication pages. The root route returns `auth-app`, a minimal mount view for React.
- `resources/js/app.js` mounts the React authentication experience at `#auth-app`; React calls the same-origin `/api` endpoints through Axios.
- Bootstrap 5 is imported through Sass. Tailwind CSS 4 is imported from `resources/css/app.css` and processed through the Tailwind Vite plugin.
- React 18 is installed with the Vite React plugin. The root page mounts a React registration/login UI with loading, error, and authenticated states. Axios sends Sanctum session cookies and XSRF headers.
- `/admin/*` routes mount a React admin workspace from a minimal Blade host. React selects dashboard, vendor, and product screens from the browser path and uses same-origin Axios requests to protected `/api/admin/*` endpoints; no client-side router package is installed.
- Admin forms provide client-side workflow states while Laravel Form Requests remain the validation authority. API validation errors and session-stored success feedback are rendered in React.
- `vite.config.js` builds the CSS, Sass, and JS entry points. There is no Inertia or Livewire integration in the inspected application.

The concrete current versions are listed in [current state](../status/current-state.md). See [setup](../development/setup.md) for the build command and [components](../system-design/components.md) for request ownership. Public storefront and order UI remain unimplemented.