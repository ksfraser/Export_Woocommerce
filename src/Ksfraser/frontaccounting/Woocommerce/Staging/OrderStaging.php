<?php
namespace ksfraser\FrontAccounting\Woocommerce\Staging;

use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;
use ksfraser\FrontAccounting\Woocommerce\DTO\OrderDTO;

class OrderStaging
{
    private $db;
    private $logger;

    private const HOOK_MODULE = 'ksf_FA_ImportStagingProcessing';

    public const STATUS_STAGED = 'staged';
    public const STATUS_CUSTOMER_PENDING = 'customer_pending';
    public const STATUS_CUSTOMER_MATCHED = 'customer_matched';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_ERROR = 'error';

    public function __construct(DatabaseInterface $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    private function callStagingHook(string $action, array $params = []): ?array
    {
        if (!function_exists('hook_invoke')) {
            return null;
        }
        $data = [];
        $params['request'] = 'staging:' . $action;
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, $params);
        if (!empty($data['success']) && isset($data['result'])) {
            return is_array($data['result']) ? $data['result'] : ['id' => $data['result']];
        }
        if (!empty($data['error'])) {
            $this->logger->warning('Staging hook error: ' . $data['error']);
        }
        return null;
    }

    public function stageOrder(array $wooOrder, ?int $stagedCustomerId = null): int
    {
        $billing = $wooOrder['billing'] ?? [];
        $customerName = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));

        $hookResult = $this->callStagingHook('stageTransaction', [
            'source' => 'woocommerce',
            'transaction' => [
                'source_transaction_id' => (string)($wooOrder['id'] ?? 0),
                'source_order_id' => (string)($wooOrder['id'] ?? 0),
                'total_amount' => (float)($wooOrder['total'] ?? 0),
                'currency' => $wooOrder['currency'] ?? 'USD',
                'customer_email' => $billing['email'] ?? '',
                'customer_name' => $customerName,
                'raw_json' => json_encode($wooOrder),
                'status' => self::STATUS_STAGED,
            ],
        ]);

        if ($hookResult !== null) {
            return (int)($hookResult['id'] ?? 0);
        }

        // Fallback: local staging table if ISU module is not installed
        return $this->stageOrderFallback($wooOrder, $stagedCustomerId);
    }

    private function stageOrderFallback(array $wooOrder, ?int $stagedCustomerId = null): int
    {
        $prefix = $this->db->getPrefix();
        $billing = $wooOrder['billing'] ?? [];

        $sql = sprintf(
            "INSERT INTO %swoo_order_staging
            (woo_order_id, woo_status, email, total, currency,
             raw_data, staged_at, customer_staging_id, status)
            VALUES (%d, '%s', '%s', '%s', '%s', '%s', NOW(), %s, '%s')",
            $prefix,
            (int)($wooOrder['id'] ?? 0),
            $this->db->escape($wooOrder['status'] ?? 'pending'),
            $this->db->escape($billing['email'] ?? ''),
            $this->db->escape((string)($wooOrder['total'] ?? 0)),
            $this->db->escape($wooOrder['currency'] ?? 'USD'),
            $this->db->escape(json_encode($wooOrder)),
            $stagedCustomerId ? (int)$stagedCustomerId : 'NULL',
            self::STATUS_STAGED
        );

        $this->db->execute($sql);

        $result = $this->db->query("SELECT LAST_INSERT_ID() as id");
        return (int)($result[0]['id'] ?? 0);
    }

    public function linkCustomer(int $stagingId, int $faDebtorNo, string $faBranchRef): void
    {
        $this->callStagingHook('updateStatus', [
            'id' => $stagingId,
            'status' => self::STATUS_CUSTOMER_MATCHED,
        ]);

        $this->linkCustomerFallback($stagingId, $faDebtorNo, $faBranchRef);
    }

    private function linkCustomerFallback(int $stagingId, int $faDebtorNo, string $faBranchRef): void
    {
        $prefix = $this->db->getPrefix();

        $sql = sprintf(
            "UPDATE %swoo_order_staging
             SET fa_debtor_no = %d, fa_branch_ref = '%s', status = '%s'
             WHERE id = %d",
            $prefix,
            $faDebtorNo,
            $this->db->escape($faBranchRef),
            self::STATUS_CUSTOMER_MATCHED,
            $stagingId
        );

        $this->db->execute($sql);
    }

    public function getStagedOrders(): array
    {
        $prefix = $this->db->getPrefix();
        return $this->db->query(sprintf(
            "SELECT * FROM %swoo_order_staging WHERE imported = 0 ORDER BY staged_at DESC",
            $prefix
        ));
    }

    public function getOrdersPendingCustomer(): array
    {
        $prefix = $this->db->getPrefix();
        return $this->db->query(sprintf(
            "SELECT * FROM %swoo_order_staging
             WHERE status IN ('%s', '%s') AND fa_debtor_no IS NULL
             ORDER BY staged_at DESC",
            $prefix,
            self::STATUS_STAGED,
            self::STATUS_CUSTOMER_PENDING
        ));
    }

    public function getOrdersReadyForImport(): array
    {
        $prefix = $this->db->getPrefix();
        return $this->db->query(sprintf(
            "SELECT * FROM %swoo_order_staging
             WHERE status = '%s' AND fa_debtor_no IS NOT NULL AND imported = 0
             ORDER BY staged_at ASC",
            $prefix,
            self::STATUS_CUSTOMER_MATCHED
        ));
    }

    public function markImported(int $stagingId, int $faOrderNo): void
    {
        $this->callStagingHook('updateStatus', [
            'id' => $stagingId,
            'status' => self::STATUS_IMPORTED,
        ]);

        $this->markImportedFallback($stagingId, $faOrderNo);
    }

    private function markImportedFallback(int $stagingId, int $faOrderNo): void
    {
        $prefix = $this->db->getPrefix();

        $sql = sprintf(
            "UPDATE %swoo_order_staging
             SET imported = 1, imported_at = NOW(), fa_order_no = %d, status = '%s'
             WHERE id = %d",
            $prefix,
            $faOrderNo,
            self::STATUS_IMPORTED,
            $stagingId
        );

        $this->db->execute($sql);
    }

    public function markError(int $stagingId, string $error): void
    {
        $this->callStagingHook('updateStatus', [
            'id' => $stagingId,
            'status' => self::STATUS_ERROR,
            'error' => $error,
        ]);

        $this->markErrorFallback($stagingId, $error);
    }

    private function markErrorFallback(int $stagingId, string $error): void
    {
        $prefix = $this->db->getPrefix();

        $sql = sprintf(
            "UPDATE %swoo_order_staging
             SET status = '%s'
             WHERE id = %d",
            $prefix,
            self::STATUS_ERROR,
            $stagingId
        );

        $this->db->execute($sql);

        $this->logger->error("Order staging {$stagingId} error: " . $error);
    }

    public function extractPaymentDetails(array $wooOrder): array
    {
        return [
            'method' => $wooOrder['payment_method'] ?? '',
            'method_title' => $wooOrder['payment_method_title'] ?? '',
            'transaction_id' => $wooOrder['transaction_id'] ?? '',
            'paid' => $wooOrder['date_paid'] ?? null,
            'amount' => $wooOrder['total'] ?? 0,
            'currency' => $wooOrder['currency'] ?? 'USD',
        ];
    }

    public function stageOrders(array $wooOrders, array $customerStagingIds = []): array
    {
        $stagedIds = [];

        foreach ($wooOrders as $order) {
            $email = $order['billing']['email'] ?? '';
            $customerStagingId = $customerStagingIds[$email] ?? null;

            $status = $customerStagingId ? self::STATUS_STAGED : self::STATUS_CUSTOMER_PENDING;

            $stagingId = $this->stageOrder($order, $customerStagingId);
            $stagedIds[] = $stagingId;

            if ($status === self::STATUS_CUSTOMER_PENDING) {
                $this->updateStatus($stagingId, self::STATUS_CUSTOMER_PENDING);
            }
        }

        return $stagedIds;
    }

    private function updateStatus(int $stagingId, string $status): void
    {
        $prefix = $this->db->getPrefix();
        $this->db->execute(sprintf(
            "UPDATE %swoo_order_staging SET status = '%s' WHERE id = %d",
            $prefix,
            $this->db->escape($status),
            $stagingId
        ));
    }

    public function ensureStagingTable(): void
    {
        $prefix = $this->db->getPrefix();
        $this->db->execute(sprintf(
            "CREATE TABLE IF NOT EXISTS %swoo_order_staging (
                id INT AUTO_INCREMENT PRIMARY KEY,
                woo_order_id INT NOT NULL,
                woo_status VARCHAR(50),
                email VARCHAR(255),
                total DECIMAL(15,4),
                currency VARCHAR(10),
                raw_data TEXT,
                customer_staging_id INT NULL,
                status ENUM('staged', 'customer_pending', 'customer_matched', 'imported', 'error') DEFAULT 'staged',
                imported TINYINT DEFAULT 0,
                imported_at DATETIME NULL,
                fa_order_no INT NULL,
                fa_debtor_no INT NULL,
                fa_branch_ref VARCHAR(100) NULL,
                staged_at DATETIME,
                INDEX idx_woo_order (woo_order_id),
                INDEX idx_customer_pending (status, fa_debtor_no),
                INDEX idx_imported (imported)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            $prefix
        ));
    }

    public function processPendingOrders(callable $processCallback): array
    {
        $orders = $this->getOrdersReadyForImport();
        $results = ['processed' => 0, 'errors' => []];

        foreach ($orders as $order) {
            $wooData = json_decode($order['raw_data'], true);

            try {
                $faOrderNo = $processCallback($wooData, $order['fa_debtor_no'], $order['fa_branch_ref']);
                $this->markImported($order['id'], $faOrderNo);
                $results['processed']++;
            } catch (\Exception $e) {
                $this->markError($order['id'], $e->getMessage());
                $results['errors'][] = $e->getMessage();
            }
        }

        return $results;
    }
}
