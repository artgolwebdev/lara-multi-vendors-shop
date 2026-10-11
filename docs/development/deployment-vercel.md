# Vercel Deployment Preparation

## Current status

Plan 012 adds an initial Vercel PHP entry point and build configuration for the Laravel/React application. Read-only requests to the provided Preview's `/up` and `/api/categories` both returned HTTP 200 with HTML; the latter should return JSON. This does not verify Laravel health or Aiven connectivity, and the cause is unknown. See [Plan 012](../plans/012-vercel-aiven-production-deployment-plan.md), [Plan 013](../plans/013-aiven-mysql-ssl-investigation-plan.md), and [Plan 014](../plans/014-vercel-deployment-investigation-plan.md) for the investigation and remaining work.

Production is intended to use Aiven MySQL. The project owner reports that Aiven connection variables have been added to the Vercel project. Confirm that Vercel Production variables map to Laravel's `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Do not copy secret values into this repository.

## Vercel PHP runtime

- `vercel.json` selects `vercel-php@0.9.0` and routes requests to `api/index.php`.
- The PHP entry point initializes Laravel's writable storage and compiled views beneath the function's `/tmp` directory, configures the bundled Aiven CA certificate (with environment-variable overrides), and delegates requests to `public/index.php`.
- `composer.json` defines a `vercel` build script that runs Laravel package discovery, `npm ci`, and `npm run build`.
- `package.json` declares Node.js 22.x for the Vercel build.
- Function region is not pinned yet; select one close to the Aiven service after its region is confirmed.
- Local verification should use the PHP built-in server as documented by the community runtime; `vercel dev` is not assumed to support this runtime.

The PHP runtime is community maintained. Check its supported PHP version and extensions before deployment. Keep the production function bundle below Vercel's current 250 MB uncompressed limit and keep request/response payloads below the 4.5 MB limit.

## Aiven MySQL and TLS

Configure the Aiven service host, port, database name, username, and password in Vercel's Production environment. Set `DB_CONNECTION=mysql`. The app's `config/database.php` accepts `MYSQL_ATTR_SSL_CA` as a path and enables PDO server-certificate verification for MySQL connections.

The project-provided CA file at `storage/certs/ca.pem` is used by default when it is present in the deployment bundle. `api/index.php` sets `MYSQL_ATTR_SSL_CA` to its absolute path before Laravel boots. The file is not excluded by `.vercelignore`; keep it in the deployment source so the fallback remains available. PHP OpenSSL parses it, marks it `CA:TRUE`, and reports it is currently within its validity window. These local checks do not prove it is the current Aiven CA, that it chains to the service certificate, or that Vercel can connect. A non-empty `AIVEN_CA_PEM_B64` variable overrides the bundled file by writing the certificate under `/tmp`; a valid `MYSQL_ATTR_SSL_CA` file path is the next fallback. On Vercel, the entry point fails closed if it cannot find any of these certificates. Keep the CA bundle current and verify the connection from a deployed preview; update it when Aiven announces CA rotation.

Do not migrate or seed Production during the Vercel build. Before the first migration, establish whether the database contains data, take an Aiven backup, review pending migrations, and confirm the migration runner can reach Aiven. Run only reviewed forward migrations with `php artisan migrate --force` from a controlled release environment. Never use `migrate:fresh` or run demo seeders against Production.

## Runtime environment

Set these in Vercel's Production environment after confirming domain and storage decisions:

| Variable | Production setting |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Stable key generated for this application; do not rotate casually |
| `APP_URL` | HTTPS production domain |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Aiven connection values |
| `AIVEN_CA_PEM_B64` | Optional base64 Aiven project CA bundle override; normally the bundled `storage/certs/ca.pem` is used |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_SAME_SITE` | `lax` |
| `SESSION_DOMAIN` | Unset for same-origin session cookies unless a reviewed subdomain setup needs it |
| `SANCTUM_STATEFUL_DOMAINS` | Production SPA hostname(s); preview hosts only if preview auth is enabled |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` until an external queue worker is provided |
| `LOG_CHANNEL` | `stderr` |
| `MAIL_MAILER` and `MAIL_*` | A real provider for password recovery; `log` does not deliver email |
| `VERCEL_PHP_DOCROOT` | `public` |

The `FILESYSTEM_DISK` for product uploads is **not production-ready**. The app currently uses the local public disk. Vercel's `/tmp` is temporary scratch space, not durable storage; uploaded files can disappear when a function instance is replaced. Do not use Vercel local disk as the permanent product image store. Choose durable object storage, then configure the `public` disk and implement direct or bounded uploads before enabling admin image uploads in Production.

## Sessions, queue, logs, and assets

- Database-backed sessions and cache require their Laravel tables to exist in Aiven.
- `QUEUE_CONNECTION=sync` executes the current simulated notification job during the request. Vercel does not supply a persistent `queue:work` process. If real asynchronous work is required, provide a managed queue and an always-on worker outside the function.
- Send logs to `stderr`; avoid file-based logging and file cache/session drivers.
- Vite's `public/build` output is ignored by Git, so it must be built by the Composer Vercel build hook. `bootstrap/cache` package-discovery files are build artifacts; do not write there from a request.
- Local uploads and the `public/storage` symlink are not durable deployment storage.

## Rollout checklist

1. Confirm Aiven region, Vercel variable names/scopes, CA bundle, database contents, domain, mail provider, and object-storage plan.
2. Configure Preview variables without copying Production secrets into local files or logs.
3. Build and deploy a Preview. Verify static assets, Laravel boot, Aiven TLS connectivity, authentication, and function logs.
4. Configure and verify durable image storage before relying on product image upload or URLs.
5. Back up Aiven and apply reviewed migrations through a controlled release job before production promotion.
6. Configure the closest supported Vercel function region to Aiven, attach the production domain, and verify HTTPS.
7. Smoke-check `/up`, storefront routes, APIs, auth/CSRF, email recovery, image storage, logs, and database connection capacity.

## Known limits

- The runtime is a community PHP runtime; PHP 8.5 compatibility needs a Vercel build check because local development currently uses PHP 8.4.23.
- Vercel's function bundle limit, 4.5 MB payload limit, temporary filesystem, cold starts, and database connection concurrency require preview validation.
- This repository does not yet include a production migration workflow, selected object-storage provider, or verified Aiven connection from a Vercel function.
