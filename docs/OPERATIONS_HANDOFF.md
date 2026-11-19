# Inventory, Orders & Reports — Frontend Handoff

## Scope / current status
Internal admin-only Sakura Beaded Bags prototype. This branch contains three functional **DEMO UI** modules, built on the shared login/dashboard/products/payments pages from `integration/system-testing`.

The pages **do not use MySQL**, do not save real orders or stock, and do not produce official financial statements. Fictitious demo data and edits are stored in `sessionStorage` for the current browser tab only. With DEMO_MODE or USE_SAMPLE_DATA off, the UI shows empty results and prevents edits.

## Files
- `admin/inventory.php` — inventory metrics, material list, add material and stock adjustment forms.
- `admin/orders.php` — order metrics, order list, create order, view details and status updates.
- `admin/reports.php` — month filter, KPI totals, monthly order chart, top products, status breakdown, low-stock list, print and CSV.
- `assets/css/operations.css` — responsive Sakura-themed styling shared by all three modules.
- `assets/js/operations.js` — view logic and demo-only storage.
- This handoff document.

## Preview for UI/UX review
1. Check out branch `frontend/inventory-orders-reports`.
2. From the project root run `php -S 127.0.0.1:8000` (or start Apache through XAMPP).
3. Open `http://127.0.0.1:8000/admin/login.php?demo=1` with `DEMO_MODE = true` for local testing.
4. Use the shared sidebar links for **Order Management**, **Inventory Management** and **Financial Reports**.
5. Try Add Material, Stock In/Out, New Order, Change Status and the period/CSV/print controls. Navigate between pages within the same tab to see changes reflected in Reports.

## Important cross-module boundaries
- Orders, Inventory and Reports share the demo storage key `sakura-operations-v1`.
- Reports also read the existing Payments demo storage key `sakura-demo-payments-v1` and the five known *fictitious* seeded payments to calculate collections on matching seeded orders.
- The current Payments page has **its own separately seeded order list**. New orders created on Order Management will appear in Reports but **will not automatically appear as payment choices** until the backend ties both modules to the same database.
- The Product Costing page still uses its own sample material list; changing stock here does not recalculate Product Costing until backend integration.
- Order Value sums non-cancelled orders. Payments Received sums known demo payments against selected non-cancelled orders. The month selector groups *orders by order date*; payments for those orders are counted regardless of the date the payment was recorded. The report is not audited accounting or profit.

## Backend implementation checklist
- [ ] Build normalized SQL tables: materials (ID, unit, cost, stock, reorder threshold), stock_movements (audit trail), orders, order_items, payments.
- [ ] Replace demo arrays/sessionStorage with authorized parameterized MySQL reads/writes via PHP endpoints.
- [ ] Require a real admin session for every endpoint, add CSRF protection and server-side validation, and enforce permissions.
- [ ] Stock out and order fulfillment must use database transactions, locking rows to prevent negative inventory and race conditions.
- [ ] Define how bill-of-materials consumption deducts material stock, and how cancelled orders affect stock and payment balances.
- [ ] Make Orders and Payments reference the SAME order table; never derive payment options from a second static seed list.
- [ ] Recalculate totals from authoritative rows, reconcile payment settlements/refunds, and decide accounting date and recognition policies.
- [ ] Use DECIMAL currency storage (or integer cents); store timestamps and audit who changed stock/order status.
- [ ] Add meaningful empty/loading/error states, role/permission checks and validation feedback from actual endpoints.
- [ ] Disable DEMO_MODE and seed-only data before any real deployment.

## Basic QA scenarios
- [ ] Empty state with demo off; add buttons disabled.
- [ ] Add material validation (duplicates, negative cost, non-integer counts).
- [ ] Stock-in updates totals; stock-out never makes quantity negative.
- [ ] Inventory low/out threshold and search/filter; CSV export.
- [ ] Create order requires name, product, positive quantity/price and valid date.
- [ ] View/change order status; search/filter; Cancelled excluded from sales value.
- [ ] Reports update after demo order/stock changes; period and CSV/print work.
- [ ] Reports collection includes seeded + Payments demo session payments on matching orders.
- [ ] Test desktop/mobile layout, keyboard modal interaction and browser storage failure.
- [ ] Full server/database/integration/security tests still **pending**.

## Merge note
Branch is based on current `integration/system-testing` to allow immediate preview, but **do not merge to main without team review**. Cherry-pick or review the six module files if integration has advanced.
