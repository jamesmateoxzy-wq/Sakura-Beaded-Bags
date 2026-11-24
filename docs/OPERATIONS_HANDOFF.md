# Inventory, Orders & Reports — Frontend Handoff

## Scope / current status
Internal admin-only Sakura Beaded Bags system. Orders, Inventory, Product Costing, Payments & Sales, Dashboard, and Financial Reports use the shared MySQL database through authorized PHP APIs.

Sales are successful payment records, not submitted order totals. Product and material removal uses inactive flags to preserve order, costing, and inventory history.

## Files
- `admin/inventory.php` — inventory summary, material table, history, low-stock alerts, Stock-In and stock-correction forms.
- `admin/orders.php` — FIFO order cards and list, New Order with a bill of materials, nested inventory-material selection, and order detail actions.
- `admin/reports.php` — reference-style revenue/expense/profit summary, monthly comparisons, expense analysis, date range, CSV and PDF export.
- `assets/css/inventory.css` / `assets/js/inventory-live.js` — inventory UI and MySQL operations.
- `assets/css/orders.css` / `assets/js/orders-live.js` — order queue, BOM and MySQL workflows.
- `assets/css/reports.css` / `assets/js/reports-live.js` — payment-based financial reports.
- `admin/api/products.php` — product reads/writes and soft removal.
- `admin/api/inventory.php` — material reads/writes and soft removal.
- This handoff document.

## Local verification
1. Configure the existing database connection in `includes/db.local.php`.
2. On an existing database, back it up and apply the reviewed migrations listed in `database/README.md`.
3. Run the application with PHP/XAMPP and sign in as an authorized administrator.
4. Verify the order, inventory, product costing, payments, and report flows against the same MySQL database. No sample payment records are used.

## Important cross-module boundaries
- Orders, Inventory, Product Costing, Payments, and Reports use the same Sakura MySQL schema.
- Order submission does not record payment or increase Total Sales. Payment entry is separate, idempotent, and capped at the remaining amount.
- Voided/refunded payment records remain in history but are excluded from order balances, sales summaries, dashboard, and reports.
- Product and material removal marks records inactive; orders, product recipes, costing references, payments, and inventory movements remain intact.
- Reports use payment date for sales; production and operating costs are estimates, not audited accounting.

## Database setup
- Existing databases: back up, preview and apply `scripts/migrate_sales_soft_deletion.php` to add successful/cancelled/refunded payment states and material inactive flags. Do not import `database/schema.sql` over existing data.
- The migration preserves every payment/material row and updates the order-balance view to sum successful payments only.

## Basic QA scenarios
- [ ] Empty state when the database has no records; forms stay disabled until authorized API data loads.
- [ ] Stock-In requires a valid material, positive whole quantity, valid unit cost and date; balance and history update together.
- [ ] Stock Correction rejects negative balances and values above the supported stock limit.
- [ ] Inventory low-stock threshold, search, Stock-In and Stock Correction.
- [ ] Create order requires name, product, positive quantity/price and valid date.
- [ ] Create an order with a selected product and BOM; check unit cost, total and downpayment calculations.
- [ ] Add/remove BOM materials, reject quantities above stock, and show a low-stock warning below the reorder point.
- [ ] View order details and BOM; archive or cancel orders; search/filter; Cancelled excluded from sales value.
- [ ] Reports update after successful payments; date range, chart interval, CSV and print work.
- [ ] Product/material removal requires confirmation and leaves related history intact.
- [ ] Test desktop/mobile layout and keyboard modal interaction.
- [ ] Verify unpaid, partial, fully paid, duplicate-request, void, and refund flows against the configured MySQL database.
- [ ] Verify removal of unused and historically referenced products/materials preserves historical records.
