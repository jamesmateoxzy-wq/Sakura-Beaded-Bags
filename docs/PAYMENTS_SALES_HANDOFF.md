# Payments & Sales — frontend handoff

**Scope:** Internal Sakura Beaded Bags admin system. No customer-facing checkout and no payment gateway.

**Status:** Frontend prototype with interactive DEMO behavior. This does **not** record actual business payments, charge a customer, or save anything to MySQL.

## Files

- admin/payments.php — page structure, fictitious seed data, summary, transactions, payment form, slip preview
- assets/css/payments.css — styling matched to Phase 3 Figma, responsive layout, print receipt rules
- assets/js/payments.js — filtering, page navigation, balance calculation, validation, demo recording, slip selection and printing

Built using the common includes/header.php, includes/footer.php, includes/functions.php, includes/config.php, assets/css/style.css, and assets/js/main.js from the frontend/login-dashboard-products branch. This working branch is based on those shared files; it does not modify the original branch or main.

## Preview

1. Check out the frontend/payments-sales branch.
2. Run the project from its root with a PHP server, for example: php -S 127.0.0.1:8000 (or use XAMPP).
3. With DEMO_MODE = true (current local development setting), visit http://127.0.0.1:8000/admin/login.php?demo=1.
4. Select **Payments & Sales** from the sidebar. This demo account sees *fictitious* orders and payment slips.
5. Try recording a partial payment for ORD-004, then the remaining balance. View the newest slip and try printing.
6. Payments added during the demo are stored in sessionStorage in this browser tab only. A new tab or ending the browser session may reset them.

**Never enter real customer payment information into the demo.** Set DEMO_MODE = false and configure Google sign-in securely before deploying. USE_SAMPLE_DATA = true is also for demos only.

## Current demo behavior

- Four live-derived summary metrics: total order value, payments received, outstanding balance, collection rate.
- Transactions searchable by slip ID, order ID, customer, method or reference, plus status filter and pagination.
- Selects only unpaid orders; rejects empty/negative payments, excess balance, >2 decimals, future/invalid dates, duplicate reference numbers for the same method.
- Cash receipts get an automatic demo reference; GCash / bank payments require a reference.
- Each partial or final payment creates its own demo slip, with the remaining balance at the time of payment.
- Printable slips are explicitly marked **DEMO ONLY**.
- If no demo session or sample data is enabled, the page shows zeros and leaves recording disabled instead of pretending records are saved.

## Backend integration TODO — IMPORTANT

The existing MySQL database integration has not been implemented in this branch. Before production:

1. Create SQL tables/relationships for orders and payments, with a foreign key from payment to order.
2. Use DECIMAL(12,2) for money (or integer cents), not floating-point storage.
3. Replace the PHP sample arrays with authorized, parameterized SQL reads scoped to the correct order and payment records.
4. Implement an authenticated POST endpoint to record a payment with CSRF protection and **server-side** validation of all fields.
5. In a database transaction, lock the target order, recalculate the remaining balance, reject overpayment, insert the payment and commit. Do not trust browser-provided balances, slip IDs, or customer names.
6. Generate unique slip IDs on the server; treat repeated requests idempotently to prevent duplicate payments. Constrain reference uniqueness where applicable.
7. After a confirmed successful response, refresh transaction rows, order balances and summary cards from the server. **Remove** demo sessionStorage as the system of record.
8. Wire actual data into Dashboard and Financial Reports. Ensure order cancellation/refund policy and audit history are defined.
9. Keep Google Client ID/allowed admins configured correctly, disable demo sign-in, and test access restrictions before deployment.
10. No real GCash, bank, or card payment is processed by this frontend. Do not claim a payment is received until staff verify it according to business policy.

### Suggested data shapes

Order: { id: "ORD-004", customer: "Isabel Garcia", item: "Petal Handbag", total: 1200.00 }

Payment: { id: "SLP-004", orderId: "ORD-004", amount: 600.00, method: "Cash", date: "2026-10-25", reference: "CASH-1025-04", balanceAfter: 600.00 }

Treat these as display contracts only, not trusted API payloads.

## QA checklist

- [ ] Desktop and mobile layout is readable
- [ ] Sidebar link opens Payments & Sales after demo login
- [ ] Empty state is readable when demo data is off
- [ ] Search by customer, order ID and slip ID
- [ ] Status filter and pagination change results correctly
- [ ] Recording partial payment updates summary and remaining balance
- [ ] Paying exact remainder changes status to Fully Paid and removes order from unpaid choices
- [ ] Zero, negative, too many decimals and overpayment are rejected
- [ ] Invalid future date, missing transfer reference and duplicate reference are rejected
- [ ] View each slip and print only the selected slip
- [ ] Demo banner and slip warning are visible
- [ ] Reload within the same tab restores demo additions when sessionStorage is available
- [ ] Logout/login flow and access control remain intact
- [ ] Full backend/integration/security tests are still **pending**

## Merge note

Because this branch builds on frontend/login-dashboard-products, review and merge that common frontend first. Then review the three Payments & Sales files and this handoff document, and coordinate any merge/rebase conflicts. Do not merge directly to main without the team's review.
