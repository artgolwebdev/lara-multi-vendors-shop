# Frontend Architecture

## Current Stack (Verified)

- Blade renders the welcome, home, and Laravel UI authentication pages.
- The authenticated layout loads the Sass entry point and `resources/js/app.js`; the welcome view loads the Tailwind CSS entry point and the same JavaScript entry point when Vite assets are available.
- Bootstrap 5 is imported through Sass. Tailwind CSS 4 is imported from `resources/css/app.css` and processed through the Tailwind Vite plugin.
- React 18 is installed with the Vite React plugin. `resources/js/app.js` imports one example component; the component mounts only if an element with `id="example"` is present. The inspected views do not establish a React SPA.
- `vite.config.js` builds the CSS, Sass, and JS entry points. There is no Inertia or Livewire integration in the inspected application.

The concrete current versions are listed in [current state](../status/current-state.md). See [setup](../development/setup.md) for the build command and [components](../system-design/components.md) for request ownership.