<?php
/**
 * Import WooCommerce Customers Admin Page
 * 
 * @since 1.0.0
 * @module woocommerce_sync
 */

$page_security = 'SA_WOOCOMMERCE_IMPORT';

require_once '../includes/api/load.inc';

$hooks = $GLOBALS['hooks'] ?? null;
if ($hooks && method_exists($hooks, 'get_services')) {
    $services = $hooks->get_services();
    $customerStaging = $services['customerStaging'];
    $customerExporter = $services['customerExporter'];
} else {
    $customerStaging = get_woo_customer_staging();
    $customerExporter = get_woo_customer_exporter();
}

$title = _('Import WooCommerce Customers');
$ajax = in_ajax();

if (!$ajax) {
    page_header($title);
}

// Handle staging import
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['stage'])) {
        // Stage customers from WooCommerce
        $limit = (int)($_POST['limit'] ?? 10);
        $wooCustomers = $customerExporter->listCustomers(['limit' => $limit]);
        
        $staged = 0;
        foreach ($wooCustomers as $wc) {
            try {
                $customerStaging->stageCustomer($wc);
                $staged++;
            } catch (\Exception $e) {
                // Log but continue
            }
        }
        $message = sprintf('Staged %d customers for review.', $staged);
    }
    
    if (isset($_POST['import_staged'])) {
        $stagingId = (int)($_POST['staging_id']);
        $debtorNo = !empty($_POST['match_' . $stagingId]) && $_POST['match_' . $stagingId] !== 'new' 
            ? (int)$_POST['match_' . $stagingId] : null;
        
        $result = $customerStaging->importCustomer($stagingId, $debtorNo);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $message = sprintf('Customer imported as Debtor #%d', $result['debtor_no']);
        }
    }
}

// Get staged customers with matches
$stagedCustomers = $customerStaging->getStagedCustomers();
$stagedWithMatches = [];
foreach ($stagedCustomers as $sc) {
    if (!$sc['imported']) {
        $matches = $customerStaging->findMatches($sc['id']);
        $sc['matches'] = $matches;
    }
    $stagedWithMatches[] = $sc;
}
?>

<div style="padding: 20px;">
    <h1><?php echo $title; ?></h1>
    
    <?php if ($message): display_notification($message); endif; ?>
    <?php if ($error): display_error($error); endif; ?>
    
    <h3><?php echo _('Stage Customers'); ?></h3>
    <form method="POST">
        <table>
            <tr>
                <td>Limit:</td>
                <td><input type="number" name="limit" value="10" min="1" max="100" /></td>
                <td><button type="submit" name="stage" class="button">Stage Customers</button></td>
            </tr>
        </table>
    </form>
    
    <h3><?php echo _('Staged Customers - Review & Import'); ?></h3>
    <table class="woo-sync-table">
        <tr>
            <th>Email</th>
            <th>Name</th>
            <th>Company</th>
            <th>Staged</th>
            <th>Match Score</th>
            <th>Action</th>
        </tr>
        <?php foreach ($stagedWithMatches as $sc): ?>
        <tr>
            <td><?php echo htmlspecialchars($sc['email']); ?></td>
            <td><?php echo htmlspecialchars(trim($sc['first_name'] . ' ' . $sc['last_name'])); ?></td>
            <td><?php echo htmlspecialchars($sc['company']); ?></td>
            <td><?php echo $sc['staged_at']; ?></td>
            <td>
                <?php if (!empty($sc['matches'])): ?>
                    <strong><?php echo $sc['matches'][0]['score']; ?></strong>
                    <br/>Match: <?php echo htmlspecialchars($sc['matches'][0]['name']); ?>
                <?php else: ?>
                    <em>No match</em>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!$sc['imported']): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="staging_id" value="<?php echo $sc['id']; ?>" />
                    <select name="match_<?php echo $sc['id']; ?>">
                        <option value="new">-- Create New --</option>
                        <?php foreach ($sc['matches'] ?? [] as $m): ?>
                        <option value="<?php echo $m['debtor_no']; ?>">
                            <?php echo htmlspecialchars($m['name'] . ' (Score: ' . $m['score'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="import_staged" class="button">Import</button>
                </form>
                <?php else: ?>
                <span style="color: green;">Imported #<?php echo $sc['fa_debtor_no']; ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($stagedWithMatches)): ?>
        <tr><td colspan="6">No staged customers.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php
if (!$ajax) {
    end_page();
}

function get_woo_customer_staging() {
    // Fallback implementation
}

function get_woo_customer_exporter() {
    // Fallback implementation
}