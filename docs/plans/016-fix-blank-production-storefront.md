# 016: Fix the Blank Production Storefront After a Successful Deployment

- ID: `016-blank-production-storefront`
- Status: in_progress
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: not completed

## Problem and Intended Outcome

The Vercel Production deployment can reach Ready and return Laravel-rendered storefront HTML, but the browser shows a blank white page. A live browser check found that the HTML references Vite JavaScript and CSS files under `/build/assets/`, and both URLs return `Not Found`. The page contains an empty `#store-app`, so React cannot mount when its JavaScript bundle is unavailable.

Diagnose and correct the Vercel deployment/routing path so the exact generated Vite assets are included in the deployment and served successfully, Laravel routes and APIs continue to reach PHP, and the production storefront renders in a browser. Independently verify catalog API behavior so an asset repair does not hide a separate database failure.

## Scope

### In Scope

- Inspect the production HTML, its Vite asset references, built manifest, deployment output, and Vercel request routing.
- Verify the pending filesystem-aware rewrite candidate in `vercel.json` and determine whether Vercel serves `public/build` files from the configured `public/` output.
- Make a focused routing/output correction if deployment evidence shows the candidate is insufficient.
- Verify storefront rendering, assets, direct route refreshes, and public API responses after a successful deployment.
- Update deployment and status documentation with observed production results.

### Non-Goals

- Do not redesign the storefront or change its React components as a response to missing static files.
- Do not change Aiven credentials, database contents, migrations, or seeders as part of the asset-routing fix.
- Do not disable Vercel Deployment Protection to enable tests.
- Do not claim the page is fixed solely because Vercel reports a deployment as Ready.

## References

- Current deployment status: [current state](../status/current-state.md), [known issues](../status/known-issues.md), and [Vercel deployment guide](../development/deployment-vercel.md).
- Related work: [Plan 014](014-vercel-deployment-investigation-plan.md) and [Plan 015](015-vercel-dist-output-build-failure-plan.md).
- Follow-up API routing investigation: [Plan 017](017-fix-production-api-404s-plan.md).
- Application entry points: `vercel.json`, `api/index.php`, `resources/views/store-app.blade.php`, `resources/js/app.js`, `resources/js/components/store/StoreApp.jsx`, and `vite.config.js`.
- Build output: `public/build/manifest.json` and files under `public/build/assets/` (generated during Vercel build; not committed).
- Vercel routing reference: [Project Configuration (`vercel.json`)](https://vercel.com/docs/project-configuration/vercel-json).

## Existing Implementation Findings

### Verified Facts

- Production root HTML loads and sets the title `Laravel — Small shops, good things`.
- The returned HTML references `/build/assets/app-CKhJFyC7.js` and `/build/assets/app-C2scsCq6.css` and contains an empty `<div id="store-app"></div>`.
- The user-provided Production browser console reports HTTP 404 for both referenced bundle URLs and for `/favicon.ico`. Earlier read-only browser navigation to each Vite bundle URL also returned `Not Found`.
- The tracked `public/favicon.ico` file is zero bytes in this workspace, so its 404 is not sufficient evidence of the same publication failure; it is an empty placeholder and is not a valid icon.
- Locally, `public/build/manifest.json` references those same filenames and both files are present in the build output. `public/build` is generated and ignored by Git; Vercel must generate and publish it during its build.
- `vercel.json` uses a `rewrites` catch-all to `/api/index.php` instead of the previous `routes` catch-all. This configuration and the PHP asset-serving change were pushed in commit `2cfb494`; its Production deployment reached `READY`. The reported API 404s show that Ready does not establish Laravel API behavior.
- The pinned `vercel-php@0.9.0` runtime README states that files created by the Composer `vercel` script can be used by PHP lambdas but are not automatically accessible to browsers. This app creates Vite assets during that Composer hook. That runtime behavior is the leading explanation for the 404s.
- `api/index.php` previously returned `false` for existing public files, a PHP built-in-server static-file passthrough convention. A serverless invocation needs to emit the file body itself. The entry point now streams an allowlisted file for GET/HEAD requests and retains Laravel fallback for other paths.
- The Vercel runtime-log and deployment-fetch integrations returned HTTP 403 for the connected account, so production build artifacts and server logs could not be inspected through those tools.
- The deployed HTML response proves PHP rendered a Blade page, but it does not prove the frontend bundle or the storefront's catalog API works.
- After the follow-up commit `2cfb494` reached Production `READY`, the user reported browser console requests originating from `app-CKhJFyC7.js` for `/api/user` and `/api/products?sort=price_asc&per_page=4`, both with HTTP 404. This confirms the JavaScript is now loading and executing far enough to make requests; CSS delivery and visible rendering have not been independently confirmed. The API 404s are tracked in Plan 017.

### Inferences to Verify

- The missing JS bundle is sufficient to explain the empty React mount and visible white page. The runtime README supports the hypothesis that Composer-hook-generated assets are available inside the PHP function bundle but are not automatically published as browser-facing static files.
- The catch-all rewrite routes static misses into the PHP function, where the new file-serving branch can read generated assets bundled with that function. Deployed verification is still required.
- The favicon's zero-byte source is a separate content defect; it is not the primary signal for this fix.

### Unknowns

- Whether the current Production deployment serves the CSS bundle and all manifest assets successfully.
- Whether the storefront visibly renders after the JavaScript bundle executes.
- Whether an asset 404, if any remain, comes from Vercel's static layer or PHP; authorized response headers/logs are needed.
- Whether catalog APIs work after the separate routing issue in Plan 017 is corrected.
- Whether direct refreshes on nested storefront routes continue to return the Laravel host page after route changes.

## Proposed Design

### Affected Components

- `vercel.json`: preserve the `Other` preset, `public/` output directory, PHP runtime declaration, and catch-all rewrite so static misses reach the function.
- `composer.json`, `package.json`, `vite.config.js`: inspect the existing Composer build hook and Vite output path; change only if logs or the deployment manifest prove the build is not emitting assets into `public/build`.
- `api/index.php`: serve only real files beneath `public/` with an extension allowlist and explicit content types for GET/HEAD; continue booting Laravel for other paths.
- `docs/development/deployment-vercel.md`, Plan 015, and deployment status docs: record the verified fix and test results.

### Database

No schema or data changes are planned. After assets render, make a read-only request to the storefront catalog API and record whether it returns the expected JSON. If it fails, track the Aiven/database issue separately instead of changing database state in this task.

### API / Routes

- Static generated assets under `/build/assets/*` must return successful responses with JavaScript/CSS content types and the exact bytes for the deployed manifest filenames.
- Laravel web paths such as `/`, `/shop`, `/categories`, and `/products/{id}` must still return the SPA host HTML.
- API paths such as `/api/products` must still reach Laravel and return JSON, not static fallback HTML.
- Verify function-bundled files can be streamed by the PHP entry point; do not rely only on Vercel's static output directory or local PHP development-server behavior.

### Frontend

- Verify the `@vite`-generated asset URLs match the deployed `public/build/manifest.json`.
- Confirm the JS module executes, React mounts into `#store-app`, and the storefront produces visible page content.
- Capture browser console errors and inspect catalog requests after mounting.

### Security

- Keep `APP_DEBUG=false` in Production; do not expose stack traces or environment values during smoke checks.
- Use read-only GET requests for deployment verification. Do not run migrations, seeders, or write operations as part of this plan.
- Do not bypass Vercel Authentication or Deployment Protection. Obtain authorized access/log visibility if required.

## Implementation Tasks

- [x] **016.1 — Confirm current Production behavior.** User-provided browser evidence and earlier browser inspection confirm Laravel HTML loads, its JS/CSS bundle URLs return 404, and React's mount remains empty. Current Vercel deployment ID/commit and protected response logs remain unavailable (403).
- [x] **016.2 — Verify local build and runtime contract.** `npm run build` succeeds and emits the manifest-referenced JS/CSS files. The official `vercel-php@0.9.0` README confirms Composer-hook-created files can be used inside PHP lambdas but are not automatically browser-accessible. `.vercelignore` does not exclude `public/build`.
- [x] **016.3 — Implement serverless asset response.** `api/index.php` streams allowlisted files found beneath `public/` for GET/HEAD requests, rather than returning `false` for PHP development-server passthrough. Local PHP-server smoke checks returned 200 with expected JavaScript/CSS MIME types and nonzero bundle sizes; `/up` remained 200. Vercel deployment verification is still pending.
- [ ] **016.4 — Verify deployed assets and storefront rendering.** Production user evidence now shows the JS bundle executes and requests the APIs; still confirm CSS and all required manifest assets return successfully, the page visibly renders, and storefront routes survive direct navigation/refresh. Vercel response/log access is required for independent confirmation.
- [ ] **016.5 — Link API verification to Plan 017.** Recheck `/api/products` and `/api/categories` after the Plan 017 routing correction. Confirm JSON responses and distinguish any Aiven/database failure. Do not seed or mutate production data.
- [ ] **016.6 — Update deployment records.** Record the exact deployed commit, deployment state, asset status, browser result, API result, and any unavailable checks in this plan, deployment guide, current state, known issues, changelog, and backlog. Mark complete only after the storefront is visibly rendered in the deployed environment.

## Acceptance Criteria

- [ ] Vercel deployment reaches Ready and includes every asset named in its Vite manifest.
- [ ] Referenced JS and CSS assets return HTTP 200 with correct content types from the production domain.
- [ ] React mounts and the storefront displays visible content without an uncaught browser startup error.
- [ ] Storefront paths work on direct navigation and refresh while Laravel API paths continue to return JSON.
- [ ] Catalog API behavior is reported separately from asset loading, with no unverified claim that Aiven is healthy.
- [ ] Production debug output and credentials remain private; no migrations or seeders run during smoke verification.
- [ ] Deployment docs distinguish a Ready build from successful browser/API verification.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| Production root HTML | Laravel SPA shell includes Vite bundle URLs and `#store-app` | Confirmed via browser; React mount was empty. |
| Production JS asset | Successful response for `/build/assets/app-CKhJFyC7.js` | Earlier check returned `Not Found`; the later user console shows execution from this bundle after commit `2cfb494`, but its HTTP response was not independently captured. |
| Production CSS asset | Successful response for `/build/assets/app-C2scsCq6.css` | `Not Found` at time of investigation. |
| Production favicon | A valid, nonempty icon is served | User-provided console reports HTTP 404. Local `public/favicon.ico` is zero bytes, so this is a separate placeholder defect and not a valid test of the generated-bundle issue. |
| Local Vite manifest/output | Manifest names match generated files | Present in local `public/build`; deployment inclusion remains unknown. |
| Local PHP function-style static handling | Generated files stream with correct content type; Laravel routes still fall back | Passed with PHP 8.4.23 built-in server and `api/index.php`: JS 200/462,792 bytes; CSS 200/52,020 bytes; `/up` 200. |
| Vercel Preview static handling | Generated files stream from the deployed PHP function bundle | Not yet run. |
| Production browser render | Visible storefront and no uncaught startup errors | Still unverified. User reports `/api/user` and `/api/products?sort=price_asc&per_page=4` returning 404 from the executing bundle; API investigation is tracked by Plan 017. |
| Catalog API | `/api/products` and `/api/categories` return expected JSON | Production browser check showed `/api/categories` returning the storefront HTML and the user reported `/api/products` 404. The local PHP-entrypoint cause and correction are tracked in Plan 017; deployed verification remains pending. |
| Vercel build/deployment logs | Confirm config revision, build output, and deployed files | Vercel APIs returned 403 to the connected account. |

## Risks, Dependencies, and Open Questions

- Fixing static delivery may reveal a separate catalog API or Aiven database error; these are distinct boundaries and need separate diagnoses.
- The rewrite and PHP static-serving changes were pushed in commit `2cfb494`, whose Production deployment reached `READY`. The user-observed JS execution is evidence the asset path improved, but Vercel-side asset response and visible storefront rendering remain unverified.
- The Vite build output is ignored by Git, so successful deployment depends on Vercel invoking the build hook and publishing the generated output.
- Vercel project permissions currently block access to deployment details and logs. If this persists, verification depends on the owner providing logs or enabling the proper project integration access.
- Production alias may move to another deployment while this plan is in progress; record the actual deployment ID/commit at verification time.

## Implementation Notes and Discoveries

- Initial live check: root HTML loads; JavaScript and CSS files named in the HTML both return `Not Found`; React therefore has no client bundle to mount. The reported favicon 404 is a separate issue because the local favicon is empty.
- Runtime documentation establishes that Composer-hook output is bundled for PHP lambda access but is not directly published as browser assets. The local fix streams these files through `api/index.php`, reached by the catch-all rewrite. A successful local smoke check is not a substitute for Preview verification.

## Final Summary and Remaining Work

The serverless static-file response is implemented and verified locally. Plan remains in progress until a Preview deployment confirms the function bundle contains the generated assets, the browser renders the storefront, and the read-only catalog APIs respond as expected.
