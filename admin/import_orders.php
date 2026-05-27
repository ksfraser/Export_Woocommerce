<?php
/**
 * Import WooCommerce Orders Admin Page
 * 
 * @since 1.0.0
 * @module woocommerce_sync
 */

$page_security = 'SA_WOOCOMMERCE_IMPORT';

require_once '../includes/api/load.inc';

$hooks = $GLOBALS['hooks'] ?? null;
if ($hooks && method_exists($hooks, 'get_services')) {
    $services = $hooks->get_services();
    $orderExporter = $services['orderExporter'];
} else {
    $orderExporter = get_woo_order_exporter();
}

$title = _('Import WooCommerce Orders');
$ajax = in_ajax();

if (!$ajax) {
    page_header($title);
}

// Handle import action
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    $limit = (int)($_POST['limit'] ?? 10);
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
}

// Get recent orders
$recentOrders = $orderExporter->getOrders(['per_page' => 10]);
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

function get_woo_order_exporter() {
    // ... similar fallback as public/index.php
}