# 012: Vercel Production Deployment with Aiven MySQL

- ID: `012-vercel-aiven-production-deployment`
- Status: in_progress
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: not completed

## Problem and Intended Outcome

Prepare this Laravel API and React storefront for production deployment to Vercel, using the MySQL database already created in Aiven. The user reports that Aiven MySQL connection environment variables have already been added to Vercel. Those values are not visible to this plan; verify variable names, environment scope, TLS certificate configuration, and network connectivity during implementation without exposing secret values.

The deployment must build and serve the React/Vite assets, persist sessions and cache, store product images durably, connect to Aiven over TLS, and provide a safe migration and release workflow. This document is a plan only; no deployment configuration or application files have been changed.

## Scope

### In Scope

- Configure the community PHP runtime `vercel-php@0.9.0`, a Laravel entry point, and catch-all routing.
- Build production assets and package Laravel production dependencies.
- Connect Laravel to the production Aiven MySQL service using TLS.
- Keep mutable runtime writes in `/tmp`, Aiven, or external object storage.
- Configure production-safe session, cache, queue, logging, mail, Sanctum, and proxy settings.
- Move product images from local storage to durable object storage and address function payload limits.
- Define migration, deployment, and smoke-check procedures.

### Non-Goals

- Implementing or deploying the configuration in this plan.
- Changing marketplace behavior unrelated to deployment.
- Running production migrations, seeders, or modifying Aiven data before a backup and release procedure are agreed.
- Selecting a storage provider, Vercel region, or plan tier without the required project information.

## References

- Requirements: user request to deploy this Laravel application to Vercel with production Aiven MySQL.
- Architecture: [docs current state](../status/current-state.md), [architecture overview](../architecture/overview.md), [development setup](../development/setup.md).
- Decisions: none recorded yet; deployment choices should be documented after they are confirmed.
- Primary platform references:
  - [vercel-php runtime](https://github.com/vercel-community/php)
  - [Vercel function limits](https://vercel.com/docs/functions/limitations)
  - [Vercel filesystem/runtime behavior](https://vercel.com/docs/functions/runtimes)
  - [Vercel function configuration](https://vercel.com/docs/project-configuration/vercel-json)
  - [Aiven PHP/MySQL connection](https://aiven.io/docs/products/mysql/howto/connect-with-php)
  - [Aiven TLS certificates](https://aiven.io/docs/platform/concepts/tls-ssl-certificates)

## Existing Implementation Findings

### Verified facts

- `composer.json` requires PHP `^8.3` and Laravel `^13.17`; this workspace has Laravel 13.35.0 and PHP 8.4.23. The current `vercel-php@0.9.0` README describes PHP 8.5.x, while `0.8.0` describes PHP 8.4.x. PHP 8.5 meets the declared PHP constraint, but the project has not been verified on that runtime.
- The runtime lists PDO MySQL (`pdo_mysql`), PDO, OpenSSL, `intl`, `fileinfo`, mbstring, DOM, and XML. GD is not listed. Current application image storage does not appear to perform image manipulation.
- Vite is used; `npm run build` runs `vite build`. `.gitignore` excludes `/public/build`, and the build output is not tracked, so deployment must generate it.
- `config/database.php` defaults to SQLite but includes MySQL configuration using `DB_*`/`DB_URL`. Its PDO options read `MYSQL_ATTR_SSL_CA` as a certificate file path. The user reports that Aiven MySQL credentials are present in Vercel, but the exact environment variable names and scopes have not been verified.
- `.env.example` defaults to database sessions, cache, and queues; the matching tables have migrations. `config/logging.php` offers a `stderr` channel.
- `config/filesystems.php` defines the `public` disk as local. Product upload, URL-generation, and deletion code explicitly select `Storage::disk('public')`. The repository defines an S3 disk, but `composer.json` does not currently require the Flysystem S3 adapter.
- Product upload validation accepts up to 10 images per request, each up to 2 MB. Vercel documents a 4.5 MB function request/response payload limit, so the existing multi-image request can exceed the platform limit.
- `NotifyVendorJob` implements `ShouldQueue`; `QueueAutoProcessor` may run `queue:work`. Vercel functions cannot provide a persistent queue worker. No scheduled tasks, Reverb, or Horizon integration were found in the inspected code.
- Sanctum stateful API middleware is enabled. The React Axios setup sends credentials and XSRF tokens. Production hostnames must be configured for same-origin session auth.
- `routes/web.php` includes closure routes, so do not assume route caching is safe. Runtime filesystem writes must avoid the read-only deployment filesystem; Vercel documents writable `/tmp` scratch storage up to 500 MB. Function bundles have a 250 MB uncompressed limit including runtime layers.
- Local `vendor` is about 48 MiB including development dependencies, and `node_modules` is about 98 MiB. These local folder sizes do not prove the final function bundle size; measure the deployed production bundle.
- `composer.json`'s `setup` script includes `php artisan migrate --force`; do not invoke that setup script as part of a Vercel build. Do not seed production.

### Inferences

- Database-backed sessions and cache should work with Aiven once their tables are migrated and function-to-Aiven network access is confirmed.
- The least-complex current queue configuration is `QUEUE_CONNECTION=sync`, as the present job logs/simulates notifications and no always-on worker is configured. If real asynchronous delivery is required, use a managed queue plus a separately hosted worker.
- A single region close to Aiven should reduce DB request latency. The correct region cannot be selected until the Aiven service region is known.

### Unanswered questions

- What is the Aiven service region? Which Vercel region is closest and supported for this project?
- Are the Vercel variables named `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`, and are they scoped to Production? Is the Aiven CA certificate already available to the function?
- Is the Aiven database empty, or does it contain production data that must be preserved?
- Which object-storage provider should hold product images? The user chose to defer this decision and leave uploads unconfigured for now. Does the current catalog contain local image files that need migration?
- Should Vercel preview deployments support authenticated sessions?
- Which mail service should deliver password recovery emails?
- What Vercel plan/compute settings apply to the project?

## Proposed Design

### Affected Components

- New: `api/index.php`, `vercel.json`, `.vercelignore`.
- Update: `composer.json` with a Vercel build hook; `package.json` with a Node 22 engine declaration; `config/filesystems.php` so the explicitly used `public` disk can select durable object storage; `bootstrap/app.php` for proxy trust after validating the deployment ingress.
- Potential application changes: product upload flow and admin React upload client for direct or split object-storage uploads; storage adapter dependency and `composer.lock`; focused tests for upload behavior and runtime path setup.
- Documentation: after implementation, update deployment instructions, verified current state, and this plan with validation results.

### Database

- Use Aiven MySQL in Production via Laravel's `mysql` connection. Confirm the deployed environment variable names and that their values are scoped to the correct Vercel environment.
- Use the Aiven CA certificate. The project owner identified `storage/certs/ca.pem` as the Aiven CA bundle. The Vercel entry point resolves this bundled file to an absolute path and sets `MYSQL_ATTR_SSL_CA` before requiring `public/index.php`. `AIVEN_CA_PEM_B64` can override it by writing the certificate under `/tmp`; a valid `MYSQL_ATTR_SSL_CA` path is also accepted. Verify the effective TLS behavior with an actual staging connection; do not disable certificate verification to resolve errors.
- Back up an existing Aiven database before migration. Review pending migrations and test against a disposable/staging MySQL database. Run `php artisan migrate --force` from a controlled CI/release job with Aiven network access. Do not use `migrate:fresh` or run `DatabaseSeeder` in Production.
- Database sessions/cache are acceptable for initial deployment. Measure connection use under realistic concurrency and consider connection pooling if the Aiven service limit is approached.

### API / Routes

- Route all HTTP requests to `api/index.php`, then bootstrap through `public/index.php` as required by the runtime approach.
- Keep the existing `/up` health endpoint. Add no public migration endpoint.
- If a scheduled task is later introduced, invoke a protected endpoint from Vercel Cron or an external scheduler; no scheduler currently needs deployment.

### Frontend

- Use the runtime's Composer `vercel` script to run `php artisan package:discover --ansi`, `npm ci`, and `npm run build`. Do not run the `setup` script or migrations during build.
- Keep generated `public/build` assets in the deployment artifact. Configure the PHP document root as `public`; verify that allowlisted static files can be served by the PHP runtime and that missing assets reach Laravel correctly.
- Move images to S3-compatible object storage. Configure the existing `public` disk to use that provider because application code explicitly chooses the `public` disk. Add the provider adapter to Composer and update the lock file through Composer.
- Prefer direct-to-object-storage uploads (or individual bounded uploads) so file bytes do not exceed Vercel's 4.5 MB function request limit. Update the upload API/client, validation/metadata flow, URLs, and deletion behavior together. Use signed uploads so storage credentials are not exposed to the browser.

### Security

- Set `APP_ENV=production`, `APP_DEBUG=false`, a stable `APP_KEY`, HTTPS `APP_URL`, and `LOG_CHANNEL=stderr`.
- Configure Sanctum stateful domains for the production host. Use secure, `SameSite=Lax` session cookies and leave `SESSION_DOMAIN` unset for a same-origin host unless a reviewed cross-subdomain setup requires it.
- Trust forwarded proxy headers only when the app is reachable through Vercel's proxy; verify HTTPS and host handling in preview.
- Keep DB, mail, and object-storage secrets in Vercel's environment variables; never add values to source, Vite-exposed variables, build logs, or this plan.
- Use database-backed sessions/cache. Set the current queue to `sync` unless a separately hosted queue consumer is introduced. Use a real mail provider for password resets; the sample `.env.example` mail driver logs messages only.

## Implementation Tasks

- [ ] Confirm Aiven region, service type, Production variable names/scope, TLS CA availability, and whether existing data must be preserved.
- [ ] Confirm object-storage provider, whether existing catalog images need copying, preview-auth expectations, mail provider, and Vercel plan.
- [x] Add initial `vercel.json` using `vercel-php@0.9.0`, catch-all routing, and a conservative `maxDuration`. Region remains unset until the Aiven location is known.
- [x] Add `api/index.php` to initialize `/tmp` storage and compiled-view directories, use the supplied `storage/certs/ca.pem` by default (with env overrides and fail-closed behavior on Vercel), route safe static-file requests, and require `public/index.php`.
- [x] Add `.vercelignore` excluding development-only/local data while retaining migrations, public assets, build output, bootstrap build artifacts, Composer/npm manifests, and application code.
- [x] Add Composer `vercel` build script for package discovery, `npm ci`, and Vite build; declare Node `22.x`. Build has not been run.
- [x] Configure MySQL PDO server-certificate verification when PDO MySQL is available.
- [x] Configure Laravel to trust forwarded proxy headers for the Vercel ingress path; verify the production host and HTTPS behavior before rollout.
- [ ] Configure durable product-image storage. The user chose to defer provider selection; local `/tmp` storage must not be treated as persistent. Add the selected adapter and direct/signed upload flow before Production image uploads are relied upon.
- [ ] Configure Production Vercel environment values for Aiven MySQL, TLS, storage, sessions, cache, queue, logs, mail, APP URL/key, Sanctum, and document root. Keep Preview values separated.
- [ ] Verify trusted proxy behavior, HTTPS cookies, Sanctum CSRF, login, registration, and logout on a preview deployment.
- [ ] Create a controlled pre-deploy migration job. Verify Aiven access from that runner, take/confirm backup, review migration SQL against MySQL, run pending migrations with `--force`, and never seed production.
- [ ] Deploy preview; validate build, function start, assets, Aiven reads/writes, uploads, auth, logs, and limits. Record bundle size and any cold-start/connection issues.
- [ ] Deploy production after migration approval; configure custom domain/SSL; run health and smoke checklist; document actual settings and results.

## Acceptance Criteria

- [ ] Vercel builds and deploys the application with generated Vite assets and a verified function bundle below the platform limit.
- [ ] Production Laravel requests connect successfully to Aiven MySQL over verified TLS using Vercel Production variables.
- [ ] Existing production data is preserved; pending migrations run through a documented, backup-aware release step without production seeding.
- [ ] Login, CSRF-protected Sanctum requests, and password recovery work on the HTTPS production domain.
- [ ] Product images use persistent object storage, survive new function instances, and can be listed and deleted. Upload requests do not exceed the function payload limit.
- [ ] Sessions and cache use persistent storage; logs reach Vercel logs; no feature relies on persistent local function storage or a long-running worker.
- [ ] `/up`, storefront routes, API routes, and static assets pass post-deploy checks; deployment documentation records actual verification results.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| PHP runtime and Composer install on selected `vercel-php` version | Production dependencies install and Laravel boots | Not run; implementation in progress |
| PHP syntax and formatting | Changed PHP files parse and follow project style | `php -l` passed for `api/index.php`, `bootstrap/app.php`, and `config/database.php`; Pint passed |
| Composer manifest | `composer.json` is valid | `composer validate --no-check-publish` passed |
| Deployment JSON | `vercel.json`, `package.json`, and `package-lock.json` are valid JSON | Parsed successfully |
| Vite production build | `public/build/manifest.json` and referenced assets are present | Not run; implementation in progress |
| Aiven CA PEM syntax | PHP OpenSSL can parse the provided certificate file | Passed locally with `openssl_x509_read`; trust/rotation and live connection are not verified |
| Staging Aiven TLS/database connectivity | Laravel can connect and query using the CA and configured variables | Not run; credentials are in Vercel and were not inspected |
| MySQL migrations on disposable/staging Aiven database | All pending migrations apply without data reset or seeding | Not run; no migration was run |
| Vercel preview smoke checks | Health, pages, APIs, auth, assets, and uploads work | Not run; implementation in progress |
| Production deployment smoke checks | Production hostname and Aiven-backed flows work | Not run; implementation in progress |

## Risks, Dependencies, and Open Questions

- `vercel-php` is community-maintained, and its current runtime uses PHP 8.5.x while this project has only been verified locally on PHP 8.4.23. Test the dependency lock on PHP 8.5 before production rollout; use `vercel-php@0.8.0` only if PHP 8.4 compatibility is necessary and the older runtime remains supported.
- Vercel functions have a 250 MB uncompressed bundle limit including runtime layers, a 4.5 MB request/response payload limit, and read-only application files with `/tmp` scratch storage up to 500 MB. Validate actual bundle and upload behavior on a preview deployment.
- Aiven connection limits and network access from Vercel and the migration runner are unknown. Align Vercel region to Aiven and evaluate pooling/limits before launch.
- The Aiven CA PEM parses locally with PHP OpenSSL. Its trust/rotation status and actual TLS connection are not verified. Do not ship until a real function invocation confirms certificate verification.
- Object storage is not selected. Local product images will not be durable on Vercel; an S3-compatible provider and an upload-flow decision are dependencies for production readiness.
- The current queued job has no worker process in Vercel. `sync` changes execution timing; if asynchronous behavior becomes required, provide an external worker/queue.
- Preview Sanctum host configuration, production mail, Vercel plan/compute settings, current database contents, and existing image migration requirements remain open.
- If conventional Laravel request handling, durable local filesystem, or long-running workers are important, compare Laravel Cloud or an always-on PHP host (Railway, Fly.io, or a VPS) before implementation; Vercel introduces deployment-specific runtime and upload constraints.

## Implementation Notes and Discoveries

- User confirmed Aiven MySQL exists, reports that its environment variables were added to Vercel, and identified `storage/certs/ca.pem` as the Aiven CA file. The entry point prefers a non-empty `AIVEN_CA_PEM_B64` override, then a configured `MYSQL_ATTR_SSL_CA` path, then the bundled CA file; it fails closed on Vercel if none resolve. PHP OpenSSL successfully parsed the supplied PEM. Never request or record credential values; verify env names/scope and live TLS without copying secrets into logs or docs.
- Initial Vercel scaffolding was added during implementation: `vercel.json`, `api/index.php`, `.vercelignore`, Composer's Vercel build script, Node 22 engine declaration, trusted-proxy configuration, and MySQL PDO server-certificate verification. Actual Aiven connectivity has not been verified.
- The user asked whether Vercel local disk can persist product images and chose to defer object-storage selection. Vercel `/tmp` is ephemeral; image persistence is an outstanding production-readiness dependency.
- Laravel 13.35's `Application::storagePath()` supports `LARAVEL_STORAGE_PATH`; view config supports `VIEW_COMPILED_PATH`. Use these for runtime writable paths. Keep `bootstrap/cache` as a read-only build artifact generated during package discovery; do not regenerate it during a request.
- Vercel runtime README documents `vercel-php@0.9.0`, PHP 8.5.x, PDO MySQL, Node 22.x, catch-all routes, `.vercelignore`, and a Composer script named `vercel`. The README suggests using PHP's built-in server locally instead of `vercel dev`.
- `config/database.php` supplies `MYSQL_ATTR_SSL_CA` but currently does not demonstrate that the production Aiven endpoint has been tested or that hostname identity verification is enabled. Confirm TLS behavior with Aiven guidance before accepting this setting.
- `APP_STORAGE` is not a Laravel framework variable. Prefer Laravel's native `LARAVEL_STORAGE_PATH` and `VIEW_COMPILED_PATH`.

## Final Summary and Remaining Work

Initial deployment scaffolding is implemented, but Plan 012 remains in progress. Vercel deployment, Aiven TLS connectivity, migrations, production database state, and region choice are not verified. The object-storage provider is intentionally deferred by the user; local function disk must not be considered persistent. Remaining Vercel environment, preview-auth, mail, and plan details are also unconfirmed.
