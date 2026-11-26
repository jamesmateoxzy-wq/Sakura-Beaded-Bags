-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2026 at 04:12 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sakura_beaded_bags`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Owner','Admin') NOT NULL DEFAULT 'Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(180) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_orders`
--

CREATE TABLE `customer_orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `production_status` enum('Pending','In Production','Ready','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `delivery_status` enum('Not Scheduled','For Delivery','In Transit','Delivered','Returned') NOT NULL DEFAULT 'Not Scheduled',
  `delivery_method` varchar(50) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(10) UNSIGNED NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `movement_id` int(10) UNSIGNED NOT NULL,
  `material_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `movement_type` enum('Stock In','Production Use','Correction','Return') NOT NULL,
  `quantity_change` decimal(12,2) NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `moved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `material_id` int(10) UNSIGNED NOT NULL,
  `material_name` varchar(120) NOT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity_on_hand` decimal(12,2) NOT NULL DEFAULT 0.00,
  `low_stock_threshold` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `estimated_unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `customization_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `history_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `status_type` enum('Production','Delivery') NOT NULL,
  `old_status` varchar(40) DEFAULT NULL,
  `new_status` varchar(40) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_submission_keys`
--

CREATE TABLE `order_submission_keys` (
  `request_key` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `order_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `amount` decimal(12,2) NOT NULL,
  `payment_status` enum('Successful','Cancelled','Refunded') NOT NULL DEFAULT 'Successful',
  `payment_method` enum('Cash','GCash','Bank Transfer','Other') NOT NULL,
  `reference_number` varchar(120) DEFAULT NULL,
  `payment_slip_path` varchar(500) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_refunds`
--

CREATE TABLE `payment_refunds` (
  `refund_id` int(10) UNSIGNED NOT NULL,
  `payment_id` int(10) UNSIGNED NOT NULL,
  `request_key` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `refund_percent` decimal(5,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_submission_keys`
--

CREATE TABLE `payment_submission_keys` (
  `request_key` char(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `payment_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(160) NOT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `labor_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `packaging_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `markup_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `down_payment_percent` decimal(5,2) NOT NULL DEFAULT 50.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_materials`
--

CREATE TABLE `product_materials` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `material_id` int(10) UNSIGNED NOT NULL,
  `quantity_required` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_order_balances`
-- (See below for the actual view)
--
CREATE TABLE `v_order_balances` (
`order_id` int(10) unsigned
,`customer_id` int(10) unsigned
,`order_date` timestamp
,`production_status` enum('Pending','In Production','Ready','Completed','Cancelled')
,`delivery_status` enum('Not Scheduled','For Delivery','In Transit','Delivered','Returned')
,`order_total` decimal(44,2)
,`amount_paid` decimal(34,2)
,`balance_due` decimal(45,2)
,`payment_status` varchar(8)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_product_costing`
-- (See below for the actual view)
--
CREATE TABLE `v_product_costing` (
`product_id` int(10) unsigned
,`product_name` varchar(160)
,`selling_price` decimal(12,2)
,`markup_percent` decimal(6,2)
,`down_payment_percent` decimal(5,2)
,`labor_cost` decimal(12,2)
,`packaging_cost` decimal(12,2)
,`other_cost` decimal(12,2)
,`material_cost` decimal(46,4)
,`total_cost` decimal(48,2)
,`suggested_selling_price` decimal(53,2)
,`required_down_payment` decimal(16,2)
);

-- --------------------------------------------------------

--
-- Structure for view `v_order_balances`
--
DROP TABLE IF EXISTS `v_order_balances`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_order_balances` AS
SELECT o.order_id, o.customer_id, o.order_date, o.production_status, o.delivery_status,
       COALESCE(item_totals.order_total, 0.00) AS order_total,
       COALESCE(receipts.total_paid, 0.00) AS amount_paid,
       GREATEST(COALESCE(item_totals.order_total, 0.00) - COALESCE(receipts.total_paid, 0.00), 0.00) AS balance_due,
       CASE
           WHEN COALESCE(item_totals.order_total, 0.00) = 0 THEN 'No Items'
           WHEN COALESCE(receipts.total_paid, 0.00) = 0 THEN 'Unpaid'
           WHEN COALESCE(receipts.total_paid, 0.00) < item_totals.order_total THEN 'Partial'
           ELSE 'Paid'
       END AS payment_status
FROM customer_orders AS o
LEFT JOIN (
    SELECT order_id, SUM(quantity * unit_price) AS order_total
    FROM order_items GROUP BY order_id
) AS item_totals ON item_totals.order_id = o.order_id
LEFT JOIN (
    SELECT p.order_id, SUM(p.amount - COALESCE(refunds.refunded_amount, 0)) AS total_paid
    FROM payments AS p
    LEFT JOIN (
        SELECT payment_id, SUM(amount) AS refunded_amount
        FROM payment_refunds GROUP BY payment_id
    ) AS refunds ON refunds.payment_id = p.payment_id
    WHERE p.payment_status = 'Successful'
    GROUP BY p.order_id
) AS receipts ON receipts.order_id = o.order_id;

-- --------------------------------------------------------

--
-- Structure for view `v_product_costing`
--
DROP TABLE IF EXISTS `v_product_costing`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_product_costing`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `p`.`selling_price` AS `selling_price`, `p`.`markup_percent` AS `markup_percent`, `p`.`down_payment_percent` AS `down_payment_percent`, `p`.`labor_cost` AS `labor_cost`, `p`.`packaging_cost` AS `packaging_cost`, `p`.`other_cost` AS `other_cost`, coalesce(`bom`.`material_cost`,0.00) AS `material_cost`, round(coalesce(`bom`.`material_cost`,0.00) + `p`.`labor_cost` + `p`.`packaging_cost` + `p`.`other_cost`,2) AS `total_cost`, round((coalesce(`bom`.`material_cost`,0.00) + `p`.`labor_cost` + `p`.`packaging_cost` + `p`.`other_cost`) * (1 + `p`.`markup_percent` / 100),2) AS `suggested_selling_price`, round(`p`.`selling_price` * `p`.`down_payment_percent` / 100,2) AS `required_down_payment` FROM (`products` `p` left join (select `pm`.`product_id` AS `product_id`,sum(`pm`.`quantity_required` * `m`.`unit_cost`) AS `material_cost` from (`product_materials` `pm` join `materials` `m` on(`m`.`material_id` = `pm`.`material_id`)) group by `pm`.`product_id`) `bom` on(`bom`.`product_id` = `p`.`product_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `fk_orders_customer` (`customer_id`),
  ADD KEY `fk_orders_admin` (`created_by`),
  ADD KEY `idx_order_queue` (`production_status`,`order_date`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `fk_exp_admin` (`recorded_by`),
  ADD KEY `idx_expense_date` (`expense_date`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `fk_mov_order` (`order_id`),
  ADD KEY `fk_mov_admin` (`recorded_by`),
  ADD KEY `idx_mov_material_date` (`material_id`,`moved_at`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`material_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `fk_oi_order` (`order_id`),
  ADD KEY `fk_oi_product` (`product_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `fk_hist_order` (`order_id`),
  ADD KEY `fk_hist_admin` (`changed_by`);

--
-- Indexes for table `order_submission_keys`
--
ALTER TABLE `order_submission_keys`
  ADD PRIMARY KEY (`request_key`),
  ADD KEY `fk_order_request_order` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `ux_payments_method_ref` (`payment_method`,`reference_number`),
  ADD KEY `fk_pay_admin` (`recorded_by`),
  ADD KEY `idx_pay_order_date` (`order_id`,`payment_date`);

--
-- Indexes for table `payment_refunds`
--
ALTER TABLE `payment_refunds`
  ADD PRIMARY KEY (`refund_id`),
  ADD UNIQUE KEY `uq_payment_refund_request` (`request_key`),
  ADD KEY `idx_refund_payment_date` (`payment_id`,`created_at`);

--
-- Indexes for table `payment_submission_keys`
--
ALTER TABLE `payment_submission_keys`
  ADD PRIMARY KEY (`request_key`),
  ADD KEY `fk_paykey_payment` (`payment_id`),
  ADD KEY `idx_paykey_created` (`created_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `uq_products_sku` (`sku`),
  ADD KEY `idx_products_category` (`category_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_product_categories_name` (`category_name`);

--
-- Indexes for table `product_materials`
--
ALTER TABLE `product_materials`
  ADD PRIMARY KEY (`product_id`,`material_id`),
  ADD KEY `fk_pm_material` (`material_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_orders`
--
ALTER TABLE `customer_orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `movement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `material_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `history_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_refunds`
--
ALTER TABLE `payment_refunds`
  MODIFY `refund_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD CONSTRAINT `fk_orders_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_exp_admin` FOREIGN KEY (`recorded_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_mov_admin` FOREIGN KEY (`recorded_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mov_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`material_id`),
  ADD CONSTRAINT `fk_mov_order` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`order_id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `fk_hist_admin` FOREIGN KEY (`changed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hist_order` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_submission_keys`
--
ALTER TABLE `order_submission_keys`
  ADD CONSTRAINT `fk_order_request_order` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`order_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_admin` FOREIGN KEY (`recorded_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`order_id`);

--
-- Constraints for table `payment_refunds`
--
ALTER TABLE `payment_refunds`
  ADD CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`);

--
-- Constraints for table `payment_submission_keys`
--
ALTER TABLE `payment_submission_keys`
  ADD CONSTRAINT `fk_paykey_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`) ON UPDATE CASCADE;

--
-- Constraints for table `product_materials`
--
ALTER TABLE `product_materials`
  ADD CONSTRAINT `fk_pm_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`material_id`),
  ADD CONSTRAINT `fk_pm_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
