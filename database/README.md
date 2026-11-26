# Sakura Beaded Bags — Database Setup

This folder contains the **structure-only** MySQL/MariaDB schema for the SBB internal admin prototype. No sample customers, orders, payments, or inventory records are included. The exported structure includes 15 base tables and two views (`v_order_balances` and `v_product_costing`), with the current product-catalog and submission-key extensions already incorporated.

## Requirements

- Windows with XAMPP (PHP 8.0+ with PDO MySQL and MariaDB/MySQL), or an equivalent PHP/MySQL setup.
- The PHP project files from this repository, including `includes/db.php` and `includes/db.local.php.example`.

## Fresh installation (recommended)

1. Start **Apache** and **MySQL** in XAMPP, or start MySQL and run PHP's built-in local development server.
2. Open phpMyAdmin, create an **empty** database named `sakura_beaded_bags`, using `utf8mb4_unicode_ci` collation when available.
3. Select the newly created database, click **Import**, choose `database/schema.sql`, and click **Go**. Import into an empty database only; this file is not a migration or an overwrite-safe backup.
4. In your project's PowerShell terminal, create your private DB settings by copying the template:

   ```powershell
   Copy-Item includes/db.local.php.example includes/db.local.php
   ```

   Edit `includes/db.local.php` to match your local MySQL host, port, username and password. **Never commit this file**; `.gitignore` excludes it.
5. Verify the PHP database connection:

   ```powershell
   & "C:\xampp\php\php.exe" scripts/check_db.php
   ```

   The script should report `SUCCESS: PHP connected to MySQL!` and 14 base tables.
6. Optionally seed the three starter product categories (the schema contains the table but no category rows):

   ```powershell
   & "C:\xampp\php\php.exe" scripts/migrate_product_catalog.php --apply
   ```

   On a freshly imported schema, the script should only add the starter category rows; it also checks that required product-catalog structures exist.
7. Start the local app from the repository root:

   ```powershell
   & "C:\xampp\php\php.exe" -S 127.0.0.1:8000
   ```

   Open `http://127.0.0.1:8000/admin/login.php`. The current `DEMO_MODE` setting permits a **localhost-only** preview, and Google sign-in is not yet configured.

## Existing databases

**Do not import `schema.sql` into a database already containing SBB tables or data.** If updating an older database, back it up and use the relevant migration scripts after reviewing what they will change:

- `scripts/migrate_product_catalog.php`
- `scripts/migrate_order_requests.php`
- `scripts/migrate_payment_requests.php`
- `scripts/migrate_sales_soft_deletion.php`
- `scripts/migrate_partial_refunds.php`

The sales/soft-deletion migration adds `payments.payment_status` (existing payment rows default to `Successful`), adds `materials.is_active` (existing materials remain active), and updates `v_order_balances` to count only successful payments. Preview it with `php scripts/migrate_sales_soft_deletion.php`; apply it only after backing up with `php scripts/migrate_sales_soft_deletion.php --apply`. The script is re-runnable and does not delete records.

The supplied fresh-install schema already includes these structural changes, so rerunning them is not needed for new installations, except optionally to seed the three product categories.

The partial-refund migration adds an auditable `payment_refunds` table and updates `v_order_balances` to subtract recorded refunds from successful payments. Preview with `php scripts/migrate_partial_refunds.php`; after backing up, apply with `php scripts/migrate_partial_refunds.php --apply`. The migration is safe to rerun and does not change existing payment records.

## Notes

- The two views use `SQL SECURITY INVOKER` instead of pinning a view to the exporting machine's `root@localhost` account. The database user must have permission to read the referenced tables.
- This export includes `v_order_balances` and `v_product_costing`, but **not** `v_low_stock` or `v_order_profit` from older schema notes. Add those separately only if a feature requires them.
- No business data is included, so the dashboard will initially show zero totals until you add materials, products, and orders.
- **Before public deployment:** disable `DEMO_MODE`, configure and verify real authentication, restrict database permissions, and complete a security review. This setup is for local academic development, not a production deployment guide.
