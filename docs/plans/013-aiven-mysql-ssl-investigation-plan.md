# 013: Aiven MySQL SSL Integration Investigation

- ID: `013-aiven-mysql-ssl-investigation`
- Status: in_progress
- Created: 2026-10-11
- Updated: 2026-10-11
- Completed: not completed

## Problem and Intended Outcome

The application is prepared to use Aiven MySQL from Vercel, but no connection from a deployed function has been verified. This plan audits the existing TLS setup and defines a safe sequence to prove that Laravel connects to the intended Aiven service with certificate verification enabled, without exposing credentials or changing production data unexpectedly.

## Scope

### In Scope

- Audit Laravel's MySQL connection, environment-variable precedence, PDO TLS options, Vercel PHP runtime and artifact contents.
- Validate the supplied CA file locally without printing its contents; distinguish PEM parsing from CA trust and live server verification.
- Confirm Vercel and Aiven network access, environment scoping, TLS verification, health checks, and a safe database rollout procedure.
- Implement only the minimal corrections justified by those checks after this plan is approved for implementation.

### Non-Goals

- Do not reveal or copy credentials, connection URIs, or certificate contents into logs or docs.
- Do not disable TLS, peer verification, or use an insecure fallback.
- Do not deploy, run migrations, seed, reset, or otherwise mutate production data during the read-only audit phase.
- Do not select or implement image storage; Plan 012 records that object storage is deferred.
- Do not add a database package or change application architecture unless the audit proves it necessary.

## References

- Requirements: user-provided task brief, “Audit and Plan Aiven MySQL SSL Integration for Laravel on Vercel”.
- Architecture: [current state](../status/current-state.md), [deployment preparation](../development/deployment-vercel.md), [Plan 012](012-vercel-aiven-production-deployment-plan.md).
- Decisions: none recorded.
- Primary technical references: [Aiven PHP/PDO connection guide](https://aiven.io/docs/products/mysql/howto/connect-with-php), [Aiven TLS certificates and rotation](https://aiven.io/docs/platform/concepts/tls-ssl-certificates), [vercel-php runtime](https://github.com/vercel-community/php), [Vercel function runtimes](https://vercel.com/docs/functions/runtimes), and [Vercel function limits](https://vercel.com/docs/functions/limitations).

## Existing Implementation Findings

### Verified facts

- `composer.json` requires PHP `^8.3` and Laravel `^13.17`; installed Laravel is 13.35.0. The local XAMPP CLI is PHP 8.4.23.
- `vercel.json` selects `vercel-php@0.9.0`. The runtime project documentation currently lists PHP 8.5.x for 0.9.0 and includes `openssl`, `PDO`, and `pdo_mysql`; the project's own `composer.json` therefore permits the runtime's PHP 8.5 version. Runtime behavior still requires an actual Vercel build check.
- Local PHP reports `pdo_mysql` and `openssl` loaded, and exposes `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT`.
- The `mysql` connection in `config/database.php` uses Laravel's `DB_*`/`DB_URL` settings. When `pdo_mysql` is loaded, it supplies `Pdo\Mysql::ATTR_SSL_CA` from `MYSQL_ATTR_SSL_CA` and sets `Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT` to `true`. The default connection remains SQLite unless `DB_CONNECTION` selects MySQL.
- `api/index.php` configures `MYSQL_ATTR_SSL_CA` before loading Laravel. Precedence is non-empty `AIVEN_CA_PEM_B64` written to `/tmp`, then a configured `MYSQL_ATTR_SSL_CA` path, then `storage/certs/ca.pem`. On Vercel it returns an error if no certificate path resolves. It does not print the certificate or database credentials.
- `storage/certs/ca.pem` exists and is readable in the checked-out repository. PHP OpenSSL parses it as a certificate, reports `CA:TRUE`, and says it is currently within its validity period according to the local system clock. This does not establish that it is the current Aiven project CA, that it chains to the Aiven server certificate, or that the server hostname is verified.
- `.vercelignore` excludes local mutable storage directories but does not exclude `storage/certs/ca.pem`. The file was included in commit `4674fae`, so it is present in the current Git deployment source. Confirm the actual Vercel artifact during Preview.
- `.gitignore` does not ignore `storage/certs/ca.pem`. The certificate is public trust material, but must be refreshed when Aiven rotates the project CA. Never commit private keys or credentials beside it.
- `composer.json` has a `vercel` build script for package discovery, `npm ci`, and `npm run build`; it does not currently run `config:cache`. Do not introduce build-time caching of Production secrets. Verify Vercel build settings and artifact behavior directly.
- Existing tests cover database-backed application features, but no dedicated Aiven TLS integration test or Vercel health/connectivity check was found in the inspected suite. The `/up` route is Laravel's basic health endpoint and does not by itself prove database connectivity.
- No Vercel deployment or remote Aiven connection was performed for this audit. Aiven network allowlists, service state, user grants, Vercel environment names/scopes, and database contents remain unknown.

### Inferences requiring validation

- The current PDO CA path plus `ATTR_SSL_VERIFY_SERVER_CERT=true` is intended to verify the certificate, but the effective behavior depends on the deployed PHP/mysqlnd build and Aiven's current CA and endpoint configuration. Confirm using the runtime itself; do not infer a successful secure connection from local PEM parsing.
- Vercel's PHP runtime is community maintained. Current published runtime information lists PHP 8.5.x and required PDO MySQL/OpenSSL extensions for `vercel-php@0.9.0`; this is a documented expectation, not a successful build of this repository.
- Laravel resolves configuration into its configuration repository at bootstrap. `api/index.php` sets the CA environment before `public/index.php`, which is appropriate for uncached config. If a cached config artifact is introduced, verify it cannot embed stale/local database or CA settings.

### Unanswered questions

- Which Aiven service region and hostname/port are configured, and does the Aiven service accept connections from the deployed Vercel function region/network?
- Are all required Vercel variables named and scoped as Laravel expects (`DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)? Are previews isolated from Production?
- Is the checked-in CA the current Aiven project CA and a CA certificate valid for the service? What is the rotation schedule?
- Does the Production database contain user data, and have the required Laravel migrations already been applied?
- Does the production Aiven user have only the required privileges, and is Aiven's connection limit adequate for serverless concurrency?

## Proposed Design

### Affected Components

- `config/database.php`: retain Laravel's MySQL connection and verified TLS options unless deployed runtime tests demonstrate a specific incompatibility. If a correction is needed, keep TLS and peer verification mandatory and test it locally and on Preview.
- `api/index.php`: keep the current CA resolution and fail-closed behavior; consider only narrowly scoped diagnostic or validation improvements that do not reveal secrets or certificate contents.
- `storage/certs/ca.pem` and `.vercelignore`: retain the public CA file in the artifact and document a controlled refresh process. Do not add private keys.
- `tests/Feature` or `tests/Unit`: add deterministic tests for configuration and missing/invalid certificate behavior where those paths can be tested without a live secret-bearing database.
- `docs/development/deployment-vercel.md`, `docs/status/current-state.md`, and this plan: record only verified environment names/scopes and connection outcomes, never values.

### Database

- Production must use the existing Aiven MySQL database through Laravel's `mysql` driver. Keep credentials in Vercel environment variables.
- Match Aiven's TLS mode to the PDO MySQL options and downloaded project CA. Require server certificate verification. Do not set an option that disables verification to make a failing connection pass.
- Use read-only connectivity checks first. Before any schema change, inspect current tables and migration status, establish whether data must be preserved, and take or confirm a restorable Aiven backup.
- Apply only reviewed forward migrations from a controlled release environment with `php artisan migrate --force`. Never run `migrate:fresh`, seeders, or destructive commands against Production.

### Environment Variables

| Name | Local Windows/XAMPP | Vercel Preview/Production |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` when intentionally testing against MySQL; otherwise keep local SQLite | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Use a separate local or staging Aiven service; never paste values into docs or source | Set per environment in Vercel; verify names and scope without copying values out |
| `MYSQL_ATTR_SSL_CA` | Absolute path to the current Aiven CA file when testing a remote Aiven connection | Optional explicit CA path; the entry point otherwise resolves the bundled `storage/certs/ca.pem` |
| `AIVEN_CA_PEM_B64` | Usually unset when the CA file is available locally | Optional override for the CA file; store as a Vercel secret and do not print it |
| `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_URL` | Local-only values in ignored `.env` | Production values per Vercel environment; `APP_DEBUG=false` and stable key |

The entry point's CA precedence is `AIVEN_CA_PEM_B64`, then a resolvable `MYSQL_ATTR_SSL_CA`, then the bundled CA. Do not set multiple sources unless the override precedence is intentional. Keep Preview isolated from Production data and credentials.

### SSL Verification Requirements

- Require TLS and verify the peer certificate against the current Aiven project CA. The deployed PHP/mysqlnd runtime must demonstrate the behavior; local OpenSSL parsing alone is not proof.
- Confirm the hostname verification behavior supported by the exact PDO MySQL runtime and Aiven endpoint configuration. If hostname identity cannot be established with the current PDO options, document the residual risk and choose a supported secure client configuration before Production rollout.
- Missing, unreadable, malformed, expired, or untrusted CA configuration must fail closed. Never retry without SSL or turn off peer verification.
- Test a deliberately invalid CA in a controlled Preview to show that verification rejects the connection; never run this negative test against a production user flow.

### API / Routes

- No public migration or diagnostic endpoint is proposed. Use an authenticated/controlled Preview invocation or a one-off release command that performs a minimal read-only query and reports only success/failure plus a request identifier.
- Keep `/up` as an application health check; separately verify database access because `/up` alone may not establish a database connection.

### Frontend

- No frontend changes are needed for database TLS.

### Security

- Keep DB credentials and optional `AIVEN_CA_PEM_B64` in Vercel environment settings; never use `NEXT_PUBLIC_`/`VITE_` prefixes or log configuration dumps.
- Do not log connection URIs, passwords, raw exception DSNs, or certificate contents. Redact exception output and share only sanitized diagnostic codes/messages.
- Limit database privileges to those required by the application; use a separate migration identity if available and operationally practical.
- Validate preview and production variables separately. Do not point Preview at Production unless explicitly approved and safely read-only.
- Track CA validity and Aiven rotation notices; update the public CA artifact before rotation takes effect, then verify a Preview connection.

### Local Testing Strategy (Windows/XAMPP)

1. Confirm the active `php.exe` is the expected XAMPP PHP and that `pdo_mysql` and OpenSSL are loaded; record versions only.
2. Use a separate local/staging Aiven database and a local ignored `.env`. Set `MYSQL_ATTR_SSL_CA` to the absolute Windows path if not using the repository CA.
3. Run a short read-only `SELECT 1` using Laravel/PDO, then a read-only application query. Do not echo environment values or full PDO exceptions.
4. Confirm a missing/incorrect CA fails; do not disable verification as a workaround. Avoid `config:cache` while changing credentials and CA paths; if config cache is introduced, clear/rebuild it using the intended environment and verify no local values are embedded.

### Deployment Testing Strategy (Vercel)

1. Set Preview-only variables and deploy the Preview build; check runtime PHP version/extensions and confirm the CA file is in the function artifact.
2. Invoke a controlled read-only database check from the deployed function and inspect sanitized logs. `/up` alone is insufficient evidence of database connectivity.
3. Verify an invalid CA is rejected in a controlled Preview, confirm HTTPS/proxy behavior, and record latency/error category without secrets.
4. Review Aiven access controls, service status/region, database grants, and connection capacity. Align function region with Aiven where the chosen Vercel plan supports it.
5. Only after Preview passes, configure Production scopes, verify the existing database and backup, and schedule reviewed migrations separately from the build/deploy step.

### Migration Considerations

- First inspect the current schema, `migrations` table, and whether Production contains data. The status snapshot says prior migrations ran in the configured MySQL environment, but it does not prove those were the user's Aiven Production database.
- Review every pending migration against MySQL behavior and rehearse it on a disposable/staging Aiven database. Capture and confirm a restorable backup before Production changes.
- Execute only the reviewed forward set through a controlled release runner using the intended Aiven credentials and `php artisan migrate --force`. Do not invoke the `composer setup` script, `migrate:fresh`, or seeders in Production.
- Record migration output with secrets redacted and verify the final migration status read-only.

## Implementation Tasks

The audit tasks below are read-only. Any code correction discovered during the audit must be proposed in a follow-up implementation change and must not be applied as part of the read-only audit.

- [x] **013.1 — Verify local config and certificate facts.** Files: `config/database.php`, `api/index.php`, `storage/certs/ca.pem`, `.gitignore`, `.vercelignore`. Required: inspect driver defaults, PDO option availability, CA path precedence and artifact rules; run format/readability checks without displaying certificate contents. Depends on: none. Verification: `composer show --direct`; `composer check-platform-reqs`; `php -v`; `php -m`; PHP OpenSSL parse, CA basic-constraints, and current-validity checks; PDO SSL option check. Completion: findings recorded with PEM parsing distinguished from CA trust.
- [ ] **013.2 — Verify Vercel runtime and build compatibility.** Files: `vercel.json`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`. Required: compare configured runtime, PHP requirements, extensions, Node version, Composer scripts, and deployment bundle with current runtime documentation; run the build in a Preview or equivalent without Production secrets. Depends on 013.1. Verification: Vercel build logs and sanitized artifact-size/runtime details. Completion: Laravel boots under the deployed PHP runtime and required extensions are present, or a concrete incompatibility is documented.
- [ ] **013.3 — Confirm Aiven and Vercel environment/network settings.** Files: no repository files unless documentation needs a sanitized update. Required: inspect variable names and environment scopes in dashboards without copying values; confirm Aiven service state, database, endpoint/port, access controls, user grants, and selected regions. Depends on 013.2. Verification: dashboard review and approved read-only connectivity check. Completion: safe Preview-to-Aiven access is confirmed, or the exact external blocker and owner action are recorded.
- [ ] **013.4 — Prove TLS verification in Preview.** Files: `api/index.php`, `config/database.php`, `docs/development/deployment-vercel.md` only if a verified correction or result needs recording. Required: use the bundled/current CA; make one read-only PDO/Laravel query; confirm verification rejects an invalid CA or hostname where the driver supports that test. Never disable verification. Depends on 013.1–013.3. Verification: sanitized Preview function logs and read-only query result. Completion: deployed request connects to the intended Aiven host with TLS verification enabled and no secret values in logs.
- [ ] **013.5 — Review migration state and production release safety.** Files: relevant migration files, deployment docs, release workflow if separately approved. Required: inspect migration status and schema without changing data, review SQL for MySQL compatibility, confirm backup/restore and execution identity. Depends on 013.3–013.4. Verification: read-only schema/status review and a disposable or staging migration rehearsal. Completion: a reviewed, reversible release sequence exists; Production migration is a separately approved operation.
- [ ] **013.6 — Close audit and publish verified findings.** Files: this plan, `docs/development/deployment-vercel.md`, `docs/status/current-state.md`, `docs/status/known-issues.md`, `docs/status/changelog.md`, `docs/plans/backlog.md`. Required: mark only completed evidence, summarize remaining gaps, and update the backlog. Depends on 013.1–013.5. Verification: links and git diff checks. Completion: documentation states what was actually tested and identifies any unresolved external blocker.

## Acceptance Criteria

- [ ] Laravel connects to the intended Aiven MySQL database using the correct environment variable names and environment scope.
- [ ] TLS is required and server certificates are verified against the intended current Aiven CA; no insecure fallback is present.
- [ ] The CA file is readable in the deployed artifact at the configured path, and certificate contents are never printed in reports or logs.
- [ ] Missing, malformed, or untrusted certificate configuration fails safely without silently disabling verification.
- [ ] Local Windows/XAMPP behavior and the deployed Vercel PHP runtime are separately verified; local success is not treated as remote proof.
- [ ] A sanitized Preview read-only query succeeds, and an invalid certificate test fails as expected where the PDO/mysqlnd runtime supports a controlled negative test.
- [ ] Existing relevant tests pass; any new tests cover deterministic config/bootstrap behavior without embedding production credentials.
- [ ] No migration or other production data mutation is run without reviewed SQL, confirmed backup, and explicit release approval.
- [ ] Deployment logs and committed docs contain no secrets, full connection URIs, or certificate contents.

## Testing Strategy and Results

| Check | Expected result | Actual result |
| --- | --- | --- |
| Installed dependency and PHP version inspection | Confirm Laravel/PHP constraints and current local versions | PHP 8.4.23; Laravel 13.35.0; `composer show --direct` completed |
| Local PDO/OpenSSL capabilities | PDO MySQL, OpenSSL, and server-certificate option are available | `pdo_mysql`, `openssl`, and `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` reported present |
| CA file check | File is readable, parses as PEM, is marked as a CA, and is within its validity window without printing contents | Readable; PHP OpenSSL parse passed; `CA:TRUE` and current validity reported. Chain trust and Aiven server identity remain unverified |
| Runtime documentation review | Identify expected PHP version and extensions | `vercel-php@0.9.0` documentation lists PHP 8.5.x and PDO MySQL/OpenSSL; repository build not run here |
| Preview endpoint routing | Confirm Preview's Laravel health and JSON catalog endpoints are served by the function | Read-only GETs to `/up` and `/api/categories` returned HTTP 200 with `text/html`; `/api/categories` should be JSON. The cause is unknown, so Laravel routing and DB connectivity are unverified |
| Deployed Vercel build and boot | Laravel boots with supported PHP/extensions and the CA included | Not run |
| Preview Aiven TLS/read query | Verified TLS connection and minimal read-only query succeed | Not run; Vercel/Aiven dashboards and secrets were not accessed |
| Invalid-CA negative check | Certificate verification rejects an untrusted CA | Not run |
| Schema/migration review | Existing data is preserved; migration plan is reviewed before release | Not run; no database mutation was performed |

## Risks, Dependencies, and Open Questions

- Live access requires user-controlled Vercel and Aiven dashboard settings; none are visible in this repository. Credential values must not be requested in chat or printed to logs.
- A PEM parser accepting a file does not prove the certificate is the current Aiven project CA, is within its validity period, or chains to the server certificate.
- Vercel serverless concurrency can create multiple database connections and exhaust Aiven connection limits. Check measured concurrency and service limits before launch; consider Aiven connection pooling if needed.
- Vercel function egress may be blocked by Aiven access controls or incompatible with the configured region. Test from the actual deployed runtime.
- `vercel-php` is community-maintained. PHP 8.5 compatibility with this dependency lock and deployment toolchain must be confirmed in a Preview build.
- Any existing production data and migration history are unknown. A backup and a staging rehearsal are prerequisites to schema changes.
- Product image uploads remain on local storage and are not durable on Vercel; this is tracked separately in Plan 012 and is not addressed here.

## Rollback Procedure

1. If Preview fails, stop promotion and preserve sanitized runtime diagnostics. Revert only the code/config change shown to cause the failure; keep TLS verification enabled.
2. Restore the previous trusted CA bundle or environment reference only if it is still valid and Aiven has not completed its rotation. Otherwise correct the new CA configuration and redeploy.
3. If a Production deployment fails before any migration, roll back to the previous Vercel deployment and verify it still has a valid CA and matching Aiven configuration.
4. If a migration has already run, do not automatically roll back schema or data. Stop writes if necessary, restore from the confirmed backup or apply a separately reviewed forward-fix, and coordinate recovery against the migration's documented impact.
5. Verify a read-only database query, application health, and sanitized logs after recovery. Do not record credentials or certificate contents in the incident report.

## Implementation Notes and Discoveries

- The Vercel/Aiven scaffolding and CA file were committed to `main` in `4674fae` before this audit plan was added.
- Completed local audit task 013.1. `composer check-platform-reqs` passes in the PHP 8.4.23 XAMPP environment; `pdo_mysql`, OpenSSL, and the PDO MySQL server-verification option are present. The CA parses, is marked `CA:TRUE`, and is currently within its validity window locally.
- The user provided a Vercel Preview URL and authorized a read-only request. The `/up` and `/api/categories` GETs returned HTTP 200 with HTML; this is not evidence that the intended Laravel routes ran.
- The user reports that the demo's Vercel Production deployment uses the Aiven Production database. Whether the supplied Preview uses the same environment/database is unconfirmed. The `/api/categories` response was `text/html` (332,297 bytes), not the expected JSON, so no Aiven connectivity result was established. Preview protection or routing behavior must be investigated without assuming which caused it. No write, migration, or seed request was made.
- Vercel dashboard variables/scopes, build logs, and the function artifact remain uninspected. Do not run more database requests until the Preview API routing/response is understood.
- No Aiven credentials, connection URI, certificate contents, remote dashboard, database, or Vercel deployment were inspected. The local CA checks do not establish Aiven trust or connectivity.
- Aiven's current PHP/PDO guide demonstrates certificate verification with `sslmode=verify-ca` and a CA path. Compare the effective `Pdo\Mysql` options in the exact Vercel runtime against the Aiven service's current connection instructions during task 013.4.
- Aiven can rotate project CA certificates. Maintain a documented refresh/redeployment step and verify the replacement CA before the old CA is retired.
- Vercel's documented function filesystem is read-only except for temporary `/tmp` storage; the runtime's bundle size and duration limits must be evaluated for the actual project deployment.

## Final Summary and Remaining Work

This plan records a read-only audit of the checked-in Laravel/Vercel/Aiven TLS setup and a staged validation path. Local PHP/PDO capabilities and PEM syntax are confirmed, but Vercel build compatibility, Aiven trust and connectivity, network access, environment scope, database state, and migrations remain unverified. Complete the Preview checks and external configuration review before any Production migration or rollout.
