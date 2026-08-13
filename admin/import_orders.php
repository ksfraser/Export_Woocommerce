<?php
/**
 * Import WooCommerce Orders Admin Page
 * 
 * @since 1.0.0
 * @module ksf_FA_Woocommerce
 */

$page_security = 'SA_WOOCOMMERCE_IMPORT';

require_once '../includes/api/load.inc';

$services = woo_services();
$orderExporter = $services['orderExporter'];

$title = _('Import WooCommerce Orders');
$ajax = in_ajax();

if (!$ajax) {
    page($title);
}

// Handle import action
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    $limit = (int)($_POST['limit'] ?? 10);
    try {
        $result = $orderExporter->importOrdersToFA(['limit' => $limit]);
        $message = sprintf('Imported %d orders. Errors: %d',
            $result['imported'] ?? 0,
            count($result['errors'] ?? [])
        );
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                display_error($err);
            }
        }
    } catch (\Exception $e) {
        display_error(_('Import failed: ') . $e->getMessage());
    }
}

// Handle staging action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage'])) {
    $limit = (int)($_POST['limit'] ?? 10);
    $services = woo_services();
    try {
        $result = $services['dispatcher']->dispatch('stage_orders', ['limit' => $limit]);
        if (isset($result['error'])) {
            display_error(_('Staging failed: ') . $result['error']);
        } else {
            $message = sprintf('Staged %d orders for review. Total fetched: %d',
                $result['staged'] ?? 0,
                $result['total'] ?? 0
            );
        }
    } catch (\Exception $e) {
        display_error(_('Staging failed: ') . $e->getMessage());
    }
}

// Get recent orders
$recentOrders = array();
try {
    $recentOrders = $orderExporter->getOrders(['per_page' => 10]);
} catch (\Exception $e) {
    display_error(_('Unable to fetch orders from WooCommerce: ') . $e->getMessage());
}
?>

<div style="padding: 20px;">
    <h1><?php echo $title; ?></h1>
    
    <?php if ($message): ?>
        <?php display_notification($message); ?>
    <?php endif; ?>
    
    <form method="POST">
        <table>
            <tr>
                <td><?php echo _('Number of orders to import:'); ?></td>
                <td>
                    <input type="number" name="limit" value="10" min="1" max="100" />
                </td>
                <td>
                    <button type="submit" name="import" class="button">
                        <?php echo _('Import Orders'); ?>
                    </button>
                </td>
                <td>
                    <button type="submit" name="stage" class="button">
                        <?php echo _('Stage Orders for Review'); ?>
                    </button>
                </td>
            </tr>
        </table>
    </form>
    
    <h3><?php echo _('Recent WooCommerce Orders'); ?></h3>
    <table class="woo-sync-table">
        <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Status</th>
        </tr>
        <?php foreach ($recentOrders as $order): ?>
        <tr>
            <td><?php echo $order['id']; ?></td>
            <td><?php echo date('Y-m-d H:i', strtotime($order['date_created'])); ?></td>
            <td><?php echo htmlspecialchars($order['billing']['first_name'] . ' ' . $order['billing']['last_name']); ?></td>
            <td><?php echo htmlspecialchars($order['total']); ?> <?php echo $order['currency']; ?></td>
            <td><?php echo $order['status']; ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentOrders)): ?>
        <tr>
            <td colspan="5"><?php echo _('No orders found.'); ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<?php
if (!$ajax) {
    end_page();
}