<?php
/**
 * WooCommerce Sync UI Entry Point
 * 
 * Single UI for all import/export operations with staging review.
 * Include this from FrontAccounting or call via cron.
 * 
 * @since 1.0.0
 */

require_once __DIR__ . '/tests/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

use Ksfraser\Frontaccounting\Woocommerce\ProductExportService;
use Ksfraser\Frontaccounting\Woocommerce\OrderExporter;
use Ksfraser\Frontaccounting\Woocommerce\CustomerExporter;
use Ksfraser\Frontaccounting\Woocommerce\CategoryExporter;
use Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging;
use Ksfraser\Frontaccounting\Woocommerce\UI\ImportExportDispatcher;

// Initialize dependencies (simplified - use DI container in production)
$restClient = new \Ksfraser\Frontaccounting\Woocommerce\WooRestClient(
    getenv('WC_URL') ?: 'https://example.com/wp-json/wc/v3/',
    getenv('WC_CONSUMER_KEY') ?: '',
    getenv('WC_CONSUMER_SECRET') ?: ''
);

$db = new \Ksfraser\Frontaccounting\Woocommerce\MysqliDatabase(
    getenv('DB_HOST') ?: 'localhost',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    getenv('DB_NAME') ?: 'frontaccounting',
    getenv('DB_PREFIX') ?: '0_'
);

$logger = new \Ksfraser\Frontaccounting\Woocommerce\FileLogger(__DIR__ . '/logs/sync.log');

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $result = $dispatcher->dispatch($_POST['action'], $_POST);
        echo "<div class='notice'>Action completed. Result: <pre>" . print_r($result, true) . "</pre></div>\n";
    }
    
    if (isset($_POST['import_staged'])) {
        $stagingId = (int)$_POST['staging_id'];
        $selectedDebtor = !empty($_POST['match_' . $stagingId]) && $_POST['match_' . $stagingId] !== 'new' 
            ? (int)$_POST['match_' . $stagingId] 
            : null;
        
        $result = $customerStaging->importCustomer($stagingId, $selectedDebtor);
        echo "<div class='notice'>Customer imported. Result: <pre>" . print_r($result, true) . "</pre></div>\n";
    }
}

// Render UI
?>
<!DOCTYPE html>
<html>
<head>
    <title>WooCommerce Sync</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { padding: 8px 16px; margin: 5px; cursor: pointer; }
        .btn-primary { background: #0073aa; color: white; border: none; }
        .notice { padding: 10px; margin: 10px 0; background: #f0f8ff; border: 1px solid #0073aa; }
    </style>
</head>
<body>
    <h1>WooCommerce Sync</h1>
    
    <h2>Actions</h2>
    <form method="POST">
        <button type="submit" name="action" value="export_products" class="btn btn-primary">Export Products</button>
        <button type="submit" name="action" value="export_categories" class="btn btn-primary">Export Categories</button>
        <button type="submit" name="action" value="import_orders" class="btn btn-primary">Import Orders</button>
        <button type="submit" name="action" value="import_customers" class="btn btn-primary">Import Customers (Stage)</button>
        <button type="submit" name="action" value="sync_all" class="btn btn-primary">Sync All</button>
    </form>
    
    <h2>Staged Customers</h2>
    <?php $dispatcher->renderStagingUI(); ?>
</body>
</html>
