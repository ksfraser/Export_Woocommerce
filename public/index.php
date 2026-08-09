<?php
/**
 * WooCommerce Sync Main Entry Point
 * 
 * Provides the main UI for WooCommerce import/export operations.
 * 
 * @since 1.0.0
 * @module ksf_FA_Woocommerce
 */

$page_security = 'SA_WOOCOMMERCE_SYNC';

require_once '../includes/api/load.inc';  // FA standard bootstrap

// Get services from hooks
$hooks = $GLOBALS['hooks'] ?? null;
if ($hooks && method_exists($hooks, 'get_services')) {
    $services = $hooks->get_services();
    $dispatcher = $services['dispatcher'];
    $customerStaging = $services['customerStaging'];
    $logger = $services['logger'];
} else {
    // Fallback for direct access
    $dispatcher = get_woo_dispatcher();
    $customerStaging = $dispatcher->customerStaging ?? null;
    $logger = null;
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $params = $_POST;
        $result = $dispatcher->dispatch($_POST['action'], $params);
        
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $message = sprintf('Action "%s" completed. Result: %s', 
                htmlspecialchars($_POST['action']), 
                json_encode($result)
            );
            if ($logger) {
                $logger->info('Action completed: ' . $_POST['action']);
            }
        }
    }
    
    // Handle staging import
    if (isset($_POST['import_staged'])) {
        $stagingId = (int)($_POST['staging_id'] ?? 0);
        $selectedDebtor = null;
        
        if (!empty($_POST['match_' . $stagingId]) && $_POST['match_' . $stagingId] !== 'new') {
            $selectedDebtor = (int)$_POST['match_' . $stagingId];
        }
        
        if ($stagingId > 0 && $customerStaging) {
            $result = $customerStaging->importCustomer($stagingId, $selectedDebtor);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $message = sprintf('Customer imported. Debtor #%d, Branch: %s', 
                    $result['debtor_no'], $result['branch_ref']);
            }
        }
    }
}

// Get staging data for display
$stagedCustomers = $dispatcher->getStagedCustomersForUI();

$title = _('WooCommerce Sync');

// Include FA header
$ajax = in_ajax();
if (!$ajax) {
    page_header($title);
}

// Display messages
if ($message) {
    display_notification($message);
}
if ($error) {
    display_error($error);
}
?>

<style>
.woo-sync-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
.woo-sync-actions { margin: 20px 0; }
.woo-sync-actions button { 
    padding: 10px 20px; 
    margin: 5px; 
    font-size: 14px; 
    cursor: pointer; 
}
.woo-sync-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
.woo-sync-table th, .woo-sync-table td { 
    border: 1px solid #ddd; 
    padding: 10px; 
    text-align: left; 
}
.woo-sync-table th { background-color: #f2f2f2; }
.woo-sync-match { max-width: 200px; }
</style>

<div class="woo-sync-container">
    <h1><?php echo $title; ?></h1>
    
    <h2><?php echo _('Actions'); ?></h2>
    <div class="woo-sync-actions">
        <form method="POST" style="display: inline;">
            <button type="submit" name="action" value="export_products" class="button">
                <?php echo _('Export Products to Woo'); ?>
            </button>
        </form>
        <form method="POST" style="display: inline;">
            <button type="submit" name="action" value="export_categories" class="button">
                <?php echo _('Export Categories'); ?>
            </button>
        </form>
        <form method="POST" style="display: inline;">
            <button type="submit" name="action" value="import_orders" class="button">
                <?php echo _('Import Orders'); ?>
            </button>
        </form>
        <form method="POST" style="display: inline;">
            <button type="submit" name="action" value="export_customers" class="button">
                <?php echo _('Export Customers to Woo'); ?>
            </button>
        </form>
        <form method="POST" style="display: inline;">
            <button type="submit" name="action" value="import_customers" class="button">
                <?php echo _('Stage Customers from Woo'); ?>
            </button>
        </form>
    </div>
    
    <h2><?php echo _('Staged Customers'); ?></h2>
    <?php $dispatcher->renderStagingUI(); ?>
    
    <h2><?php echo _('Sync Log'); ?></h2>
    <div style="background: #f5f5f5; padding: 10px; max-height: 200px; overflow-y: auto;">
        <pre style="font-size: 11px;"><?php 
            $logFile = $GLOBALS['path_to_root'] . '/modules/woocommerce_sync/logs/sync.log';
            if (file_exists($logFile)) {
                echo htmlspecialchars(file_get_contents($logFile));
            } else {
                echo _('No log entries yet.');
            }
        ?></pre>
    </div>
</div>

<?php
// Include FA footer
if (!$ajax) {
    end_page();
}

/**
 * Get dispatcher (fallback when hooks not available)
 */
function get_woo_dispatcher() {
    global $db_connections;
    $company = user_company();
    
    $config = (new \hooks_ksf_FA_Woocommerce())->get_woo_config();
    
    $logger = new \Ksfraser\Frontaccounting\Woocommerce\FileLogger(
        $GLOBALS['path_to_root'] . '/modules/ksf_FA_Woocommerce/logs/sync.log'
    );
    
    $wooClient = new \Automattic\WooCommerce\Client(
        $config['wc_url'],
        $config['wc_key'],
        $config['wc_secret']
    );
    $restClient = new \Ksfraser\Frontaccounting\Woocommerce\WooRestClient($wooClient, $logger);
    
    $dbInterface = new \Ksfraser\frontaccounting\Woocommerce\MysqliDatabase(
        $db_connections[$company]['host'],
        $db_connections[$company]['username'],
        $db_connections[$company]['password'],
        $db_connections[$company]['dbname'],
        $db_connections[$company]['tbpref']
    );
    
    $productExporter = new \Ksfraser\Frontaccounting\Woocommerce\ProductExportService($restClient, $logger, $dbInterface);
    $orderExporter = new \Ksfraser\Frontaccounting\Woocommerce\OrderExporter($restClient, $logger, $dbInterface);
    $customerExporter = new \Ksfraser\Frontaccounting\Woocommerce\CustomerExporter($restClient, $logger, $dbInterface);
    $categoryExporter = new \Ksfraser\Frontaccounting\Woocommerce\CategoryExporter($restClient, $logger, $dbInterface);
    $customerStaging = new \Ksfraser\Frontaccounting\Woocommerce\Staging\CustomerStaging($dbInterface, $logger);
    
    return new \Ksfraser\Frontaccounting\Woocommerce\UI\ImportExportDispatcher(
        $productExporter, $orderExporter, $customerExporter, $categoryExporter, $customerStaging, $logger
    );
}