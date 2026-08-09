#!/usr/bin/env php
<?php
/**
 * Cron script for WooCommerce sync
 * 
 * Usage:
 *   php cron_sync.php [action]
 * 
 * Actions:
 *   export_products   - Export FA products to WooCommerce
 *   import_orders     - Import WooCommerce orders to FA
 *   import_customers  - Import WooCommerce customers (staging)
 *   sync_all         - Do all of the above
 * 
 * @since 1.0.0
 */

// Bootstrap
require_once __DIR__ . '/tests/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

use Ksfraser\Frontaccounting\Woocommerce\ProductExportService;
use Ksfraser\Frontaccounting\Woocommerce\OrderExporter;
use Ksfraser\Frontaccounting\Woocommerce\CustomerExporter;
use Ksfraser\Frontaccounting\Woocommerce\CategoryExporter;
use Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging;
use Ksfraser\Frontaccounting\Woocommerce\UI\ImportExportDispatcher;

// Configuration
$config = [
    'wc_url' => getenv('WC_URL') ?: 'https://example.com/wp-json/wc/v3/',
    'wc_key' => getenv('WC_CONSUMER_KEY') ?: '',
    'wc_secret' => getenv('WC_CONSUMER_SECRET') ?: '',
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_name' => getenv('DB_NAME') ?: 'frontaccounting',
    'db_prefix' => getenv('DB_PREFIX') ?: '0_'
];

// Create dependencies (simplified - in production use DI container)
$logger = new \Ksfraser\frontaccounting\Woocommerce\FileLogger(__DIR__ . '/logs/sync.log');

$wooClient = new \Automattic\WooCommerce\Client(
    $config['wc_url'],
    $config['wc_key'],
    $config['wc_secret']
);
$restClient = new \Ksfraser\Frontaccounting\Woocommerce\WooRestClient($wooClient, $logger);

$db = new \Ksfraser\Frontaccounting\Woocommerce\MysqliDatabase(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name'],
    $config['db_prefix']
);

// Create services
$productExporter = new ProductExportService($restClient, $logger, $db);
$orderExporter = new OrderExporter($restClient, $logger, $db);
$customerExporter = new CustomerExporter($restClient, $logger, $db);
$categoryExporter = new CategoryExporter($restClient, $logger, $db);
$customerStaging = new CustomerStaging($db, $logger);

$dispatcher = new ImportExportDispatcher(
    $productExporter,
    $orderExporter,
    $customerExporter,
    $categoryExporter,
    $customerStaging
);

// Parse action
$action = $argv[1] ?? 'sync_all';

echo "[" . date('Y-m-d H:i:s') . "] Starting: $action\n";

$result = $dispatcher->dispatch($action);

echo "[" . date('Y-m-d H:i:s') . "] Completed: $action\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

exit(0);
