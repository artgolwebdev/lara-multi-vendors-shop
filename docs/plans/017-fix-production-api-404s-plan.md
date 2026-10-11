# 017: Fix Production Laravel API 404s on Vercel

- ID: `017-fix-production-api-404s`
- Status: in_progress
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: not completed

## Problem and Intended Outcome

After commit `2cfb494` was pushed to `main`, Vercel reported the Production deployment Ready. The user then reported browser requests from the storefront JavaScript returning HTTP 404 for `GET /api/user` and `GET /api/products?sort=price_asc&per_page=4`. The browser is executing the storefront bundle far enough to issue these API requests, but the application flow is not working.

Both paths are registered in this repository's Laravel API route file. `/api/products` is public and accepts the reported sort and pagination parameters. `/api/user` is protected by Sanctum and should return an authentication response when no valid session exists; a 404 is not the expected unauthenticated result. Identify whether the 404 comes from Vercel routing/function dispatch or Laravel route matching, then make the smallest evidence-backed correction and verify the same-origin API and authentication flows in Production.

## Scope

### In Scope

- Capture status, response body, content type, and relevant Vercel headers for both reported URLs on the active Production deployment.
- Establish which exact commit/deployment serves the affected alias and whether its PHP function is invoked for API requests.
- Compare the deployed behavior with Laravel's route table and the repository's Vercel rewrite and PHP entry point.
- Verify preservation of the original request path, HTTP method, query string, headers, and cookies through the Vercel rewrite and `public/index.php` bootstrap.
- Implement a focused correction only after identifying the layer producing the 404.
- Verify public catalog JSON, Sanctum's unauthenticated and authenticated behavior, and that storefront/static routes continue to work.
- Update deployment, status, and plan records with observed results.

### Non-Goals

- Do not change product data, Aiven credentials, database schema, migrations, or seeders to address a route-level 404.
- Do not make `/api/user` public or weaken Sanctum authorization.
- Do not add a database fallback or conceal a database failure as an empty catalog.
- Do not disable Vercel Deployment Protection or expose credentials/logs to make checks easier.
- Do not infer a successful API fix from a Ready deployment or from static assets loading.

## References

- [Current state](../status/current-state.md), [known issues](../status/known-issues.md), and [deployment guide](../development/deployment-vercel.md).
- [Plan 016: blank storefront and asset delivery](016-fix-blank-production-storefront.md).
- [Laravel API endpoint inventory](../api/endpoints.md) and [Sanctum authentication behavior](../api/authentication.md).
- Vercel deployment config: `vercel.json`; function entry point: `api/index.php`; Laravel route registration: `bootstrap/app.php`; API routes: `routes/api.php`.
- Frontend callers: `resources/js/store/authSlice.js`, `resources/js/components/AuthApp.jsx`, `resources/js/components/AdminApp.jsx`, and `resources/js/components/store/pages/HomePage.jsx`; Axios defaults: `resources/js/bootstrap.js`.
- Existing coverage: `tests/Feature/ApiAuthenticationTest.php` and `tests/Feature/StorefrontProductListingTest.php`.
- User-provided browser console evidence: both `/api/user` and `/api/products?sort=price_asc&per_page=4` return 404 from `https://lara-multi-vendors-shop.vercel.app/` after the Production push.

## Existing Implementation Findings

### Verified Repository Facts

- `php artisan route:list --path=api --except-vendor` registers 52 API routes in the workspace, including `GET api/products` and `GET api/user`.
- `routes/api.php` registers public `GET /api/products` at lines 23–24. The reported `sort=price_asc` and `per_page=4` values are explicitly supported and validated by `app/Http/Controllers/Api/ProductController.php`.
- `routes/api.php` registers `GET /api/user` inside an `auth:sanctum` group at lines 32–34. The frontend calls it to restore the current session. Existing feature coverage expects an authenticated request to succeed and an unauthenticated request to return 401.
- `bootstrap/app.php` explicitly loads `routes/api.php` and applies stateful API middleware. Its exception configuration renders API paths as JSON.
- `resources/js/bootstrap.js` configures Axios to send credentials and XSRF headers. The page and API use same-origin relative URLs.
- `vercel.json` declares the `vercel-php@0.9.0` function at `api/index.php` and rewrites every path to `/api/index.php`.
- `api/index.php` serves allowlisted existing public files, then requires `public/index.php` for non-static requests. It does not define an API route itself.
- Read-only browser checks against the Production alias showed `/up` returns Laravel's health page, `/api/categories` returns the storefront HTML instead of JSON, and `/api/products` and `/api/user` display a 404 page. The user also provided browser-console evidence of both API calls returning 404. The Vercel integration still returns 403 for deployment logs and protected deployment fetch.
- Reproducing requests through `php -S ... api/index.php` locally yielded the same route symptoms before the correction: `/api/categories` returned the SPA HTML, while `/api/user` and `/api/products` returned Laravel 404s. This isolates the cause to the PHP front-controller request context rather than missing route declarations or a Vercel-only static path.
- Vercel listed deployment `dpl_3AJqEQPwbi784jt5TTZnr7ZpWerm` for commit `2cfb494ae49470c66c7256c2a677adf659266999` as Production `READY`. A Ready state does not establish API route success.

### Inferences to Verify

- Since both a public route and an authenticated route report the same 404, a shared rewrite/function-dispatch or Laravel request-path issue is more likely than a Sanctum-only problem. The response origin must be established before selecting a fix.
- If Laravel handles `/api/user` without a valid session, its expected result is an authentication response (normally 401 JSON for this app), while `/api/products` should return catalog JSON or a distinct database/server error. Matching 404s suggest the requests may not be reaching their expected Laravel routes, but this is not proven.
- The PHP runtime executes the handler from the repository's `/api` directory. Symfony HttpFoundation uses `SCRIPT_FILENAME`, `SCRIPT_NAME`, and `PHP_SELF` to derive the request base URL; with those variables identifying `/api/index.php`, it incorrectly treats `/api` as Laravel's base path and strips that prefix before route matching. The exact symptom was reproduced locally through the entry point.

### Unknowns

- Whether the 404 response is generated by Vercel's routing layer, the PHP runtime, Laravel's router, or an application/database exception translated by another layer.
- What response body, content type, Vercel request ID, and deployment URL correspond to each 404.
- Whether `/up`, `/api/categories`, and other Laravel routes reach the same deployed PHP function.
- Whether Vercel preserves the original `/api/...` `REQUEST_URI`, query parameters, `Accept` header, and Sanctum cookies after the corrected server variables are applied.
- Whether the deployment alias currently points to commit `2cfb494` or has moved since the deployment status lookup.
- Whether valid session cookies reach Laravel and whether `SANCTUM_STATEFUL_DOMAINS`, cookie domain, and secure-cookie settings match the production hostname.
- Whether the Aiven catalog query succeeds after route dispatch; database failure should surface separately from routing 404.

## Proposed Design

### Affected Components

- `vercel.json`: inspect catch-all rewrite semantics and function routing. Change only if request evidence shows a rewrite/configuration mismatch.
- `api/index.php`: keep the incoming request URI, method, query, headers, and cookies intact, but set `DOCUMENT_ROOT`, `SCRIPT_FILENAME`, `SCRIPT_NAME`, and `PHP_SELF` to describe Laravel's `public/index.php` before bootstrapping Laravel. This prevents the handler's `/api` directory from being interpreted as Laravel's base URL. Leave asset handling unchanged.
- `bootstrap/app.php` and `routes/api.php`: change only if the deployed Laravel route table or API prefix differs from the local route table.
- `resources/js/store/authSlice.js` and related auth bootstrap: only adjust handling if the endpoint returns the expected 401 but the frontend treats that response incorrectly. Do not mask a server-generated 404 as a guest session.
- Production deployment/status docs and this plan: record deployment-specific evidence and validation.

### Database

No database changes are planned. First establish routing. For the public catalog endpoint, a successful route dispatch with a database failure should be recorded as a separate Aiven/database error (typically a 5xx), not treated as proof of a 404 fix. Use only read-only GET verification.

### API / Routes

- `GET /api/products?sort=price_asc&per_page=4` must reach the public catalog controller and return JSON with HTTP 200 when the database is available. The requested query string must arrive unchanged.
- `GET /api/user` must reach the Sanctum-protected route. Without a valid session it must return the app's JSON unauthenticated response (expected 401); with a valid same-origin session it must return the current user (200).
- `/up` and `/api/categories` provide control requests to distinguish function routing from endpoint-specific behavior. Do not interpret a DB-backed route's 5xx as a Vercel route miss.
- For each failure, capture response body/type and Vercel headers/request ID to tell a Vercel `NOT_FOUND` response from Laravel's JSON exception response.

### Frontend

- Preserve the existing same-origin relative Axios URLs and credentials/XSRF defaults.
- Verify that the auth bootstrap treats the expected 401 from `/api/user` as an unauthenticated visitor, while reporting unexpected 404/5xx responses as errors.
- Verify that the storefront renders the catalog result from `/api/products`, and that a server-side failure is visible as a recoverable error state rather than a blank page.

### Security

- Keep `/api/user` under `auth:sanctum`; never change its authorization to address a routing symptom.
- Use an existing test account only through the approved browser/session flow. Do not include session cookies, CSRF tokens, or secrets in logs or docs.
- Keep `APP_DEBUG=false` in Production and avoid returning stack traces during diagnostics.
- Limit remote checks to safe GET requests; no Production writes, migrations, or seeders are required for this investigation.

## Implementation Tasks

- [x] **017.1 — Capture the deployed 404 source.** Read-only Production browser checks show `/up` reaches Laravel, `/api/categories` renders storefront HTML, and `/api/products` and `/api/user` display 404s. The Vercel response/log connector returns 403, so response headers and server logs remain unavailable. The same behavior was reproduced locally through the PHP entry point.
- [x] **017.2 — Verify route/runtime request mapping.** `route:list` confirms the API declarations. Local PHP-server requests through `api/index.php` reproduce the failure; inspection confirms the script variables point Symfony's base-URL detection at `/api/index.php`, which strips `/api` from Laravel's route path. No cookie values were accessed or recorded.
- [x] **017.3 — Implement the evidence-backed correction.** `api/index.php` now sets the front-controller server variables to Laravel's `public/index.php`, preserving the request URI and query. Local entry-point smoke checks prove `/api/user` returns expected 401 JSON, catalog requests reach Laravel validation/database handling instead of 404, and the generated JS asset still serves. PHPUnit auth/catalog tests pass. Vercel deployment verification remains pending.
- [ ] **017.4 — Deploy and verify Preview first.** Deploy the candidate to a protected Preview environment with suitable non-production settings, then run the catalog and authentication checks. Acceptance: catalog request returns 200 JSON when its DB is available; unauthenticated `/api/user` returns expected 401 JSON; authenticated `/api/user` returns 200; Vercel request routing no longer returns a platform 404.
- [ ] **017.5 — Verify Production and close documentation.** After Preview passes, release using the approved deployment process and repeat read-only checks on the Production alias. Record commit/deployment and observed status; update current state, known issues, changelog, deployment guide, and backlog. Acceptance: the storefront API calls work in the browser and `/api/user` respects authentication.

## Acceptance Criteria

- [x] The root cause of both reported 404s is supported by the local PHP entry-point reproduction and Symfony request-path handling.
- [ ] Public catalog GET reaches Laravel, preserves `sort` and `per_page`, and returns expected JSON when Aiven is healthy.
- [ ] Unauthenticated `/api/user` returns the expected JSON authentication response; authenticated same-origin requests return the user.
- [ ] No API route is made public as a workaround; no database error is disguised as route success.
- [ ] Static assets, storefront web routes, health route, and API requests coexist under the deployed Vercel routing configuration.
- [ ] Preview verification passes before Production release; Production deployment is then checked independently.
- [ ] Documentation reports exact verified outcomes and clearly labels any remaining access or database limitation.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| Local Laravel API route inventory | `/api/products` public; `/api/user` Sanctum protected | Confirmed: `php artisan route:list --path=api --except-vendor` lists both among 52 routes. |
| Production browser request comparison | `/up`, `/api/categories`, `/api/products`, and `/api/user` distinguish routing behavior | Browser showed Laravel health at `/up`, storefront HTML at `/api/categories`, and a 404 page at the other two API paths. Vercel response headers/logs remain unavailable (403). |
| PHP entry-point reproduction before fix | Same paths reveal whether the repo's PHP front controller causes the symptom | Reproduced: health 200, categories HTML, user/products 404. |
| PHP entry-point smoke after fix | API routes dispatch; query and assets continue to work | `/api/user` returns 401 JSON; valid `/api/products?sort=price_asc&per_page=4` and `/api/categories` reach DB code but return 500 because local MySQL reports `SQLSTATE[HY000] [2006] MySQL server has gone away`; invalid query values return 422 JSON, proving query parsing; built JS asset returns 200 with JavaScript MIME type. |
| Focused PHPUnit tests | Auth and catalog API behavior remains correct | Passed: `ApiAuthenticationTest` and `StorefrontProductListingTest`, 12 tests / 68 assertions. PHPUnit emitted a non-fatal warning that the workspace `.phpunit.result.cache` is not writable. |
| PHP style and syntax | Entry point parses and matches repository style | `php -l api/index.php` and `vendor/bin/pint --dirty --format agent` passed. |
| Vercel deployment metadata | Identify active commit and state | Deployment `dpl_3AJqEQPwbi784jt5TTZnr7ZpWerm`, commit `2cfb494`, was listed as Production `READY`; it was not independently fetched. |
| Automated Production API fetch | Read-only API response for current alias | Available Vercel and web-fetch connectors did not authorize/access the project (403/tool inaccessible); no independent HTTP response captured. |
| Root cause / correction / deployment | Correct diagnosis, regression coverage, Preview and Production verification | Not started; plan only. |

## Risks, Dependencies, and Open Questions

- Vercel deployment and log integration access previously returned 403 for this team/project. The owner may need to provide response headers/body and logs or restore integration access for conclusive diagnosis.
- The local request-path fix is not yet deployed. Vercel logs still need to confirm the same request context on the pinned function runtime.
- Aiven availability or schema problems may appear after the routing issue is corrected and must be tracked independently.
- A valid authenticated `/api/user` check requires a browser login/session; never paste cookie values into chat or documentation.
- Preview may have different `APP_URL`, Sanctum domains, database, and deployment protection settings from Production. Document those differences when interpreting test results.

## Implementation Notes and Discoveries

- Local PHP-server reproduction: before the fix, `/api/categories` returned the SPA shell and `/api/user` plus `/api/products` returned route 404s. After setting front-controller server variables to the Laravel public entry point, the guest `/api/user` returned 401 JSON and the catalog routes reached database handling instead of 404. The local MySQL service is unavailable and returned 2006 errors; this is separate from API routing.

## Final Summary and Remaining Work

Plan 017 is in progress. The issue was reproduced locally and traced to Symfony treating the PHP handler's `/api` directory as Laravel's base URL. `api/index.php` now presents the correct `public/index.php` server context, and local smoke checks plus focused auth/catalog tests pass. The change has not been deployed; verify it on Preview before any Production release. Local catalog checks are limited by the unavailable MySQL service, and Vercel log access remains 403.
