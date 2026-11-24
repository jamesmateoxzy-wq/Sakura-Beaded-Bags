<?php
/**
 * Sakura Beaded Bags — Product catalog schema migration (CLI ONLY).
 *
 * Preview: C:\xampp\php\php.exe scripts\migrate_product_catalog.php
 * Apply:   C:\xampp\php\php.exe scripts\migrate_product_catalog.php --apply
 *
 * No data deletion. Re-runnable, even if an earlier run was interrupted.
 * Important: MySQL DDL auto-commits; back up the database before --apply.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command-line only.');
}

require_once __DIR__ . '/../includes/db.php';

$apply = in_array('--apply', $argv, true);
$actions = [];

try {
    $pdo = sbb_db();
    $schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($schema !== 'sakura_beaded_bags') {
        throw new RuntimeException('Unexpected database: ' . $schema . '. Expected sakura_beaded_bags.');
    }

    $tableExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND TABLE_TYPE = ?'
    );
    foreach (['products', 'materials', 'product_materials'] as $table) {
        $tableExists->execute([$schema, $table, 'BASE TABLE']);
        if ((int) $tableExists->fetchColumn() !== 1) {
            throw new RuntimeException('Required table missing: ' . $table . '. Stop and check the original schema.');
        }
    }

    $hasCategories = (function () use ($tableExists, $schema): bool {
        $tableExists->execute([$schema, 'product_categories', 'BASE TABLE']);
        return (int) $tableExists->fetchColumn() === 1;
    })();
    if (!$hasCategories) {
        $actions[] = [
            'Create product_categories table',
            'CREATE TABLE product_categories (
                category_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                category_name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_product_categories_name (category_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        ];
    }

    $columnExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    if ($hasCategories) {
        $categoryIdColumn = $pdo->prepare(
            'SELECT EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $categoryIdColumn->execute([$schema, 'product_categories', 'category_id']);
        $categoryIdExtra = $categoryIdColumn->fetchColumn();
        if ($categoryIdExtra === false) {
            throw new RuntimeException('product_categories.category_id is missing. Stop and check the existing schema.');
        }
        if (stripos((string) $categoryIdExtra, 'auto_increment') === false) {
            $primaryKey = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
            );
            $primaryKey->execute([$schema, 'product_categories', 'PRIMARY']);
            if ((int) $primaryKey->fetchColumn() !== 1) {
                throw new RuntimeException('product_categories.category_id must be a primary key before enabling auto-increment.');
            }
            $actions[] = [
                'Enable generated category IDs without changing existing IDs',
                'ALTER TABLE product_categories MODIFY category_id INT UNSIGNED NOT NULL AUTO_INCREMENT'
            ];
        }

        $indexExists = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $indexExists->execute([$schema, 'product_categories', 'uq_product_categories_name']);
        if ((int) $indexExists->fetchColumn() === 0) {
            $duplicate = $pdo->query(
                'SELECT category_name FROM product_categories
                 GROUP BY category_name HAVING COUNT(*) > 1 LIMIT 1'
            )->fetchColumn();
            if ($duplicate !== false) {
                throw new RuntimeException('Duplicate category names exist; resolve them before adding the unique category-name index.');
            }
            $actions[] = [
                'Prevent duplicate category names',
                'ALTER TABLE product_categories ADD UNIQUE INDEX uq_product_categories_name (category_name)'
            ];
        }
    }

    $columnExists->execute([$schema, 'products', 'sku']);
    $hasSku = (int) $columnExists->fetchColumn() === 1;
    if (!$hasSku) {
        $actions[] = ['Add products.sku', 'ALTER TABLE products ADD COLUMN sku VARCHAR(60) NULL AFTER product_name'];
    }

    $columnExists->execute([$schema, 'products', 'category_id']);
    $hasCategoryId = (int) $columnExists->fetchColumn() === 1;
    if (!$hasCategoryId) {
        $actions[] = ['Add products.category_id', 'ALTER TABLE products ADD COLUMN category_id INT UNSIGNED NULL AFTER sku'];
    }

    $indexExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $indexExists->execute([$schema, 'products', 'uq_products_sku']);
    if ((int) $indexExists->fetchColumn() === 0) {
        $actions[] = ['Add unique product SKU index', 'ALTER TABLE products ADD UNIQUE INDEX uq_products_sku (sku)'];
    }

    $indexExists->execute([$schema, 'products', 'idx_products_category']);
    if ((int) $indexExists->fetchColumn() === 0) {
        $actions[] = ['Add category lookup index', 'ALTER TABLE products ADD INDEX idx_products_category (category_id)'];
    }

    $fkExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?'
    );
    $fkExists->execute([$schema, 'products', 'fk_products_category', 'FOREIGN KEY']);
    if ((int) $fkExists->fetchColumn() === 0) {
        $actions[] = [
            'Add product-to-category foreign key',
            'ALTER TABLE products ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id)
                REFERENCES product_categories(category_id) ON DELETE RESTRICT ON UPDATE CASCADE'
        ];
    }

    // Seed category names independently. INSERT IGNORE preserves any existing categories.
    $actions[] = [
        'Ensure three starter categories exist',
        "INSERT IGNORE INTO product_categories (category_name) VALUES
           ('Signature collection'), ('Pink collection'), ('Pearl collection')"
    ];

    $productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    echo "Sakura Beaded Bags — Product Catalog Schema Migration\n";
    echo 'Database: ' . $schema . "\n";
    echo 'Existing products: ' . $productCount . "\n";
    echo $apply ? "MODE: APPLY CHANGES\n" : "MODE: PREVIEW ONLY, NO DATABASE CHANGES\n";
    echo "\n";

    foreach ($actions as $i => [$label, $sql]) {
        echo ($i + 1) . '. ' . $label;
        if ($apply) {
            $pdo->exec($sql);
            echo " — DONE\n";
        } else {
            echo " — PLANNED\n";
        }
    }

    if ($apply) {
        echo "\nSaved categories:\n";
        foreach ($pdo->query('SELECT category_id, category_name FROM product_categories ORDER BY category_id') as $cat) {
            echo '  ' . $cat['category_id'] . ': ' . $cat['category_name'] . "\n";
        }
        echo "\nSUCCESS: Product catalog schema is ready. Existing products and inventory were not deleted.\n";
    } else {
        echo "\nNothing changed. Back up sakura_beaded_bags before running with --apply.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "MIGRATION STOPPED: " . $e->getMessage() . "\n");
    fwrite(STDERR, "If you used --apply, some earlier DDL steps may have succeeded. After reviewing the error, the script can be re-run.\n");
    exit(1);
}
