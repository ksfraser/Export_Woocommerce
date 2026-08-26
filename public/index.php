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

$path_to_root = "../../..";
require_once $path_to_root . '/includes/session.inc';
add_access_extensions();
require_once dirname(__DIR__) . '/vendor/autoload.php';
include_once($path_to_root . '/modules/ksf_FA_Woocommerce/hooks.php');

$hooks = new hooks_ksf_FA_Woocommerce();
$services = $hooks->get_services();
$dispatcher = $services['dispatcher'];
$customerStaging = $services['customerStaging'];
$logger = $services['logger'];

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $params = $_POST;
        try {
            $result = $dispatcher->dispatch($_POST['action'], $params);
        } catch (\Exception $e) {
            $result = array('error' => $e->getMessage());
        }
        
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
            try {
                $result = $customerStaging->importCustomer($stagingId, $selectedDebtor);
            } catch (\Exception $e) {
                $result = array('error' => $e->getMessage());
            }
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
    page($title);
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
            $logFile = $GLOBALS['path_to_root'] . '/modules/ksf_FA_Woocommerce/logs/sync.log';
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