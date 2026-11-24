# Final improvements QA handoff

Use a backed-up local test database. Stop the PHP server, switch to feature/final-improvements, pull and run:

    C:\xampp\php\php.exe scripts/migrate_final_improvements.php --apply

This creates three tables only if absent. Existing expenses and order_status_history are reused. Do not reimport or delete your database. Existing migration scripts must already have been applied.

## Checks
1. Profile: save a display name and phone; refresh, logout and sign back in. Email/role stay read-only.
2. Settings: save boutique name/contact/address; verify printed slips and reports. Change packaging/markup/downpayment defaults; check a new product. Labor rate is a reference for entering total labor cost. Existing products/orders are unchanged.
3. Expenses: add and edit an operating expense; check the report for that date. Reject zero, negative and invalid values. No permanent deletion feature in this batch. Do not duplicate production costs.
4. Reports: verify Estimated Profit labels and CSV/print. Calculation remains payment-based, not audited net income.
5. Photos: replace an active product photo with a JPG/PNG/WebP <=4 MB; reload. Reject invalid files. Price and BOM must be unchanged. Old uploaded files are retained.
6. Overdue: verify past-due, not cancelled/delivered/returned/archived orders are flagged; check dashboard link and filter. Uses Manila calendar date.
7. History: create order, advance production, ship and deliver when fully paid; see recorded transitions. Cancel a separate eligible test order; check inventory restoration behavior. Archived history remains accessible. Historical events that were never recorded are not reconstructed.
8. Errors: test missing DB connection on a test environment, confirm dashboard shows unavailable instead of false zeros; restore DB. Test expired form tokens and invalid photo/expense input. No raw SQL error shown to browser.
9. Mobile: check all pages and modals at 360px, 390px, 768px, desktop. Tables should scroll horizontally; no clipped buttons. Visual browser/device QA still required.
10. Activity: check sign-in/out, profile/settings, expenses, successful product/inventory/order/payment operations. Activity is operational logging, not tamper-proof audit; API logging is best effort, can include accepted no-op requests and does not backfill old actions.

Run regression tests: Google authorization; dashboard totals; category removal rules; product photo add; pricing and duplicate rules; inventory stock in/correction; order creation/cancellation/archive/unarchive; shipping/delivery; GCash/bank transfer; reports/exports. Confirm database and uploads backup/restore before deployment.

Main is not merged. Runtime PHP/MySQL and browser testing are required.
