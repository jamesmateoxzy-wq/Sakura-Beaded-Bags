# Sakura Staging on alwaysdata Free (PHP + MariaDB + Google Login)

**Purpose:** a no-cost, noncommercial academic test deployment. Do not put real customers, real sales, personal payment details or passwords in this staging database. This is *not* approval for production use.

**Plan limits (verified Nov 23, 2026):** Free plan offers 1 GB combined storage, 256 MB RAM, 1/4 CPU, a site at `[account].alwaysdata.net`, and is restricted to **nonprofit/noncommercial use**. Without a payment card or previous payment, alwaysdata applies a technical restricted mode which may affect web requests. Confirm current eligibility before signing up.

Official sources:
- https://help.alwaysdata.com/en/docs/admin-billing/billing/public-cloud-prices/
- https://help.alwaysdata.com/en/docs/admin-billing/profile/restricted-mode/
- https://help.alwaysdata.com/en/docs/web-hosting/sites/add-a-site/
- https://help.alwaysdata.com/en/docs/web-hosting/databases/mariadb/
- https://help.alwaysdata.com/en/docs/web-hosting/remote-access/ssh/
- https://help.alwaysdata.com/en/docs/web-hosting/languages/php/packages/
- https://help.alwaysdata.com/en/docs/web-hosting/sites/ssl-tls/lets-encrypt/

## 0. Before deploying the branch

- The branch is `feature/google-login-team-setup`, reviewed separately from `main`.
- **Do not publish it yet**. Run branch tests, review all API/page guards, and generate and commit a tested `composer.lock`. The GitHub PHP syntax check alone is insufficient.
- The server runs in production security mode even though this is a *staging* instance.
- Test only with simulated data.

## 1. Free account

1. Go to https://www.alwaysdata.com/en/ and choose the **Free** offering, not a paid trial.
2. Create a hosting account. Write down its account name as `[account]`; the free site's address is typically `https://[account].alwaysdata.net`.
3. Verify the address has valid HTTPS; the provider manages certificates for its `alwaysdata.net` subdomains.
4. Leave payment details empty if staying truly at ₱0. Expect additional HTTP restrictions on such accounts.

## 2. Prepare remote files (after source is ready for staging)

1. alwaysdata Control Panel > **Remote access > SSH/SFTP**, verify SSH credentials. Official host: `ssh-[account].alwaysdata.net` port 22.
2. Connect using PowerShell `ssh [ssh-user]@ssh-[account].alwaysdata.net`, or use the provider's SSH web interface.
3. In the remote shell:
   ```sh
   mkdir -p ~/www
   cd ~/www
   git clone --branch feature/google-login-team-setup --single-branch https://github.com/jamesmateoxzy-wq/Sakura-Beaded-Bags.git sakura-staging
   cd sakura-staging
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   ```
   **Before this step, commit a tested composer.lock.** The existing composer.json alone is not a reproducible deployment.
4. In **Web > Sites**, create/configure a **PHP 8.2+** site for `[account].alwaysdata.net`, with document root `/home/[account]/www/sakura-staging` (adjust if you chose a different folder).
5. **Do not expose the app until** the private config and HTTP protections are verified. The root includes .htaccess and directory-specific deny rules. Test they take effect on alwaysdata's Apache/PHP site. The normal login page is `https://[account].alwaysdata.net/admin/login.php`; visiting the domain root by itself may show a 403 because no index page is committed.

## 3. Database

1. Control Panel > **Databases > MySQL**, create a MariaDB database and a database user with privileges needed for the app. Record the **actual** database name and username from the control panel (providers may prefix names).
2. Use its phpMyAdmin link to select the **empty** database and import `database/schema.sql` from the repository. Do not import this fresh-install schema over existing data.
3. Copy the ignored template to the private file on the remote server:
   ```sh
   cp includes/db.local.php.example includes/db.local.php
   ```
   Edit `includes/db.local.php` on the host using a trusted editor. Fill host `mysql-[account].alwaysdata.net`, port `3306` unless displayed otherwise, the **actual** database name, restricted username and password.
4. Validate in SSH:
   ```sh
   php scripts/check_db.php
   ```

## 4. Google Sign-In for the HTTPS staging address

1. In the *development/test* Google Cloud project, create a **separate Web application OAuth client for staging** (keep the working localhost client).
2. Add **Authorized JavaScript origin** `https://[account].alwaysdata.net`.
3. Add **Authorized redirect URI** `https://[account].alwaysdata.net/admin/google-callback.php`.
4. Ensure your authorized Gmail is in **Google Auth Platform > Audience > Test users**.
5. In the remote server's private config:
   ```sh
   cp includes/config.local.php.example includes/config.local.php
   ```
   Edit the **ignored** `includes/config.local.php` settings:
   ```php
   return [
       'environment' => 'production',
       'base_url' => 'https://[account].alwaysdata.net',
       'google_client_id' => 'THE-STAGING-CLIENT-ID.apps.googleusercontent.com',
       'allowed_admin_emails' => ['approved-test-user@gmail.com'],
   ];
   ```
   Replace examples with real values **on the host only**. Do not upload an OAuth JSON, client secret, or this private file to GitHub.
6. In alwaysdata Environment > PHP, set secure PHP sessions for the HTTPS site when possible: `session.cookie_secure=1`, `session.cookie_httponly=1`, `session.cookie_samesite=Lax`, `session.use_strict_mode=1`.
7. Use the **official Google PHP client library**, installed via Composer. In production mode the callback does not use the local-only tokeninfo fallback.
8. Visit `https://[account].alwaysdata.net/admin/login.php`, sign in with the allowed test Gmail, then test logout. The default demo login is disabled in this branch.

## 5. Preflight and safety verification

SSH:
```sh
php scripts/check_deployment.php
```
This checks PHP, settings, HTTPS URL, extensions, Composer Google client, and DB connection. It is not a security audit.

Before sharing the URL, verify:
- Unauthenticated requests to all /admin pages and /admin/api endpoints are rejected, even if a session exists from the old demo.
- Approved Gmail works; an unapproved Gmail does **not** reach the admin dashboard.
- Uploading and state-changing requests require access controls and CSRF checks.
- Sensitive URLs `/includes/config.php`, `/includes/config.local.php`, `/database/schema.sql`, `/scripts/check_db.php`, `/vendor/`, `/.git/` return 403/404 instead of content.
- HTTPS is forced; session cookie is Secure and HttpOnly; browser/console shows no OAuth errors.
- Test dashboard, orders, inventory, payments and reports on **fake data**.
- The free plan's request filtering does not interfere with Google's redirect POST.

If the provider's free-tier traffic filtering or the Google OAuth domain registration blocks testing, **stop and reassess**. Don't weaken token checks or turn demo auth back on to bypass a hosting error.

## 6. Rollback

Don't merge `main` or connect production users/data. If a check fails, disable the hosted site in Web > Sites. Keep local working code/database and draft PR intact. Consult `docs/DEPLOYMENT_PREP.md` for the broader go-live checklist.
