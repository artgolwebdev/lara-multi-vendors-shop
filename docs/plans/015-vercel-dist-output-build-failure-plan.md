# 015: Fix Vercel Missing `dist` Output Directory Build Failure

- ID: `015-vercel-dist-output-build-failure`
- Status: in_progress
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: not completed

## Problem and Intended Outcome

The latest Vercel build log reports that the Vite build completed successfully, then fails with `No Output Directory named "dist" found after the Build completed`. The repository deploys a Laravel application through the community PHP runtime and builds Vite assets into Laravel's `public/build` directory; it is not a standalone Vite SPA that emits a root-level `dist` directory.

This plan identifies and corrects the Vercel project/build output configuration so a Preview deployment can complete with the Laravel PHP function, Laravel routes, and generated Vite assets intact. Production deployment and database migration are separate release actions and must wait for Preview validation.

## Scope

### In Scope

- Inspect the failing deployment's commit and Vercel build log around the PHP runtime build, Composer hook, Vite build, and output-directory validation.
- Inspect Vercel Project Settings for framework preset, root directory, build command, and output-directory override.
- Align Vercel's project settings with the repository's Laravel PHP runtime and Vite asset layout.
- Validate a Preview build and confirm that the Laravel function and static assets are included.
- Update deployment documentation and this plan with observed settings and actual results.

### Non-Goals

- Do not create or populate a `dist` directory as a workaround.
- Do not change framework routing, the PHP runtime, or deployment entry point unless build evidence shows they are responsible.
- Do not run Production migrations, seeders, or deploy/promote to Production as part of Preview diagnosis.
- Do not change Aiven credentials, database data, CA material, or object-storage configuration.

## References

- Repository status: [current state](../status/current-state.md), [known issues](../status/known-issues.md), [Vercel deployment preparation](../development/deployment-vercel.md).
- Related deployment investigation: [Plan 014](014-vercel-deployment-investigation-plan.md).
- Repository configuration: `vercel.json`, `composer.json`, `package.json`, `vite.config.js`, and `.vercelignore`.
- User-provided Vercel build log: Vite reports `built in 3.82s`; Vercel then reports missing output directory `dist`.
- Vercel documentation: [Build and Development Settings](https://vercel.com/docs/projects/project-configuration), [Project Configuration](https://vercel.com/docs/project-configuration), and [Vercel PHP runtime](https://github.com/vercel-community/php).

## Existing Implementation Findings

### Verified Repository Facts

- Before this fix, `vercel.json` selected `vercel-php@0.9.0`, set the PHP function duration, and routed all request paths to `/api/index.php`, but did not define `framework` or `outputDirectory`.
- `composer.json` defines a `vercel` script that runs Laravel package discovery, `npm ci`, `npm run build`, then `php artisan deployment:migrate --no-interaction`.
- `vite.config.js` uses `laravel-vite-plugin`; Vite's build is integrated with Laravel and its normal production output is `public/build` (including `manifest.json`), not root `dist`.
- The user-provided log says the Vite build succeeded before Vercel rejected the missing `dist` output directory. It does not establish whether the migration hook ran, which commit was built, or whether Vercel settings explicitly set `dist`.
- `.vercelignore` does not exclude `public/build`; generated assets are expected to be produced by the Vercel build.

### Inference to Verify

The failure is consistent with Vercel applying a Vite/static output-directory expectation (`dist`) to this Laravel PHP-function project, likely through the Framework Preset or an Output Directory override. The repository files alone cannot prove the active dashboard setting or runtime output contract.

### Unknowns

- Which commit and environment produced the supplied build log.
- Whether the Framework Preset is Vite, Other, or another selection.
- Whether Output Directory Override is enabled and set to `dist`.
- What build/install commands and Root Directory are currently configured in Vercel.
- Whether the PHP runtime's builder completed Composer installation and invoked the `vercel` script, and whether the migration command reached Aiven before the output check failed.

## Proposed Design

### Affected Components

- Vercel Project Settings: Framework Preset, Root Directory, Install Command, Build Command, and Output Directory.
- `vercel.json`: change only if the deployed runtime/build evidence demonstrates a repository-level configuration mismatch. Do not add `outputDirectory: "dist"` or manufacture a `dist` artifact.
- `composer.json` and `vite.config.js`: preserve the existing Laravel-managed asset build unless evidence shows the runtime does not invoke it as expected.
- `docs/development/deployment-vercel.md`, this plan, `docs/status/current-state.md`, `docs/status/known-issues.md`, `docs/status/changelog.md`, and `docs/plans/backlog.md`: record actual findings and release status.

### Build Configuration Target

For a Laravel app deployed as a Vercel PHP function, configure the project as a non-static/custom runtime project (Vercel Framework Preset `Other`/no framework autodetection) and select Laravel's `public/` as the output directory. The repository now sets `framework: null` and `outputDirectory: "public"` in `vercel.json`, which override Vercel's detected framework and dashboard output-directory setting for the deployment. Keep the project Root Directory at the repository root unless the Vercel project is intentionally configured as a monorepo subdirectory. Let the pinned PHP runtime's documented build integration invoke the Composer `vercel` script once; do not add a second dashboard build command that duplicates the same Composer/npm build.

Do not assume this target is correct until Plan 015.1 confirms the active project settings and the pinned runtime's build/output behavior. If the runtime requires a specific output convention, follow its pinned-version documentation and captured build evidence rather than treating this as a static Vite deployment.

### Migration Safety

The Composer hook currently runs `deployment:migrate` after `npm run build` and before Vercel's final output validation may finish. Inspect the full log to establish whether that command ran. For Preview validation, confirm its `VERCEL_ENV` guard skips database migrations. Do not retry a Production deployment until the migration status and build side effects are understood; the earlier failed build may still have applied migrations if its hook ran.

## Implementation Tasks

- [ ] **015.1 — Capture failing deployment and project settings.** Identify the failing deployment's commit, target environment, framework preset, root directory, install/build commands, and Output Directory override. Obtain the complete relevant build log. Confirm whether the Composer migration command ran and capture only sanitized status/output. No source changes or Production requests. Acceptance: a concrete cause is supported by deployment evidence.
- [x] **015.2 — Correct the repository output/build mismatch.** Set `framework: null` and `outputDirectory: "public"` in `vercel.json` to override Vercel's Vite preset and missing `dist` expectation while preserving the existing function route and Laravel `public/build` assets. Local JSON validation and Vite asset build pass. The next Preview build must still verify Vercel's effective runtime behavior.
- [ ] **015.3 — Validate deployed behavior.** Confirm deployment status is Ready; check `/up`, a JSON API response with `Accept: application/json`, a Laravel-rendered page, and at least one Vite CSS/JS asset. Confirm migration behavior and that no secret/debug trace is exposed. Acceptance: requests reach Laravel as expected and generated assets return successfully.
- [ ] **015.4 — Update deployment records and decide Production readiness.** Record exact settings and build evidence in deployment docs, current state, known issues, changelog, backlog, and this plan. Keep Production blocked until the Preview behavior, `APP_DEBUG=false`, Aiven connection, and migration state are verified. Acceptance: docs distinguish verified results from unknowns and contain no credential values.

## Acceptance Criteria

- [x] Vercel no longer expects a nonexistent root-level `dist` directory for the Laravel PHP deployment; the corrected Production deployment reached Ready.
- [ ] The deployment serves the pinned PHP function and generated Laravel Vite assets; runtime checks are pending.
- [ ] Laravel health/API requests are routed to PHP, and static asset URLs work.
- [ ] Preview build logs demonstrate that the Production-only migration command was skipped.
- [ ] The previous failed Production build is checked for migration side effects before another Production build is permitted.
- [ ] Deployment docs and backlog reflect the verified outcome without claiming an unobserved production fix.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| Initial repository configuration inspection | Check the committed runtime declaration and output path | Before the correction, `vercel.json` had no `framework` or `outputDirectory`; Vite uses `laravel-vite-plugin`. |
| Repository configuration correction | Force Other preset and use the existing Laravel `public/` output | `vercel.json` parsed; framework is null, output directory exists, and PHP runtime/catch-all route remain configured. |
| Vite production build | Generate Laravel Vite manifest/assets inside the configured output directory | Passed locally: `npm run build`; manifest and CSS/JS assets generated under `public/build/`. Existing Sass/Tailwind/Lightning CSS warnings remain. |
| Supplied Vercel build log | Build/output failure is recorded accurately | Vite build succeeds; Vercel reports missing `dist`. Full deployment details not yet available. |
| Vercel Preview build | Successful build and output validation | Not run. |
| Production deployment for commit `b3283de` | Build reaches Ready without the missing-`dist` error | Vercel reports deployment `dpl_JDJjUB9RkLezhKzoqsyMGEtumu1L` as `READY`; runtime logs unavailable. |
| Preview health/API/assets smoke check | Laravel and built assets respond successfully | Not run. |
| Migration side-effect check | Establish whether the failed build invoked the Production migration command | Unknown; inspect full deployment log before retrying Production. |

## Risks, Dependencies, and Open Questions

- Vercel build logs for deployment `dpl_JDJjUB9RkLezhKzoqsyMGEtumu1L` returned 403 to the connected account, so migration output cannot be confirmed from this workspace.
- A failed deployment build can still have external side effects if a command ran before the final output validation. In this project, that could include a Production migration.
- Framework autodetection, explicit Output Directory settings, and custom runtime output semantics can change independently of repository contents. Verify against the active Vercel project and pinned runtime.
- Preview authentication may prevent unauthenticated smoke tests; use an authorized testing method and do not disable deployment protection solely to make the check pass.
- The Vercel project may need an Output Directory override removed in the dashboard; a repository-only commit may not fix a dashboard-level override.

## Implementation Notes and Discoveries

- Initial investigation confirms a distinction between Vite's successful asset build and Vercel's later output-directory check. The log does not indicate a Vite compilation failure.
- Repository config changes were committed and pushed as `b3283de`. Vercel reports the resulting Production deployment Ready; the build-log endpoint denied access, and deployed route/asset smoke checks are outstanding.

## Final Summary and Remaining Work

Repository correction implemented and pushed as `b3283de`. Vercel reports Production deployment `dpl_JDJjUB9RkLezhKzoqsyMGEtumu1L` as Ready, and the missing-`dist` build error did not recur. Complete tasks 015.1 and 015.3–015.4 to verify project settings, migration outcome, Laravel routes, and asset delivery before declaring the deployment fully validated.
