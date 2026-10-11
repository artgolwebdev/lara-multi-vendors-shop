# 009: Login and Register System Design Alignment

- ID: `009-login-register-system-design`
- Status: completed
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: 2026-10-11

## Problem and Intended Outcome

Currently, the multi-vendor marketplace has a distinct, premium design identity ("Goodgoods") across the storefront (`/`, `/shop`, `/categories`, `/cart`, `/checkout`, `/order-confirmation/*`), customer account (`/account`), and admin portal (`/admin`). It uses a curated palette (forest green `#315b4b`, warm amber `#d08b47`, neutral surface `#fffefa`), modern typography (`DM Sans` and `Manrope`), and shared UI primitives (`Field`, `Input`, `Button`, `Alert`, etc.).

However, authentication entry points are fractured:
1. Navigating to `/login` or `/register` directly, or being redirected when unauthenticated (e.g. from `/admin` or expired sessions), serves default Laravel UI Bootstrap templates (`resources/views/auth/login.blade.php`, `register.blade.php` extending `layouts/app.blade.php`). These views display default Bootstrap blue styling (`#0d6efd`), `Nunito` font, and the generic "Laravel" brand.
2. The React authentication application (`AuthApp.jsx`) mounted at `/account` contains Goodgoods branding, but it does not use the shared UI primitives from Plan 008 (`resources/js/components/ui/`), dumps validation errors into a single string at the bottom, lacks a "Remember me" option, lacks password visibility toggling, lacks a "Forgot password" link, and does not automatically align its mode with the `/login` and `/register` URLs.
3. Password reset pages (`/password/reset`, `/password/reset/{token}`) use the plain Laravel Bootstrap views, creating an inconsistent user experience if a user needs to recover their account.

**Intended Outcome:**
Unify `/login`, `/register`, and `/account` under the Goodgoods multi-vendor store design system. Users visiting `/login` or `/register` will receive the rich Goodgoods experience with mode auto-detection, field-level validation, "Remember me", password visibility toggles, seamless redirects (e.g. back to checkout or admin), and cohesive styling across both React SPA and Blade fallback/password-reset screens.

---

## Scope

### In Scope
1. **Route & Controller Harmonization:**
   - Configure `LoginController@showLoginForm` and `RegisterController@showRegistrationForm` to return `view('auth-app')`.
   - Preserve existing named routes (`login`, `register`, `password.*`) and web POST routes for backward compatibility.
   - Update `AuthApp.jsx` to detect route pathname (`/login` -> login mode, `/register` -> register mode, `/account` -> tabbed/dashboard mode) while preserving existing `?mode=` and `?redirect=` query parameters.
2. **React AuthApp Improvements (`AuthApp.jsx`):**
   - Adopt shared primitives from `resources/js/components/ui/` (`Field`, `Input`, `Textarea`, `Button`, `Alert`, `Card`).
   - Implement field-level validation error displays (`errors.email`, `errors.password`, `errors.name`, etc.) alongside top-level status alerts.
   - Add "Remember me" checkbox support and send `remember: true/false` to `/api/login`.
   - Add show/hide password toggle button for password fields.
   - Add "Forgot your password?" link pointing to `/password/reset`.
   - Add quick "Back to store" navigation in header/story section.
   - Enhance the left "Marketplace introduction" story panel with multi-vendor marketplace highlights (independent makers, first-order discounts, transparent vendor pricing).
3. **Backend API & Request Polish:**
   - Update `LoginRequest` to accept optional boolean `'remember'` and update `AuthController@login` to pass the remember flag into `Auth::guard('web')->attempt(..., $request->boolean('remember'))`.
4. **Blade Layout & Password Reset Modernization:**
   - Restyle `resources/views/layouts/app.blade.php` with the Goodgoods brand identity (`tokens.css`, `Manrope`/`DM Sans` fonts, wordmark `goodgoods.`, forest green primary styling).
   - Modernize `resources/views/auth/passwords/email.blade.php` and `reset.blade.php` to match the Goodgoods aesthetic with matching cards, inputs, and buttons.
5. **Styling & Accessibility:**
   - Refactor `resources/css/auth.css` to use design tokens (`tokens.css`).
   - Ensure WCAG AA contrast (minimum 4.5:1), visible focus rings (`--focus-ring`), and min 40px touch targets (`--tap`).
6. **Automated Testing & Documentation:**
   - Add feature tests for `GET /login`, `GET /register`, and `GET /account` page mounts.
   - Add feature test verifying `remember` parameter handling in `POST /api/login`.
   - Verify unauthenticated redirects preserve intended redirect targets.
   - Run Pint and asset build; update backlog and status documentation.

### Non-Goals
- Adding social OAuth (e.g. Google/GitHub login) or two-factor authentication.
- Implementing multi-role vendor self-registration (vendors remain admin-managed per Plan 003).
- Altering existing session lifetime configuration or Sanctum stateful middleware.

---

## References

- Requirements: [`docs/product/requirements.md`](file:///c:/xampp/htdocs/multi-vendor-store/docs/product/requirements.md)
- Architecture: [`docs/architecture/overview.md`](file:///c:/xampp/htdocs/multi-vendor-store/docs/architecture/overview.md) and [`docs/architecture/frontend.md`](file:///c:/xampp/htdocs/multi-vendor-store/docs/architecture/frontend.md)
- Design Tokens: [`resources/css/tokens.css`](file:///c:/xampp/htdocs/multi-vendor-store/resources/css/tokens.css)
- Shared UI Primitives: [`resources/js/components/ui/`](file:///c:/xampp/htdocs/multi-vendor-store/resources/js/components/ui/) and [`resources/css/ui.css`](file:///c:/xampp/htdocs/multi-vendor-store/resources/css/ui.css)
- Existing Auth Plan: [`docs/plans/002-react-api-auth.md`](file:///c:/xampp/htdocs/multi-vendor-store/docs/plans/002-react-api-auth.md)

---

## Existing Implementation Findings

### Verified Facts
1. `routes/web.php` defines `Route::get('/account', ...)` returning `view('auth-app')`, followed by `Auth::routes()`.
2. `Auth::routes()` registers `GET /login` -> `Auth\LoginController@showLoginForm` returning `view('auth.login')` and `GET /register` -> `Auth\RegisterController@showRegistrationForm` returning `view('auth.register')`.
3. Both `auth.login` and `auth.register` extend `layouts.app`, which loads Bootstrap Sass and `Nunito` font from Bunny CDN with a generic "Laravel" brand navbar.
4. `CustomerAccessControlTest` and `AdminAccessTest` verify that guests trying to access `/admin` are redirected to `/login`.
5. `resources/js/components/AuthApp.jsx` handles `/account` with local tabs, but:
   - Does not detect when the browser URL is `/login` or `/register`.
   - Uses plain HTML `<input>` tags and custom `.field-label` classes instead of the shared `Field` and `Input` primitives.
   - Has only a single generic error string (`setError(errors ? Object.values(errors).flat()[0] : ...)`).
   - Lacks "Remember me", password show/hide, and "Forgot password" links.
6. `app/Http/Controllers/Api/AuthController.php` handles `/api/login` via `LoginRequest`, which currently only validates `email` and `password`.

---

## Proposed Design

### Affected Components

1. **`app/Http/Controllers/Auth/LoginController.php` & `RegisterController.php`:**
   - Override `showLoginForm()` and `showRegistrationForm()` to return `view('auth-app')`.
2. **`app/Http/Requests/Auth/LoginRequest.php`:**
   - Add optional `'remember' => ['nullable', 'boolean']` validation rule.
3. **`app/Http/Controllers/Api/AuthController.php`:**
   - Update `login()` to pass `$request->boolean('remember')` into `Auth::guard('web')->attempt(...)`.
4. **`resources/js/components/AuthApp.jsx`:**
   - Enhance mode resolution: check `window.location.pathname` (`/login` -> `'login'`, `/register` -> `'register'`) as well as `?mode=`.
   - Import and use shared UI primitives: `Field`, `Input`, `Textarea`, `Button`, `Alert`.
   - Add field errors state (`fieldErrors` object) populated from `response.data.errors` to show specific inline errors under each input.
   - Add "Remember me" checkbox (`form.remember`).
   - Add show/hide password toggle.
   - Add "Forgot your password?" link pointing to `route('password.request')` (`/password/reset`).
   - Add "Back to shop" link with arrow (`← Back to shop`).
   - Enrich left editorial story panel with multi-vendor value props.
5. **`resources/views/layouts/app.blade.php`:**
   - Replace Bunny Nunito font with Google Fonts `DM Sans` / `Manrope`.
   - Import `tokens.css` and `ui.css`.
   - Update navbar with `goodgoods.` wordmark and forest green styling.
6. **`resources/views/auth/passwords/email.blade.php` & `reset.blade.php`:**
   - Restyle form cards, labels, inputs, and submit buttons using Goodgoods tokens and layout styling.
7. **`resources/css/auth.css`:**
   - Modernize styles using CSS variables from `tokens.css` (`--c-primary`, `--c-accent`, `--c-surface`, `--c-bg`, `--font-display`, `--font-body`).

---

## Implementation Tasks

- [x] **Task 1: Web Route & Controller Integration**
  - Override `showLoginForm()` in `LoginController` to return `view('auth-app')`.
  - Override `showRegistrationForm()` in `RegisterController` to return `view('auth-app')`.
  - Ensure `/login` and `/register` URLs return HTTP 200 and mount `#auth-app`.
- [x] **Task 2: API Login Remember-Me Support**
  - Add `'remember' => ['nullable', 'boolean']` to `LoginRequest`.
  - Pass `$request->boolean('remember')` to `Auth::attempt()` in `AuthController@login`.
- [x] **Task 3: Modernize React AuthApp (`AuthApp.jsx`)**
  - Implement path-aware initial mode (`/login`, `/register`, or `/account`).
  - Refactor form fields onto shared primitives (`Field`, `Input`, `Textarea`, `Button`, `Alert`).
  - Add field-level validation error mapping and inline error messages.
  - Add "Remember me" checkbox and password visibility toggle.
  - Add "Forgot password?" link and "← Back to shop" header link.
  - Update editorial copy to showcase independent makers and multi-vendor perks.
- [x] **Task 4: Update Blade Auth Layout & Password Reset Views**
  - Update `resources/views/layouts/app.blade.php` with Goodgoods brand header, `tokens.css`, and `DM Sans`/`Manrope` fonts.
  - Style `email.blade.php` and `reset.blade.php` with Goodgoods card, input, and button design.
- [x] **Task 5: Refine `auth.css` with Design Tokens**
  - Replace raw hex values with CSS variables from `tokens.css`.
  - Verify WCAG AA color contrast and responsive layout (360px to 1440px).
- [x] **Task 6: Feature Tests and Verification**
  - Add test asserting `/login` and `/register` return 200 and render `auth-app` view with `#auth-app` selector.
  - Add test verifying `POST /api/login` with `'remember' => true` generates a remember cookie.
  - Verify guest admin redirection to `/login` remains green.
  - Run `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, and `npm run build`.

---

## Acceptance Criteria

- [x] Visiting `/login` renders the Goodgoods sign-in experience; `/register` selects account creation.
- [x] `/account` retains its customer dashboard and tabbed guest authentication behavior.
- [x] Form validation errors render next to their matching fields, with a separate general alert.
- [x] Remember-me login succeeds and sets the configured web guard recaller cookie.
- [x] Password recovery and reset pages use the Goodgoods layout and design tokens.
- [x] Intended login redirects and explicit same-origin `redirect` targets are preserved.
- [x] All automated tests pass (146 tests, 662 assertions).
- [x] Pint passes and the Vite production build completes.

---

## Testing Strategy and Results

| Check | Expected Result | Actual Result |
| --- | --- | --- |
| `GET /login` | HTTP 200, view `auth-app`, contains `#auth-app` | Passed in feature tests |
| `GET /register` | HTTP 200, view `auth-app`, contains `#auth-app` | Passed in feature tests |
| `GET /password/reset` | HTTP 200, Goodgoods recovery form | Passed in feature tests |
| `POST /api/login` with `remember: true` | HTTP 200, authenticated session and recaller cookie | Passed; remember token persisted |
| `GET /admin` unauthenticated | Redirect to `/login`, intended URL retained | Passed in feature tests |
| `php artisan test --compact` | All tests pass | Passed: 146 tests, 662 assertions |
| `vendor/bin/pint --dirty --format agent` | Zero Pint violations | Passed |
| `npm run build` | Production assets compile | Passed; existing Sass/Tailwind deprecation and minifier warnings remain |

---

## Risks, Dependencies, and Open Questions

1. **Risk:** Overriding `showLoginForm` in `LoginController` could affect custom session handling if not careful.
   - *Mitigation:* `LoginController` uses standard `AuthenticatesUsers` trait; overriding only the view returned by `showLoginForm()` and `showRegistrationForm()` is standard Laravel practice and preserves all middleware, guards, and session logic.
2. **Backward Compatibility:** Direct web POSTs to `/login` and `/register` remain functional via standard controllers.

## Completion Summary

- Reused the shared Goodgoods UI primitives for the React auth experience, with route-aware modes, field validation, remember-me, password visibility, recovery links, and safe same-origin redirects.
- Restyled the Laravel UI password recovery/reset templates and shared Blade layout.
- Added feature coverage for `/login`, `/register`, `/account`, intended redirects, password recovery, and the remember cookie.
- Follow-up: changed storefront links to the separately mounted auth app to perform full-page navigation so React Router does not consume `/account`, `/login`, or `/register`, while retaining its import for in-store cart navigation.
- Updated authentication and current-state documentation. No dependency changes were needed.
