# Sakura Beaded Bags — Google Login on Each Teammate's Laptop

This guide is for the **internal admin system**, not public customer registration.

## Google Cloud: one shared OAuth client

The project owner maintains **one** Google Cloud project and OAuth **Web application** client for the team. Each teammate does **not** need to create a separate Google Cloud project.

In Google Cloud Console > Google Auth Platform:

1. Open **Audience > Test users**. Add the Gmail address of **each approved teammate** who will sign in. In Testing mode, Google limits access to designated test users.
2. Open **Clients > Sakura Beaded Bags Web** and verify:
   - Authorized JavaScript origin: `http://127.0.0.1:8000`
   - Authorized redirect URI: `http://127.0.0.1:8000/admin/google-callback.php`
3. Share the **OAuth Client ID** with teammates. This ID is public. **Never share the Client Secret** or upload the downloaded OAuth JSON file to GitHub.

## Each teammate: local PHP setup

1. Clone/pull the shared Sakura repository with the team Google Login changes.
2. In VS Code / PowerShell at the repository root, run:
   ```powershell
   Copy-Item includes/config.local.php.example includes/config.local.php
   ```
3. Open `includes/config.local.php` and replace:
   - `google_client_id` with the **same** project's full OAuth Client ID (ends in `.apps.googleusercontent.com`).
   - `allowed_admin_emails` with the approved Gmail account(s). **Do not leave the example placeholder**.
   
   Note: Google test-user status **alone does not grant dashboard access**. The Gmail must be listed in the local PHP allowlist too. On a publicly deployed server, only the administrator should manage the server's allowlist.
4. Start PHP from the repository root (no need to run Apache):
   ```powershell
   & "C:\xampp\php\php.exe" -S 127.0.0.1:8000
   ```
5. Open `http://127.0.0.1:8000/admin/logout.php` to clear any previous PHP session. This redirects to `admin/login.php`.
6. Click **Continue with Google**, choose the approved account, and confirm that Sakura shows the dashboard.

**Every teammate can use the same `127.0.0.1:8000` address on their own laptop.** `127.0.0.1` refers to the laptop being used, not one shared remote computer. This is **not** a shared online deployment.

## MySQL is separate from Google Login

The dashboard fetches live MySQL data. On each laptop, start **MySQL** in XAMPP and configure the existing database connection (see `database/README.md`). The teammates' separate local databases **do not automatically synchronize**. A shared online database/server requires deployment work later.

## Troubleshooting

- **Google button missing or setup alert**: check `includes/config.local.php` and its Client ID. Confirm Chrome can load Google's script.
- **origin / redirect URI mismatch**: launch exactly `http://127.0.0.1:8000/admin/login.php`, not a different port or `localhost`, unless those addresses are separately registered in Google Cloud.
- **Google access blocked while in Testing**: check the Gmail under Google Cloud > Audience > Test users.
- **This Google account is not authorized as a Sakura admin**: check the Gmail spelling in `allowed_admin_emails`.
- **Dashboard could not load MySQL data**: start MySQL and test `& "C:\xampp\php\php.exe" scripts/check_db.php` in a second terminal.
- **Dashboard skips login**: use `admin/logout.php` to clear an existing session.

## Security

- The committed `includes/config.php` defaults to **DEMO_MODE = false** and an empty allowlist until a local file is configured.
- `includes/config.local.php` is ignored by `.gitignore`; don't force-add it.
- Use an approved-account list, not an "allow any Google account" rule.
- This is an academic **local-development** guide. Before public deployment, perform a security review, replace tokeninfo-based verification with Google's supported server-side token-verification library, use HTTPS, and audit all admin/API routes.
