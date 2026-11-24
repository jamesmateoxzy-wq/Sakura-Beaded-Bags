# Payments & Sales — frontend handoff

**Scope:** Internal Sakura Beaded Bags admin system. No customer-facing checkout and no payment gateway.

**Status:** The Payments & Sales page uses the authorized MySQL API. Staff-entered receipts are recorded as successful payment rows; no payment gateway is connected.

## Files

- admin/payments.php — payment summary, transaction table, payment form, and slip preview
- assets/css/payments.css — styling matched to Phase 3 Figma, responsive layout, print receipt rules
- assets/js/payments-live.js — MySQL reads/writes, filtering, validation, void/refund confirmations, slip selection and printing
- admin/api/payments.php — authorized, CSRF-protected and idempotent payment operations

Built using the common includes/header.php, includes/footer.php, includes/functions.php, includes/config.php, assets/css/style.css, and assets/js/main.js from the frontend/login-dashboard-products branch. This working branch is based on those shared files; it does not modify the original branch or main.

## Preview

1. Check out the frontend/payments-sales branch.
2. Run the project from its root with a PHP server, for example: php -S 127.0.0.1:8000 (or use XAMPP).
3. With DEMO_MODE = true (current local development setting), visit http://127.0.0.1:8000/admin/login.php?demo=1.
4. Select **Payments & Sales** from the sidebar. This demo account sees *fictitious* orders and payment slips.
5. Try recording a partial payment for ORD-004, then the remaining balance. View the newest slip and try printing.
6. Payments added during the demo are stored in sessionStorage in this browser tab only. A new tab or ending the browser session may reset them.

**Never enter real customer payment information into the demo.** Set DEMO_MODE = false and configure Google sign-in securely before deploying. USE_SAMPLE_DATA = true is also for demos only.

## Current behavior

- Total Sales and Payments Received include successful payment records only; outstanding balance is calculated from successful payments.
- Transactions searchable by slip ID, order ID, customer, method or reference, plus status filter and pagination.
- Selects only unpaid orders; server-side validation rejects invalid/over-balance payments, invalid dates and duplicate references.
- Payment request keys prevent retry/double-submission from recording a duplicate payment.
- Successful payments can be voided or marked fully refunded; their records remain in the database and no longer count toward sales or order balances.
- Dashboard and Financial Reports use successful payment records, grouped by payment date.

## Existing-database migration

For an existing database, back it up and preview/apply `scripts/migrate_sales_soft_deletion.php`. It adds payment states and material inactive flags without deleting records and updates `v_order_balances` to sum only successful payments. Never import `database/schema.sql` over an existing database.

## QA checklist

- [ ] Desktop and mobile layout is readable
- [ ] Sidebar link opens Payments & Sales after demo login
- [ ] Empty state is readable when demo data is off
- [ ] Search by customer, order ID and slip ID
- [ ] Status filter and pagination change results correctly
- [ ] An unpaid order does not increase Total Sales
- [ ] Partial and additional payments increase Total Sales by the successful amounts only
- [ ] Paying the exact remainder changes status to Fully Paid and removes the order from unpaid choices
- [ ] Replaying a payment request does not create a duplicate
- [ ] Voided/refunded payments remain visible in history but are excluded from sales and balances
- [ ] Zero, negative, too many decimals and overpayment are rejected
- [ ] Invalid future date, missing transfer reference and duplicate reference are rejected
- [ ] View each slip and print only the selected slip
- [ ] Successful changes refresh from MySQL and display a notification
- [ ] Logout/login flow and access control remain intact
- [ ] Database migration and end-to-end testing against the configured MySQL instance

## Merge note

Because this branch builds on frontend/login-dashboard-products, review and merge that common frontend first. Then review the three Payments & Sales files and this handoff document, and coordinate any merge/rebase conflicts. Do not merge directly to main without the team's review.
