# HelioHost Johnny Free — Sakura Staging Plan

**Status (Nov 23, 2026): guide only.** No HelioHost account, files, production OAuth client, or database have been created by this repository. Keep `main` untouched and use **fictional data only**. This is an internal admin school project, **not** a commercial sales site.

## Verified host capabilities and limitations

- HelioHost's **Johnny** plan is free, supports PHP, MariaDB, and Plesk. Hosting storage is **1,000 MB combined** (files, logs, databases and backups).
- New free Johnny signup slots are limited and generally open **00:00 UTC and 12:00 UTC**, equivalent to **8:00 AM and 8:00 PM Philippine time**. Availability depends on capacity.
- Johnny has **no SSH/shell access**. Use Plesk's **File Manager or FTP** and Plesk's **Composer integration**. Do **not** follow the SSH / `git clone` instructions in the alwaysdata guide.
- Plesk main-site document root is ordinarily **`httpdocs`**.
- Let's Encrypt SSL certificates are available through Plesk. Install HTTPS **before** registering the public Google redirect URI.
- Log in at **heliohost.org** at least once each month to keep the Johnny account active.
- Accounts are ordinarily limited to **one Johnny/Tommy hosting account per person**. One account can host SBB staging; teammates can sign in through Sakura's Google Login instead of sharing Plesk access.
- Academic/development use is compatible with its noncommercial plan terms. No live payment processing, real financial services, or real customer records.

Official sources:
- https://heliohost.org/signup/
- https://heliohost.org/terms/
- https://wiki.helionet.org/Johnny
- https://wiki.helionet.org/Storage
- https://wiki.helionet.org/Create_Website
- https://wiki.helionet.org/MySQL
- https://wiki.helionet.org/SSL_Certificate_Problems
- https://helionet.org/index/topic/64592-hh943046-user-name-wapps-re-use-of-ssh-with-johnny-server/

## Stage 1 — Register a free Johnny account

1. Open https://heliohost.org/signup/.
2. Choose **Johnny (Free)**, not the paid/donation-only Tommy/Morty/VPS options.
3. If the free signup says full, use the next slot at 8 AM or 8 PM Philippine time. The slot can fill very quickly.
4. Use a non-disposable email and a username with **no private details**. Complete account/email verification.
5. Once HelioHost activates the hosting account, note your **actual** assigned subdomain (often `username.helioho.st`, but follow what Plesk displays). Do **not** guess an address.
6. Enter **Plesk** from the HelioHost dashboard. Check **Websites & Domains**, **PHP settings**, **Databases**, **Files** and **Composer** availability.
7. A new account/domain may require up to **2 hours** before browser access works normally. Status: https://heliohost.org/status/.

## Stage 2 — Prepare a reproducible staging release BEFORE uploading

1. Keep this work on `feature/google-login-team-setup`, not `main`. Review authentication for every admin page/API, sensitive file exposure, and CSRF coverage.
2. On a development computer, install Composer dependencies, test PHP locally, and generate and commit a reviewed `composer.lock` matching `composer.json`.
3. GitHub Actions PHP syntax lint should pass. **Passing lint does not mean functionality or security are tested.**
4. Prepare a ZIP of the reviewed branch with **no** `config.local.php`, `db.local.php`, OAuth JSON, `.env` files, SQL backups, secrets, or `.git` directory.
5. On HelioHost shared hosting there is **no SSH** for running `composer install` or `php scripts/check_deployment.php`. Upload the reviewed source through **Plesk > Websites & Domains > [domain] > Files**, extract to the Plesk webroot / designated application path, and use the **Plesk Composer UI** to install dependencies using the lockfile if available.
6. Confirm Plesk is running **PHP 8.2 or newer** with PDO MySQL, OpenSSL and required Google library extensions. If any requirement is missing, stop rather than bypassing authentication checks.

### Exposing internal files

The current repository layout uses `admin/`, `assets/`, `includes/`, `database/`, `docs/` and `scripts/` relative to its root. Do **not** publicly upload until its document-root/Apache-Nginx protections have been independently reviewed. The repository has Apache `.htaccess` deny rules, **but Nginx direct static-file serving can bypass them** if misconfigured.

Prefer keeping configuration/source outside `httpdocs` where possible, but that requires code/path adjustments and host support; do not move folders arbitrarily because existing PHP uses relative `__DIR__` paths.

**Pre-launch test required:** direct requests for `/includes/config.local.php`, `/database/schema.sql`, `/scripts/check_db.php`, `/vendor/` and `/.git/` must return **403 or 404** with no source, credentials, or SQL displayed. In Plesk, check **Apache & Nginx Settings**, including whether Nginx serves static files directly. If content is readable, disable the site and fix the configuration first.

## Stage 3 — Create MariaDB through Plesk

1. Choose **Plesk > Databases > Add Database**. Create a *new empty* Sakura staging database.
2. Create a restricted database user; allow **local connections only**. HelioHost typically prefixes both database/user with your hosting account name.
3. For PHP on the same hosting machine, MySQL host is `localhost` and port is usually `3306`; confirm in Plesk.
4. Open **phpMyAdmin** from the database entry and import **`database/schema.sql` into the fresh empty database only**. Never import it over existing valuable data.
5. On the host, copy the template from `includes/db.local.php.example` to the ignored `includes/db.local.php` and fill the exact host-provided DB name, username and private password through Plesk File Manager. Never put a DB password into GitHub or chat.
6. The schema contains **no real business records**. Use fictional test records only; verify access via the app once ready.

## Stage 4 — HTTPS and Google sign-in for staging

1. In **Plesk > Websites & Domains > [domain] > SSL/TLS Certificates**, use **Let's Encrypt** for the real assigned domain. Select HTTP-to-HTTPS redirect once certificate issuance succeeds. Verify an HTTPS URL actually loads with a valid browser certificate.
2. Create a **separate staging Web OAuth client** in the existing Google Cloud *test* project. Do not replace the working localhost client. Use the **exact** assigned HTTPS domain:
   - **Authorized JavaScript origin** `https://YOUR-ASSIGNED-DOMAIN`
   - **Authorized redirect URI** `https://YOUR-ASSIGNED-DOMAIN/admin/google-callback.php`
3. Google may require domain authorization/verification for the assigned free subdomain. **Do not assume** the HelioHost subdomain will be accepted as a Google OAuth origin until it is entered and tested. If Google refuses it, review Google's domain-ownership requirements and HelioHost support process.
4. Keep approved Gmail addresses in **Google Auth Platform > Audience > Test users** and in Sakura's server-side approved-admin email list.
5. Create an **ignored** server-side `includes/config.local.php` from its example with values similar to:
   ```php
   <?php
   return [
       'environment' => 'production', // enforce HTTPS/token verification, even for staging
       'base_url' => 'https://YOUR-ASSIGNED-DOMAIN',
       'google_client_id' => 'YOUR-STAGING-CLIENT-ID.apps.googleusercontent.com',
       'allowed_admin_emails' => ['your-approved-test-account@gmail.com'],
   ];
   ```
   Replace placeholders **privately on the host**. **Never upload** Google OAuth JSON or client secrets.
6. This branch intentionally sets `DEMO_MODE=false` and uses the official Google PHP ID-token verification library in production mode. Verify Plesk installed `vendor/autoload.php` and the `google/apiclient` package before testing login.
7. Use secure session settings in Plesk/PHP where supported: `session.cookie_secure=1`, `session.cookie_httponly=1`, `session.cookie_samesite=Lax`, `session.use_strict_mode=1`.

## Stage 5 — Test and keep staging private

- Sign in through `https://YOUR-ASSIGNED-DOMAIN/admin/login.php` with an approved *test* Google account; test unauthorized account rejection and logout.
- Check access controls for **every admin page and every admin/api endpoint**, including POST operations, CSRF tokens and stale/demo sessions.
- Check Google callback works with the real domain and server outbound requests. If blocked, **never disable token validation to work around it**.
- Test dashboard, orders, inventory, payments, reports with fictional records.
- Check sensitive URL denying, HTTPS, cookies, PHP error display, Composer installed files, backups/restore, and disk usage.
- Review Plesk server logs for errors. Because Johnny has no shell, the CLI-only `scripts/check_deployment.php` cannot be executed directly on that server; use local/CI checks and Plesk settings instead.
- Don't enable live transactions or upload real customers' personal/payment data. School staging is not production approval.

## Stop / rollback

If registration is full, wait for another free slot. If Plesk cannot support required dependencies, Google rejects the shared domain, HTTP protections don't work, or PHP/MySQL fails, **do not expose the application**. Keep the site unpublished/disabled while resolving it. `main` and local development remain unaffected.
