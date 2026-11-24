# Mobile sign-in and deployment handoff

## Local preview
Start MySQL and PHP on the usual loopback address for desktop authentication. For a trusted private LAN preview only, use PHP -S 0.0.0.0:8000 and open the laptop's LAN IP on the phone. The login screen intentionally explains why Google authentication is unavailable over an ordinary HTTP LAN IP. Do not expose the PHP development server to the internet. Stop it after the preview.

## HTTPS deployment
- Obtain a stable public DNS hostname with valid HTTPS and redirect all HTTP requests to HTTPS at the web server/load balancer. Do not expose an unencrypted backend to the internet.
- Set SBB_PUBLIC_ORIGIN in the PHP server environment to the exact public HTTPS origin, e.g. https://sbb.example.com (illustrative only, not an assigned domain). No path or trailing slash is needed. Ensure PHP receives this environment variable.
- In Google Auth Platform > Clients, use the configured Web application OAuth client. Add the exact origin under Authorized JavaScript origins and the full HTTPS URL ending in /admin/google-callback.php under Authorized redirect URIs (adjust for the actual deployment subdirectory if applicable).
- Keep DEMO_MODE false and maintain the allowed administrator email list. Use the existing authorized client ID configuration; do not commit credentials. Do not trust client-supplied forwarded headers. Configure secure, HttpOnly session cookies on HTTPS and test the redirect flow.
- For local desktop development retain the existing localhost/127.0.0.1 entries that were working. Do not add the private LAN IP as an HTTP OAuth origin.
- Google token validation and CSRF verification are unchanged. Before public production deployment, replace the prototype tokeninfo request with Google's supported server-side ID-token verification library and verify hosting/security configuration. No live cloud resources have been provisioned by this change.

Reference: https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid

## Mobile QA after HTTPS is available
Check login at 360px, 390px, 768px and landscape; ensure logo and Google button fit. Test authorized and unauthorized sign-in, logout, desktop sign-in regression, dashboard, sidebar/profile menu, products and photos, inventory dialogs, order creation and FIFO, order history, payments/slips, financial reports and exports, settings and activity logs. Confirm tables scroll horizontally, inputs and action buttons are usable, and modals scroll without clipping. Actual device/browser verification is still required.
