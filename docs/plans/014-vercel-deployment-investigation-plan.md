# 014: Vercel Deployment Investigation and Rollout Plan

- ID: `014-vercel-deployment-investigation`
- Status: in_progress
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: not completed

## Problem and Intended Outcome

The repository contains a Vercel PHP runtime configuration for Laravel 13, React/Vite assets, and an Aiven MySQL CA certificate. A read-only request to the supplied Preview returned HTTP 200 with HTML for both `/up` and `/api/categories`; the latter should return JSON. That response does not prove that Laravel handled the request or that Aiven is reachable. The active Vercel framework/build/output settings and deployment logs are not available in the repository, so the cause must be established before changing routing or promoting the deployment.

This plan audits the actual Laravel/Vite architecture and proposes an ordered route/build investigation followed by a safe Vercel Preview validation. It keeps Vercel as the primary target, treats the existing Aiven Production database as sensitive, and does not apply code/config changes, run builds/tests, execute migrations, seed, or deploy during this planning task.

## Scope

### In Scope

- Review current Laravel, React/Vite, Vercel runtime, Aiven TLS, environment, filesystem, session/cache/queue, auth, and migration setup.
- Identify why Preview URLs responded with HTML and verify that function routing, JSON APIs, Laravel pages, and static Vite assets work as intended.
- Set a verified Vercel dashboard and `vercel.json` configuration in a later implementation step, avoiding duplicate dependency installs/builds.
- Define environment-specific secrets, safe database checks, migration/recovery process, staged Preview/Production smoke checks, and fallback hosting evaluation.

### Non-Goals

- No implementation, dependency changes, tests, builds, deployments, environment edits, or Vercel/Aiven dashboard changes as part of this plan-only task.
- No schema or data mutation. Do not run migrations or seeders in Production without a separately reviewed release step.
- No image-storage provider selection. The user deferred product image uploads; local disk must not be treated as durable on Vercel.
- No credential or certificate contents in documentation, terminal output, frontend variables, or build logs.

## References

- Requirements: user-provided task brief, “Create an Implementation Plan for Deploying Laravel 13 + React + Vite to Vercel with Aiven MySQL”.
- Repository docs: [current state](../status/current-state.md), [known issues](../status/known-issues.md), [Vercel deployment status](../development/deployment-vercel.md), [Plan 012](012-vercel-aiven-production-deployment-plan.md), and [Plan 013](013-aiven-mysql-ssl-investigation-plan.md).
- Primary platform docs reviewed 2026-10-11: [vercel-php runtime README](https://github.com/vercel-community/php), [vercel-php build implementation](https://github.com/vercel-community/php/blob/master/src/index.ts), [vercel-php Composer/build utilities](https://github.com/vercel-community/php/blob/master/src/utils.ts), [Vercel build configuration](https://vercel.com/docs/builds/configure-a-build), [Vercel project configuration](https://vercel.com/docs/project-configuration/vercel-json), [Vercel runtime behavior](https://vercel.com/docs/functions/runtimes), [Vercel function limits](https://vercel.com/docs/functions/limitations), [Aiven PHP/PDO connection guide](https://aiven.io/docs/products/mysql/howto/connect-with-php), and [Aiven certificate rotation guidance](https://aiven.io/docs/products/mysql/howto/maintenance-updates).
- Date caveat: the GitHub source links for runtime build flow target the runtime repository's current default branch. During implementation, compare the source/behavior of the pinned `vercel-php@0.9.0` package before relying on details that can change.

## Existing Implementation Findings

### Verified Repository Facts

- Laravel Framework is installed at 13.35.0; `composer.json` constrains PHP to `^8.3` and Laravel to `^13.17`. Local XAMPP PHP is 8.4.23. `composer check-platform-reqs` passed locally. `pdo_mysql` and OpenSSL are loaded locally; `composer.json` does not explicitly require the MySQL extension, so the deployed runtime must be checked directly.
- `vercel.json` selects community runtime `vercel-php@0.9.0`, max duration 30 seconds, and a catch-all route to `/api/index.php`. Runtime docs reviewed for the pinned version list PHP 8.5.x, Node autodetection, and extensions including OpenSSL, PDO, `pdo_mysql`, `mysqlnd`, `intl`, and `gd`. This is platform documentation, not a successful build of this repository.
- The runtime README uses `api/index.php` as the PHP entry point, supports catch-all routing, recommends the PHP built-in server for local development, and says `vercel dev` is not supported. The runtime source on its current default branch installs Composer dependencies and executes `composer run vercel` when `composer.json` is present. Verify the exact behavior of the pinned runtime and actual Vercel build logs.
- `composer.json` contains a `vercel` script that runs Laravel package discovery, `npm ci`, and `npm run build`. The runtime builder may execute this script as part of function build; a separate dashboard install/build command could repeat some work. Confirm the project build logs and use one deliberate pipeline.
- Local versions are Node 24.15.0/npm 12.0.2; `package.json` declares Node `22.x`, which matches the runtime documentation's Node 22.x support. `npm list --depth=0` reports Vite 8.3.4, Laravel Vite plugin 3.2.0, React/React DOM 18.3.1, React Router DOM 7.18.4, Redux Toolkit 2.13.0, React Redux 9.3.0, Tailwind/Vite plugin 4.3.3, Sass 1.105.1, Axios 1.20.0, and Vite React plugin 6.1.2. There is an unmet optional `@laravel/multiplex` dependency; its relevance to production is not established.
- React is integrated through `laravel-vite-plugin` and `@vitejs/plugin-react`; Vite inputs are `resources/css/app.css`, `resources/sass/app.scss`, and `resources/js/app.js`. React is mounted into Laravel-rendered pages/routes; this is not a separate Vite `dist` SPA deployment. `public/build/manifest.json` currently exists in the local working copy, while `/public/build` is ignored by Git and must be generated/included by deployment build behavior.
- `public/index.php` is Laravel's standard HTTP bootstrap. `api/index.php` initializes writable runtime paths under `/tmp`, selects the Aiven CA path before Laravel boots, allows a small static-extension list to return to the PHP built-in server, then requires `public/index.php`. The semantics and deployed behavior of that static pass-through need to be validated in the pinned runtime.
- `bootstrap/app.php` enables stateful Sanctum APIs, trusts all forwarded proxies (`*`), and selects JSON exception rendering for `api/*` or JSON requests. Verify the effective Vercel ingress/header behavior and do not assume trusted-host filtering is configured.
- `config/database.php` defaults to SQLite, supports MySQL through `DB_URL` or `DB_*`, and provides `MYSQL_ATTR_SSL_CA` plus `Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT=true` when `pdo_mysql` is available. `api/index.php` resolves `AIVEN_CA_PEM_B64`, then `MYSQL_ATTR_SSL_CA`, then `storage/certs/ca.pem`; it fails closed on Vercel if no CA path resolves. Local PHP OpenSSL parses the checked-in CA, marks it `CA:TRUE`, and reports it currently within its validity period. This does not prove that it is Aiven's active CA, validates the remote chain/host, or proves remote connectivity. See Plan 013.
- Earlier checks of the provided deployment URL followed or received HTML for `/up` and `/api/categories`; that did not establish that Laravel handled the calls. Header-only requests on 2026-10-11 then showed Vercel Authentication intercepting that deployment URL (`/up` redirects to Vercel SSO; `/api/categories` returns Vercel 401 JSON).
- Header-only GETs to the user-provided `lara-multi-vendors-shop.vercel.app` alias on 2026-10-11 returned `/up` HTTP 200 `text/html` for a browser request and HTTP 200 `application/json` when sent with `Accept: application/json`; Laravel 13's built-in health route is implemented to return its health view or JSON according to request negotiation. Both responses carried `X-Powered-By: PHP/8.5.2`. `/api/categories` returned HTTP 500 `application/json`. A second in-memory inspection of only the API response schema identified a Laravel `QueryException` and the presence of a trace. The raw message and trace were not displayed or saved. This strongly suggests deployed debug details are exposed; confirm/fix `APP_DEBUG=false` immediately in Vercel Production. The database/TLS cause is not established.
- `config/session.php` defaults to database sessions; cookies use environment-driven secure and same-site settings. `config/cache.php` defaults to database cache. Both require their tables in the selected Aiven database.
- `config/queue.php` defaults to a database queue. `NotifyVendorJob` is dispatched for vendor order notifications, and Vercel does not run a persistent `queue:work` process. A serverless-safe choice must be explicit: synchronous handling for the demo if behavior and response time permit, or an external queue plus worker.
- `config/filesystems.php` defaults to local storage and defines a local `public` disk. Product image routes store/delete through `Storage::disk('public')`. No durable Vercel upload storage is configured; object-storage selection is deferred. The existing upload request accepts up to 10 images of up to 2 MB each, which can exceed Vercel's 4.5 MB function payload limit.
- `config/logging.php` supports an error-log/stderr channel. `.env.example` defaults to stack logging, database session/cache/queue, local filesystem, and log mail. Mail recovery therefore needs a real provider for production.
- Authentication uses Laravel session auth and Sanctum stateful SPA requests, with same-origin React/Axios behavior. Configure production and preview hostnames/cookies carefully; no separate cross-origin CORS design is needed unless frontend/backend origins differ.
- `composer.json`'s `setup` script includes `php artisan migrate --force`; it must never be used as a Vercel build command. `DatabaseSeeder` invokes demo/admin/catalog/customer seeders; review idempotence and credentials before any deliberate demo seed operation. Never seed the production database automatically.
- The `/up` route is configured through Laravel's health route option, but an HTML response is not proof this route reached Laravel. Existing tests cover local route/API behavior; no test or health endpoint proves remote function behavior. No test, production build, or deployment command was run during this planning task.
- Current Aiven Production environment values, Vercel Preview scopes/build logs/Deployment Protection settings, Aiven service region/access policy, production database contents, migrations, and Vercel plan are not visible in the repository.

### Compatibility and Deployment Assessment

- Composer allows PHP 8.5 and the community runtime documents PHP 8.5 for `vercel-php@0.9.0`; compatibility with the complete lockfile remains unverified. The runtime's documented builder may use `--ignore-platform-reqs`, so do not treat its Composer install as proof of extension compatibility; explicitly validate platform requirements in the runtime/build environment.
- A catch-all PHP function is supported by the community runtime, but the Preview HTML response means current routing/output configuration is not accepted as verified. Check Deployment Protection and the actual function invocation log before editing route rules.
- Vercel Output Directory contains files served statically. `public` is Laravel's public web root but setting it as static output can affect which requests reach the function. Do not blindly set Output Directory to `public`; first verify asset/static routing and fallback behavior. Candidate designs are: route all app paths to the PHP function and validate the runtime's built-in-server static pass-through, or serve known static assets from `public` and explicitly rewrite dynamic paths to PHP.
- Vercel functions have a read-only application filesystem except writable `/tmp` scratch space (documented up to 500 MB). The runtime has a 250 MB uncompressed bundle limit including layers and a 4.5 MB request/response payload limit. Check current official limits when implementing, especially for image uploads.
- Vercel region defaults and supported regions depend on plan/configuration. Align the PHP function region with Aiven only after confirming Aiven's service region and the Vercel project's available regions.
- Aiven requires TLS when using CA verification modes. The PDO CA option and server-certificate flag need a deployed positive and negative connection test; hostname identity behavior must be confirmed for the exact PHP/mysqlnd build.
- The app can fit Vercel if the runtime and routing are verified and durable uploads/queue behavior are handled externally. If function packaging, PHP runtime stability, or conventional Laravel worker/filesystem requirements prove unsuitable, compare Laravel Cloud or an always-on PHP host (Railway, Fly.io, or VPS) before abandoning Vercel.

## Proposed Architecture

- Laravel 13 remains the backend and serves web pages plus `/api/*` from the existing routes. React remains built by Vite using the Laravel manifest integration.
- The custom `vercel-php@0.9.0` runtime runs `api/index.php`, which prepares `/tmp` paths, sets the CA path, and hands the request to Laravel's `public/index.php`.
- Aiven MySQL is used only through Laravel's MySQL connection with TLS verification and the current Aiven CA. Secrets live in environment scopes, not files committed to Git.
- Static assets are served either by Vercel's static layer with explicit routing precedence or by the PHP runtime's static pass-through. Choose only after the diagnostic deployment proves which path currently returns HTML and why.
- Store sessions/cache in the Aiven database initially. Use synchronous job handling for the demo only if validated; otherwise add an external worker/queue in a separate approved task.
- Keep product uploads disabled for production reliance until durable object storage is selected and implemented. Vercel local disk and `/tmp` are not persistent storage.

## Vercel Project Settings Proposal

The values below are the proposed baseline; verify the existing dashboard before changing it. The Preview HTML mismatch means static/function precedence is an unresolved deployment blocker.

| Setting | Proposed value | Notes |
| --- | --- | --- |
| Framework Preset | `Other` | Vercel documents `framework: null` for Other in `vercel.json`. |
| Root Directory | Repository root (`.`) | Composer, package locks, Laravel and Vite files are all at repo root. |
| Node.js Version | `22.x` | Matches `package.json` and the PHP runtime documentation; do not use local Node 24 as the Vercel target. |
| Install Command | No manual duplicate until build logs are inspected | The PHP runtime builder handles Composer and invokes `composer run vercel`; the script currently runs `npm ci`. Verify whether Vercel's project-level auto-install adds another install before overriding it. |
| Build Command | No separate duplicate of the runtime Composer hook | Confirm the exact build phase in Vercel logs. The custom runtime's build hook should be the single place running `composer run vercel` unless logs prove otherwise. Do not configure `composer setup`. |
| Output Directory | Leave unset/empty for the all-through-function candidate; consider `public` only for a proven static-assets design | `public` contains Laravel's `index.php`; static output must not shadow dynamic Laravel/API routes. Vercel may default Other to `public` when present, so inspect and explicitly override only after a route test. |
| Development Command | Leave unset; use PHP built-in server locally | `vercel-php` documentation says `vercel dev` is not supported. |
| PHP runtime/function | Existing `vercel.json`: `api/index.php`, `vercel-php@0.9.0`, 30-second max duration | Keep runtime pin unless compatibility testing requires a reviewed version change. Align region after confirming Aiven region and plan support. |
| Function routing | Current catch-all to `/api/index.php` is a candidate, not yet verified | Preserve `/`, Laravel pages, API verbs, query strings, 404 behavior, and static files in the route matrix. Do not route to PHP based on an assumption that Vercel protection is disabled. |

### Proposed Configuration Direction (Not Yet Approved or Implemented)

The current `vercel.json` already resembles the runtime's documented catch-all pattern. It should not be rewritten until the response source is identified. If logs show requests bypass the PHP function and the project should use the runtime's catch-all mode, validate a candidate with explicit `framework: null`, the function runtime declaration, and the catch-all route. If function logs prove that routes enter PHP but return an HTML protection/static page, adjust Vercel Deployment Protection or output precedence instead of changing Laravel code. Keep the existing static-file pass-through only after it is shown to work with the deployed runtime.

## Environment Variables

Set values in Vercel Project Settings → Environment Variables. Never paste values into docs, shell output, client-side code, or issue reports. Use separate Preview and Production scopes; a Preview pointing at Production is a deliberate exception and must remain read-only during diagnostics.

| Variable | Required | Recommended setting / use |
| --- | --- | --- |
| `APP_ENV` | Yes | `production` in Production; Preview may use `preview` or `production` consistently with logging/error handling. |
| `APP_DEBUG` | Yes | `false` in all deployed environments. |
| `APP_KEY` | Yes | Preserve the existing stable Production key. Use an isolated key for Preview if it has separate encrypted/session data; never regenerate on each deploy. |
| `APP_URL` | Yes | HTTPS origin for that environment. |
| `DB_CONNECTION` | Yes | `mysql` for Aiven. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Yes | Aiven service connection values in Vercel environment scope. Do not copy into `.env` or logs. `DB_URL` is supported by config but should not duplicate/override the explicit variables unintentionally. |
| `MYSQL_ATTR_SSL_CA` | Conditional | Optional path only if deliberately set; entry point otherwise resolves the bundled CA. The file must exist in the function bundle. |
| `AIVEN_CA_PEM_B64` | Optional | Base64 CA override; secret environment variable only. Prefer one deliberate CA source and keep it updated on Aiven rotation. |
| `SESSION_DRIVER` | Yes | `database`; verify `sessions` migration is applied. |
| `SESSION_SECURE_COOKIE` | Yes | `true` on HTTPS. |
| `SESSION_SAME_SITE` | Recommended | `lax` for same-origin app. |
| `SESSION_DOMAIN` | Usually unset | Set only for a reviewed cross-subdomain setup. |
| `SANCTUM_STATEFUL_DOMAINS` | Yes for SPA auth | Production hostname, plus approved preview hosts only if preview authentication is enabled. |
| `CACHE_STORE` | Yes | `database`; verify cache table exists. |
| `QUEUE_CONNECTION` | Yes | `sync` only for the demo if synchronous notification execution is acceptable; otherwise use a separately hosted queue/worker. Do not leave database queues without a consumer. |
| `LOG_CHANNEL` | Yes | `stderr`/errorlog channel supported by current config; verify Vercel captures it. |
| `MAIL_MAILER`, `MAIL_*` | Needed for real password recovery | Configure a real mail transport; current sample `log` mailer does not deliver. |
| `FILESYSTEM_DISK` | Yes | Current local disk is not durable. Do not rely on product uploads until a durable object store and upload flow are selected. |
| `VITE_APP_NAME` | Optional build-time, public | Existing non-secret app display name only. Never put credentials or private configuration in any `VITE_*` variable. |
| `VERCEL_PHP_DOCROOT` | Runtime-specific | `public` only if the pinned runtime expects this variable; verify against runtime documentation and logs before setting. |

## Ordered Investigation and Implementation Tasks

Tasks below track active investigation and implementation; unverified items remain open. Production database checks are limited to read-only access until a separately reviewed migration/release step is approved.

### Immediate Production Security Action

The public app alias returned a Laravel API exception JSON response containing a trace. Treat exception details as publicly exposed until corrected. Ensure `APP_DEBUG=false` is assigned to the Vercel Production environment and create a new Production deployment; Vercel applies environment-variable changes to new deployments, not already deployed functions ([environment variable documentation](https://vercel.com/docs/environment-variables/managing-environment-variables)). Then repeat only the read-only API GET and confirm the response no longer includes exception traces. Keep the resulting HTTP 500, if any, for a separate database/TLS diagnosis. The Vercel connection available to this task was denied access to the project/team, so I could not perform this dashboard change.

- [ ] **014.1 — Diagnose Preview HTML source.** Objective: determine whether requests reach Laravel, are intercepted by Deployment Protection/static output, or fail in the PHP function. Files: no source changes initially; inspect Vercel deployment settings/logs and `vercel.json`. Dependencies: Vercel project authorization is currently unavailable; a requested authenticated access operation was denied (403), so function logs/settings remain inaccessible. Validation so far: unauthenticated Preview `/up` returns 302 to Vercel SSO and `/api/categories` returns Vercel 401 JSON. The app alias reaches PHP 8.5.2; Laravel's built-in `/up` handler returns 200 HTML for browser requests and 200 JSON for an `Accept: application/json` request. `/api/categories` reaches Laravel and returns a 500 JSON `QueryException` with trace present. Acceptance still requires protection/settings/log evidence and an authorized Preview URL to inspect the underlying Preview deployment. No writes, migrations, or seeds were sent.
- [ ] **014.2 — Verify pinned PHP runtime/build pipeline.** Objective: validate `vercel-php@0.9.0` PHP/extension support and ensure Composer/npm install/build execute once. Files: `vercel.json`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`. Current evidence: the user-provided alias reports PHP 8.5.2. Runtime extension list, Vercel build log, Composer invocation flags, Node/build phase, Vite artifact, and function bundle size remain unverified because project access was denied. Acceptance: runtime build and PHP extensions are evidenced in logs; there are no duplicate/skipped build steps.
- [ ] **014.3 — Select verified static/routing settings.** Objective: serve static Vite files and route Laravel web/API methods to PHP without HTML fallback. Files: `vercel.json`, `api/index.php`, Vercel dashboard settings. Dependencies: 014.1–014.2. Current evidence: the app alias's API request reaches Laravel/PHP and returns JSON-formatted 500; its `/up` HTML browser response and JSON-negotiated response both match Laravel's health handler. Static assets, other web/API methods, 404 behavior, and the protected Preview's route behavior remain unverified. No routing changes are justified by current evidence.
- [ ] **014.4 — Verify frontend production assets and browser behavior.** Objective: ensure Laravel `@vite` uses `public/build/manifest.json` and every referenced asset is available under HTTPS. Files: Vite inputs and React entry points; change configuration only if a reproduced mismatch requires it. Dependencies: 014.2–014.3. Validation: production manifest/file checks, browser console, asset requests, React page initialization, routing/refreshes, forms and API requests. Acceptance: no missing manifest/assets or client runtime errors on Preview.
- [ ] **014.5 — Verify Aiven TLS and read-only database access.** Objective: use the intended environment variables and current Aiven CA with verified TLS. Files: `api/index.php`, `config/database.php`, docs only if a verified result requires it. Dependencies: 014.1–014.3, explicit confirmation whether the Preview targets Production. Validation: sanitized function logs and a read-only query; test invalid CA rejection only in a safe non-production scope. Current evidence is a Laravel `QueryException` without inspected raw message; it could be TLS, network, authentication, schema, or another query error. Acceptance: Aiven query succeeds over verified TLS, invalid trust is rejected, and no credentials/DSNs appear in logs. Risk: Production data; no writes or migrations.
- [ ] **014.6 — Configure serverless runtime stores and integrations.** Objective: confirm database sessions/cache, queue behavior, log capture, HTTPS/proxies, mail, and upload limitations. Files: Vercel environment settings; `bootstrap/app.php` or Laravel config only when an issue is reproduced. Dependencies: 014.3–014.5. Validation: auth/CSRF/cookie flow, cache/session tables, job processing semantics, logs, mail recovery in Preview; verify uploads remain disabled until storage is durable. Acceptance: no reliance on persistent local writes or a missing queue worker; production-safe cookie/proxy behavior.
- [x] **014.7 — Add Production-only schema migrations to the Vercel build hook.** Added `deployment:migrate` in `routes/console.php` and wired it after `npm run build` in `composer.json`. It runs `migrate --force --no-interaction` only when `VERCEL_ENV=production` and `APP_ENV=production`, configures Aiven TLS from the base64 variable, configured CA path, or bundled certificate, and never runs seeders. Local verification found this workspace targets `127.0.0.1:3306/vendorsshop` and all current migrations are already applied there; this is not Aiven. Vercel CLI is not installed in the workspace. The Aiven migration is therefore not yet run or verified. Before deploying, back up Aiven and ensure Vercel exposes system environment variables; verify the Production build log.
- [ ] **014.8 — Close deployment docs and decide platform fit.** Objective: record verified dashboard values and runtime behavior, update status, and compare a conventional Laravel host if material issues remain. Files: deployment docs, current state, known issues, changelog, backlog, this plan. Dependencies: 014.1–014.7. Validation: documentation links, secret scan of changed docs, git diff checks. Acceptance: docs distinguish tested Preview and Production behavior; unresolved runtime/storage/worker risks have owners; Vercel or an alternative is selected based on evidence.

## Testing and Deployment Checklist

This checklist tracks remaining validation. During the initial investigation no build, test, migration, or deployment was run. In the current implementation session, the gated migration command was registered and verified to skip outside Vercel Production; syntax and Composer validation passed. No migration was run against Aiven and no deployment was performed.

### Local and Build Checks

1. `composer validate --no-check-publish` and `composer check-platform-reqs` using PHP 8.5-compatible CLI; confirm `pdo_mysql`, `openssl`, `fileinfo`, `mbstring`, `intl`, and any image extension actually required by code.
2. `npm ci` with Node 22.x, then `npm run build`; inspect `public/build/manifest.json` and ensure each referenced CSS/JS/font/image file exists. Do not commit secrets into Vite variables.
3. `php artisan route:list --except-vendor` and local HTTP requests via `php -S localhost:8000 api/index.php` as the runtime README suggests; don't assume `vercel dev` support.
4. Read-only DB connectivity in a separate staging/local config; verify TLS and CA failure behavior. Do not run a local query against Production by default.

### Preview Checks

1. Verify Deployment Protection and dashboard Framework/Root/Install/Build/Output settings before interpreting any `200 text/html` response.
2. Confirm function logs show the expected runtime/function invocation and no boot exception. Record sanitized PHP version/extensions and bundle size.
3. Check `GET /up` response content/type, then `GET /api/categories` with `Accept: application/json`; require JSON content type and application shape. Check Laravel pages and unknown-route 404 behavior.
4. Fetch a CSS/JS/font from `public/build` and verify status/content type. Inspect browser console and React initialization.
5. Check auth/login/CSRF/cookie behavior only with Preview-scoped users; check password reset through a non-production mail provider.
6. Only after the owner confirms the Preview database target, run the planned minimal read-only DB query and verify TLS. Do not call order, admin mutation, upload, or checkout POST routes.
7. Verify Vercel logs, runtime duration/memory, Aiven latency, and connection capacity. Vercel functions have `/tmp` scratch only, a documented 4.5 MB request/response limit, and a 250 MB uncompressed function bundle limit; recheck current platform limits before rollout.

### Production Readiness

1. Set environment variables per Production scope; preserve `APP_KEY`; set HTTPS `APP_URL`, `APP_DEBUG=false`, and safe logs.
2. Confirm current Aiven CA, service host/port, access policy, region, database name, least-privilege user, database contents, and connection limits.
3. Back up Aiven and verify restore procedure; review schema and migrations; rehearse on staging.
4. Back up Aiven before schema-changing releases. A Vercel Production build now runs `php artisan deployment:migrate`; it configures TLS and invokes pending migrations only. It does not run demo seeders. Check its build log, then verify `/up` and the application routes.
5. Promote only after Preview passes; then check custom domain/SSL, health and API content types, assets, auth, mail, DB, logs, and capacity.

## Rollback and Troubleshooting

- If Preview returns HTML for a JSON route, first check deployment protection, response headers/content type, Vercel routing and Output Directory, then function invocation logs. Do not assume a Laravel or database error based on HTTP 200 alone.
- If the PHP function is not invoked, correct Vercel project routing/build settings. If invoked, inspect sanitized PHP/runtime logs and verify Laravel's route bootstrap; do not add a second catch-all without testing route precedence.
- If Vite assets are missing, inspect build command order, `public/build/manifest.json`, artifact contents, output directory and `@vite` references. Do not commit generated assets as an unexplained workaround.
- If TLS fails, separately check CA path/file inclusion, Aiven CA freshness, PDO option support, hostname verification, DNS/TCP reachability, access controls, and credentials. Never retry without TLS verification and never print connection strings.
- If a code/config deployment regresses, redeploy the last known-good Vercel deployment and restore the previous compatible environment/CA reference. Keep TLS verification enabled.
- If a Production migration has already run, do not automatically reverse it. Stop writes if needed, use the confirmed backup/restore plan or a reviewed forward-fix, and verify integrity.
- If the runtime or static/function routing remains unstable, compare Laravel Cloud or an always-on PHP host (Railway, Fly.io, VPS) with the same Aiven TLS requirements.

## Acceptance Criteria

- [ ] Preview `/up` reaches Laravel and behaves as documented; `/api/categories` returns JSON, not a protection page or static HTML.
- [ ] Vercel logs show the pinned supported PHP runtime with required extensions, and a complete Laravel/Vite build without duplicate dependency installs or database mutations in the build hook.
- [ ] All Laravel web routes, API methods, missing routes, query strings, authentication/session requests, and Vite static assets reach their intended handlers.
- [ ] Aiven is accessed from the intended Vercel environment with current CA verification; a read-only query succeeds and invalid CA fails safely.
- [ ] Production env values are scoped correctly, credentials remain server-side, HTTPS cookies/proxy headers work, logs are captured without secrets.
- [ ] Sessions/cache use persistent DB storage; queue behavior has an actual consumer or is explicitly synchronous; uploads are not relied on until durable storage is selected.
- [ ] Production migrations have succeeded against Aiven in a Vercel Production build. No demo seeders run in Production.
- [ ] A post-deployment smoke checklist and rollback procedure pass; docs record verified evidence and remaining constraints.

## Risks, Dependencies, and Open Questions

- The supplied Preview deployment URL currently returns a 302 Vercel SSO redirect for `/up` and Vercel 401 JSON for `/api/categories`. The app alias reaches PHP/Laravel but `/api/categories` throws a `QueryException`; its response includes a trace. Vercel project authorization/log access and Production `APP_DEBUG=false` need immediate attention. The exact DB/TLS error is unknown.
- A Vercel connector operation to create or reuse a shareable URL was denied with HTTP 403 because its connected account is not authorized for this deployment's project/team. Project settings, function logs, and environment variable names/scopes remain unavailable through that connection.
- `vercel-php` is community maintained. Verify exact runtime `0.9.0` support and Composer behavior rather than relying only on the default branch README/source.
- `composer run vercel` currently builds npm assets inside the runtime hook; Vercel top-level install/build defaults may duplicate this or affect output ordering.
- The current static-file `return false` path depends on PHP built-in server/runtime semantics and needs real Preview validation.
- The current Aiven CA parses locally and is marked CA/current-valid, but actual trust chain, hostname verification, remote network access, and CA rotation status are not confirmed.
- Product image uploads cannot persist to Vercel local disk. The user deferred object storage; this is a production blocker for relying on admin image uploads.
- Database-backed session/cache tables, queue worker, production mail, Aiven connection limits, user privileges, DB contents, and Vercel Production migration state need confirmation.
- Owner clarified demo seeders are not needed in Production. Keep them out of the Vercel Production build; only the gated migration command runs.
- Vercel's single-region default and request/bundle limits may affect latency or uploads. Confirm plan entitlements and current limits.

## Final Summary and Remaining Work

This implementation session confirmed that the workspace targets local MySQL, not Aiven; the Vercel CLI is not installed. A Production-only migration command is now part of the Vercel Composer build hook, configures Aiven TLS, and does not seed. It has not yet run against Aiven; deployment and remote verification remain outstanding. The public app alias previously returned a Laravel `QueryException` trace, so ensure Production `APP_DEBUG=false`. Plan 014 remains in progress.
