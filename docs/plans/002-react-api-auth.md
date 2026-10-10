# 002: React API Authentication

- ID: `002-react-api-auth`
- Status: completed
- Created: 2026-10-10
- Updated: 2026-10-10
- Completed: 2026-10-10

## Problem and Intended Outcome

The confirmed marketplace task starts with account registration, login, and logout through a React frontend calling a Laravel API. The repository had Laravel UI web authentication but no API routes or React application flow. Implement Step 1 only and retain the existing web auth routes.

## Scope

### In Scope

- Add same-origin Sanctum stateful API authentication.
- Add JSON registration, login, logout, and authenticated current-user endpoints.
- Add a React registration/login experience at `/` with loading, validation/error, and authenticated states.
- Cover observable authentication behavior with feature tests.

### Non-Goals

- Vendor profiles, catalog, offers, product listings, cart, orders, queues, or discounts.
- Replace or remove the existing Laravel UI web authentication routes.
- Add a separate frontend package or cross-origin SPA deployment configuration.

## References

- Requirements: [product requirements](../product/requirements.md)
- Architecture: [backend](../architecture/backend.md), [frontend](../architecture/frontend.md), [database](../architecture/database.md)
- API: [authentication](../api/authentication.md), [endpoints](../api/endpoints.md)

## Existing Implementation Findings

- Verified: Laravel 13.35.0, Laravel UI 4.6.3, React 18, Axios 1.20.0, and Vite 8.3.4 were installed; Sanctum was not a direct dependency.
- Verified: `routes/api.php` did not exist, while Laravel UI session-based web registration/login/logout routes were active.
- Verified: same-origin React is served by Laravel/Vite, so Sanctum stateful cookie authentication matches the app boundary.
- Verified: test environment uses in-memory SQLite; the configured runtime connection for this execution was MySQL.

## Proposed Design

### Affected Components

`AuthController`, registration/login Form Requests, `UserResource`, API routes, Sanctum middleware, `User`, Vite React entry/component, a root Blade mount view, auth styling, feature tests, and current architecture/API docs.

### Database

Use Sanctum's standard `personal_access_tokens` table for future token clients. First-party SPA auth uses the existing session tables. No marketplace tables are introduced.

### API / Routes

- `POST /api/register`, `POST /api/login`, and authenticated `POST /api/logout`.
- Authenticated `GET /api/user`.
- Apply stateful API middleware, CSRF protection, and basic request throttling on register/login.

### Frontend

Mount a React auth screen from the Laravel-served `/` page. Axios uses same-origin cookies and XSRF headers. Keep the existing Blade-based `/login` and `/register` routes intact.

### Security

Validate registration and login with Form Requests, serialize only public user fields, regenerate the session on successful authentication, invalidate it on logout, and retain CSRF and throttling protections.

## Implementation Tasks

- [x] Install Sanctum and enable stateful API middleware.
- [x] Implement JSON register/login/logout/current-user endpoints and validation/resource boundaries.
- [x] Add the React auth UI and configure Axios for CSRF-protected same-origin requests.
- [x] Add focused feature tests and update project documentation.
- [x] Apply the Sanctum migration and validate tests and production asset build.

## Acceptance Criteria

- [x] A visitor can register and is authenticated with a session.
- [x] A user can log in, retrieve the authenticated user, and log out.
- [x] Invalid credentials and invalid registration input receive JSON errors.
- [x] React auth UI builds and renders at `/`.
- [x] Existing tests continue to pass; no later marketplace feature is introduced.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| `php artisan test --compact tests/Feature/ApiAuthenticationTest.php` | Authentication paths pass | Passed; 3 tests, 18 assertions |
| `php artisan test --compact` | Full suite passes | Passed; 5 tests, 20 assertions |
| `vendor/bin/pint --dirty --format agent` | Modified PHP follows project style | Passed; Pint fixed import ordering in `routes/web.php` |
| `npm run build` | React/Vite production assets compile | Passed after fixing JSX in the `.js` entrypoint; existing Sass/Tailwind processing warnings remain |
| `php artisan migrate --no-interaction` | Sanctum token table is applied | Passed against configured MySQL; migration status reports all migrations ran |
| Browser check at `/` | React auth screen renders | Passed; unauthenticated `/api/user` returns the expected handled 401 |

## Risks, Dependencies, and Open Questions

- Added direct dependency: `laravel/sanctum` (`^4.0`, installed 4.3.3), required for stateful SPA auth and API tokens.
- React and Axios must remain same-origin with Laravel for the current cookie/CSRF setup; a separately hosted frontend needs explicit trusted stateful domains and CORS configuration.
- Bootstrap Sass emits existing deprecation warnings during Vite builds; Tailwind directives also trigger minifier warnings in the current toolchain.
- Vendor/catalog and all later task steps remain unimplemented pending the user's next instruction.

## Implementation Notes and Discoveries

- Laravel's `install:api` generated the API route file, Sanctum config, and personal access token migration.
- Clearing cached auth guards after logout ensures a long-lived application container cannot reuse the pre-logout user.
- React JSX belongs in `.jsx`; the existing `.js` Vite entry mounts the component with `createElement`.

## Final Summary and Remaining Work

Step 1 is complete. The app now supports React-based JSON registration/login/logout via Sanctum sessions while preserving Laravel UI web routes. No vendor, product, cart, order, job, or discount behavior was added. Proceed only when the user requests the next step.