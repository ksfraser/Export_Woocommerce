-- WooCommerce Sync Database Schema
-- @since 1.0.0
-- Requires: FA 2.4+

-- Staging tables moved to ksf_FA_ImportStagingProcessing (generic module)
-- Local fallback staging tables are created on-demand by OrderStaging/CustomerStaging

-- Mapping table for synced customers (FA debtor_no -> WooCommerce customer ID)
CREATE TABLE IF NOT EXISTS `0_woo_customer_map` (
    `debtor_no` INT NOT NULL,
    `woo_customer_id` INT NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `last_synced` DATETIME DEFAULT NULL,
    PRIMARY KEY (`debtor_no`),
    INDEX `idx_woo_customer` (`woo_customer_id`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mapping table for synced products (FA stock_id -> WooCommerce product ID)
CREATE TABLE IF NOT EXISTS `0_woo_product_map` (
    `stock_id` VARCHAR(20) NOT NULL,
    `woo_product_id` INT NOT NULL,
    `woo_product_url` VARCHAR(500) DEFAULT NULL,
    `last_synced` DATETIME DEFAULT NULL,
    `sync_status` ENUM('pending', 'synced', 'error') DEFAULT 'pending',
    PRIMARY KEY (`stock_id`),
    INDEX `idx_woo_id` (`woo_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mapping table for synced categories
CREATE TABLE IF NOT EXISTS `0_woo_category_map` (
    `fa_category_id` INT NOT NULL,
    `woo_category_id` INT NOT NULL,
    `last_synced` DATETIME DEFAULT NULL,
    PRIMARY KEY (`fa_category_id`),
    INDEX `idx_woo_cat` (`woo_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sync log table for audit trail
CREATE TABLE IF NOT EXISTS `0_woo_sync_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sync_type` ENUM('product', 'category', 'order', 'customer') NOT NULL,
    `action` ENUM('export', 'import') NOT NULL,
    `reference_id` VARCHAR(50) DEFAULT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 1,
    `error_message` TEXT,
    `sync_data` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_type_action` (`sync_type`, `action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;