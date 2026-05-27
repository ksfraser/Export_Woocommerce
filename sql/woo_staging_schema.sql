-- WooCommerce Import Staging Tables
-- @since 1.0.0

-- Customer staging table
CREATE TABLE IF NOT EXISTS `0_woo_customer_staging` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `woo_customer_id` INT,
    `woo_order_id` INT,
    `email` VARCHAR(255),
    `phone` VARCHAR(50),
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `company` VARCHAR(255),
    `address1` VARCHAR(255),
    `address2` VARCHAR(255),
    `city` VARCHAR(100),
    `state` VARCHAR(100),
    `postcode` VARCHAR(20),
    `country` VARCHAR(100),
    `raw_data` TEXT,
    `imported` TINYINT DEFAULT 0,
    `imported_at` DATETIME NULL,
    `fa_debtor_no` INT NULL,
    `fa_branch_ref` VARCHAR(100) NULL,
    `staged_at` DATETIME,
    INDEX idx_email (email),
    INDEX idx_woo_customer (woo_customer_id),
    INDEX idx_imported (imported)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Order staging table (for future use)
CREATE TABLE IF NOT EXISTS `0_woo_order_staging` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `woo_order_id` INT,
    `woo_status` VARCHAR(50),
    `email` VARCHAR(255),
    `total` DECIMAL(15,4),
    `currency` VARCHAR(10),
    `raw_data` TEXT,
    `imported` TINYINT DEFAULT 0,
    `imported_at` DATETIME NULL,
    `fa_order_no` INT NULL,
    `staged_at` DATETIME,
    INDEX idx_woo_order (woo_order_id),
    INDEX idx_imported (imported)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
