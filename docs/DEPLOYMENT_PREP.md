# Sakura Beaded Bags — GitHub & Deployment Preparation

**Status: preparation only.** This branch has not been merged into `main`, deployed to a server, tested end-to-end on a hosted domain, or approved for real customer data.

This is an **internal admin system**, not a public customer sign-up site. Only approved Google accounts should access it.

## What the target host must support

- PHP **8.2+**, including PDO MySQL, OpenSSL and the extensions required by Composer's Google API client
- MySQL / MariaDB, database credentials with only necessary privileges
- HTTPS with a valid certificate for the public domain
- Server-set environment variables (preferred), or private configuration files
- Composer dependencies (`vendor/` is ignored by Git and must be installed during build/deploy)
- HTTP access controls: `includes/`, `database/`, `docs/`, `scripts/`, `vendor/`, `.git/` and credentials must **not** be web-readable
- Persistent server-side PHP session storage and proper secure-cookie settings

The PHP built-in server (`php -S 127.0.0.1:8000`) is for **local development only**, not production hosting.

## Hosting configuration (never commit real values)

Set these **on the server**, not in GitHub:

| Variable | Example / purpose |
| --- | --- |
| `SBB_ENV` | `production` |
| `SBB_BASE_URL` | `https://sakura.example.com` (exact externally visible site origin; include an application subpath only if applicable) |
| `SBB_GOOGLE_CLIENT_ID` | The full production Web OAuth client ID (public identifier) |
| `SBB_ALLOWED_ADMIN_EMAILS` | Comma-separated approved Gmail addresses |
| `SBB_DB_HOST` | Database host supplied by hosting provider |
| `SBB_DB_PORT` | Usually `3306` (use host's actual port) |
| `SBB_DB_NAME` | Database name |
| `SBB_DB_USER` | Restricted database username |
| `SBB_DB_PASSWORD` | **Private** database password |

If the provider cannot set environment variables, use ignored `includes/config.local.php` (copy the example) and `includes/db.local.php`, protected from direct HTTP access. Add `environment => 'production'` and `base_url => 'https://your-real-domain'` to the local Google config array. Never copy these real private config files to GitHub.

`SBB_ENV=production` requires an HTTPS `SBB_BASE_URL` and the official Google PHP client library. Demo mode is always disabled in the shared branch.

### Google Cloud production client

Google recommends **separate Cloud projects for test and production**. Keep today's localhost client for development. Before a real public-domain rollout:

1. Create the separate production project and Web OAuth client.
2. Register the production URL in **Authorized JavaScript origins**, e.g. `https://sakura.example.com`.
3. Register its exact callback in **Authorized redirect URIs**, e.g. `https://sakura.example.com/admin/google-callback.php`.
4. Update the production server's `SBB_GOOGLE_CLIENT_ID`. Keep authorized/admin email lists restricted.
5. Review Google Auth Platform audience, test-user / publishing status and any verification requirements.

Google's sign-in handler must be served over HTTPS on a public domain. Neither the Google **Client Secret** nor the downloaded JSON file is required by Sakura's current Google ID-token login flow.

Docs:
- https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid
- https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
- https://developers.google.com/identity/protocols/oauth2/policies

## Composer and server-side token validation

The existing PHP callback historically used Google's `tokeninfo` endpoint for local debugging. The deployment-preparation branch uses the **Google API client PHP library** when installed and **refuses production sign-in without it**.

On the development machine with Composer installed, from the repository root:

```powershell
composer install
```

Because this preparation branch does **not yet include composer.lock**, generate and commit a tested `composer.lock` before the actual production release. Deployment should then run:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

Do **not** commit `vendor/`; it is ignored. Before deployment check that `vendor/autoload.php` exists on the server.

## Database setup

1. Provision the target database and restricted DB user with the hosting provider.
2. **Back up existing data first** if migrating an existing SBB database.
3. For a **fresh empty database only**, import `database/schema.sql`. Never reimport it over an existing populated database. For existing DBs, review `scripts/migrate_*.php` according to `database/README.md`.
4. Set the `SBB_DB_*` variables on the hosting service.
5. Verify connectivity via a CLI command on the hosting machine:

```sh
php scripts/check_db.php
php scripts/check_deployment.php
```

The deployment preflight script checks PHP, Google configuration, installed Composer code and MySQL without printing passwords. It does not substitute for manual HTTP/security tests. The schema alone will not synchronize everyone's laptop data. A shared online database is a separate hosted service with credentials and backup responsibilities.

## HTTP and security safeguards

- The repo includes Apache `.htaccess` denial rules for non-public paths, plus directory-specific denies. **These do not work on Nginx, the PHP built-in server, or when Apache ignores .htaccess.** Prefer configuring the webserver document root / server rules so application internals are not exposed at all.
- Before taking real data, manually test that direct requests to `/includes/config.php`, `/includes/config.local.php`, `/database/schema.sql`, `/scripts/check_db.php`, and `/vendor/` return **403 or 404**, not their source/content. Disable directory indexing.
- On HTTPS hosting, set PHP sessions to `session.cookie_secure=1`, `session.cookie_httponly=1`, `session.cookie_samesite=Lax`, `session.use_strict_mode=1`; review session storage and proxy HTTPS forwarding.
- Disable error display to visitors (`display_errors=Off`), log server errors privately, and do not expose SQL or tokens in error messages.
- Test account allowlists and protection of **every admin page and API endpoint**, role checks, CSRF controls for state-changing requests, upload handling, and input validation.
- Check backup/restore, MySQL grants, HTTPS redirects, and a logout/session-expiration flow.
- No live deployment should be treated as production-ready without these checks and an end-to-end security review.

## Local teammate setup

See [GOOGLE_LOGIN_TEAM_SETUP.md](GOOGLE_LOGIN_TEAM_SETUP.md) for the separate `127.0.0.1:8000` workflow. All teammates can reuse a shared **development** Google Client ID, but must be added as Google's test users and explicitly authorized by Sakura.

## Release checklist

- [ ] Code reviewed and tested on an isolated branch (automated syntax workflow passing)
- [ ] Existing local `config.php` edits safely migrated to `config.local.php`
- [ ] Composer installed and a verified `composer.lock` committed
- [ ] Hosted PHP + MySQL provider chosen; domain and valid HTTPS available
- [ ] Production Google project/client created; live origin and callback registered
- [ ] Environment variables / private config set; test/demo accounts removed
- [ ] Database migrated, backed up and verified
- [ ] Non-public HTTP paths blocked by the actual server (Apache/Nginx)
- [ ] Signed-in/unauthorized/session-expired users tested on all pages and APIs
- [ ] Production smoke test and rollback plan completed
- [ ] Team agrees to merge the reviewed changes to `main` and deploy

**Not done:** no public URL, no live database, no deployed server, no production Google client, no green automated test run, and no `main` merge.
