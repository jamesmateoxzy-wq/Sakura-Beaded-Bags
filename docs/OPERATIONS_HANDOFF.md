# Inventory, Orders & Reports — Frontend Handoff

## Scope / current status
Internal admin-only Sakura Beaded Bags prototype. This branch contains three functional **DEMO UI** modules, built on the shared login/dashboard/products/payments pages from `integration/system-testing`.

The pages **do not use MySQL**, do not save real orders or stock, and do not produce official financial statements. Fictitious demo data and edits are stored in `sessionStorage` for the current browser tab only. With DEMO_MODE or USE_SAMPLE_DATA off, the UI shows empty results and prevents edits.

## Files
- `admin/inventory.php` — inventory summary, material table, history, low-stock alerts, Stock-In and stock-correction forms.
- `admin/orders.php` — FIFO order cards and list, New Order with a bill of materials, nested inventory-material selection, and order detail actions.
- `admin/reports.php` — reference-style revenue/expense/profit summary, monthly comparisons, expense analysis, date range, CSV and PDF export.
- `assets/css/inventory.css` / `assets/js/inventory.js` — reference-matched inventory layout and demo stock movements.
- `assets/css/orders.css` / `assets/js/orders.js` — order queue, BOM and order-dialog workflows.
- `assets/css/reports.css` / `assets/js/reports.js` — financial reports and demo calculations.
- This handoff document.

## Preview for UI/UX review
1. Check out branch `frontend/inventory-orders-reports`.
2. From the project root run `php -S 127.0.0.1:8000` (or start Apache through XAMPP).
3. Open `http://127.0.0.1:8000/admin/login.php?demo=1` with `DEMO_MODE = true` for local testing.
4. Use the shared sidebar links for **Order Management**, **Inventory Management** and **Financial Reports**.
5. Try Stock-In and Stock Correction, New Order, Change Status and the period/CSV/print controls. Navigate between pages within the same tab to see changes reflected in Reports.

## Important cross-module boundaries
- Orders, Inventory and Reports share the demo storage key `sakura-operations-v1`.
- Order Management stores each demo order's bill of materials with the order. The Add Material dialog reads demo inventory and warns about low remaining stock, but does not deduct stock; allocation and inventory updates need a transactional backend workflow.
- Inventory Stock-In and stock corrections update demo material balances and history in the current browser tab. They do not update a production database.
- The Inventory Management page uses the supplied five-row viewport over the demo material list; search can find the remaining seeded materials.
- The Product Costing page still uses its own sample material list; changing stock here does not recalculate Product Costing until backend integration.
- Financial Reports groups non-cancelled demo orders by order date. Its monthly expense breakdown is illustrative; the report is not audited accounting or actual profit.

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
- [ ] Stock-In requires a valid material, positive whole quantity, valid unit cost and date; balance and history update together.
- [ ] Stock Correction rejects negative balances and values above the supported stock limit.
- [ ] Inventory low-stock threshold, search, Stock-In and Stock Correction.
- [ ] Create order requires name, product, positive quantity/price and valid date.
- [ ] Create an order with a selected product and BOM; check unit cost, total and downpayment calculations.
- [ ] Add/remove BOM materials, reject quantities above stock, and show a low-stock warning below the reorder point.
- [ ] View order details and BOM; archive or cancel orders; search/filter; Cancelled excluded from sales value.
- [ ] Reports update after demo order changes; date range, chart interval, CSV and PDF export work.
- [ ] Test desktop/mobile layout, keyboard modal interaction and browser storage failure.
- [ ] Full server/database/integration/security tests still **pending**.

## Merge note
Branch is based on current `integration/system-testing` to allow immediate preview, but **do not merge to main without team review**. Cherry-pick or review the six module files if integration has advanced.
