<?php
namespace Ksfraser\frontaccounting\Woocommerce\Staging;

use Ksfraser\frontaccounting\Woocommerce\DatabaseInterface;
use Ksfraser\frontaccounting\Woocommerce\LoggerInterface;
use Ksfraser\frontaccounting\Woocommerce\DTO\OrderDTO;

/**
 * Order Staging Service
 * 
 * Stages WooCommerce orders for import, linking to staged/found customers.
 * Orders are staged when:
 * - Customer is not yet matched/created in FA
 * - Order needs review before import
 * - Line items reference products not yet synced
 * 
 * @since 1.0.0
 */
class OrderStaging
{
    private $db;
    private $logger;
    
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

    /**
     * Stage a WooCommerce order for later import
     * 
     * @since 1.0.0
     * @param array $wooOrder WooCommerce order data
     * @param int|null $stagedCustomerId Link to customer staging record
     * @return int Staging record ID
     */
    public function stageOrder(array $wooOrder, ?int $stagedCustomerId = null): int
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

    /**
     * Link staged order to a customer (after customer is matched/created)
     * 
     * @since 1.0.0
     * @param int $stagingId
     * @param int $faDebtorNo
     * @param string $faBranchRef
     */
    public function linkCustomer(int $stagingId, int $faDebtorNo, string $faBranchRef): void
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

    /**
     * Get all staged orders pending import
     * 
     * @since 1.0.0
     * @return array
     */
    public function getStagedOrders(): array
    {
        $prefix = $this->db->getPrefix();
        return $this->db->query(sprintf(
            "SELECT * FROM %swoo_order_staging WHERE imported = 0 ORDER BY staged_at DESC",
            $prefix
        ));
    }

    /**
     * Get orders pending customer resolution
     * 
     * @since 1.0.0
     * @return array Orders where customer is not yet matched
     */
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

    /**
     * Get orders ready for import (customer linked)
     * 
     * @since 1.0.0
     * @return array
     */
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

    /**
     * Mark order as imported
     * 
     * @since 1.0.0
     * @param int $stagingId
     * @param int $faOrderNo
     */
    public function markImported(int $stagingId, int $faOrderNo): void
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

    /**
     * Mark order import as failed
     * 
     * @since 1.0.0
     * @param int $stagingId
     * @param string $error
     */
    public function markError(int $stagingId, string $error): void
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

    /**
     * Get payment details from WooCommerce order
     * 
     * WooCommerce REST API structure:
     * - payment_method: string (e.g., 'stripe', 'paypal')
     * - payment_method_title: string (e.g., 'Credit Card (Stripe)')
     * - transaction_id: string (gateway's transaction reference)
     * - date_paid: string|null (ISO 8601 date)
     * - total: string (order total)
     * - currency: string (currency code)
     * 
     * @since 1.0.0
     * @param array $wooOrder
     * @return array Payment details
     */
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

    /**
     * Stage multiple orders from WooCommerce
     * 
     * @since 1.0.0
     * @param array $wooOrders
     * @param array $customerStagingIds Map of email -> customer staging ID
     * @return array Staging IDs
     */
    public function stageOrders(array $wooOrders, array $customerStagingIds = []): array
    {
        $stagedIds = [];
        
        foreach ($wooOrders as $order) {
            $email = $order['billing']['email'] ?? '';
            $customerStagingId = $customerStagingIds[$email] ?? null;
            
            // Determine status based on customer availability
            $status = $customerStagingId ? self::STATUS_STAGED : self::STATUS_CUSTOMER_PENDING;
            
            $stagingId = $this->stageOrder($order, $customerStagingId);
            $stagedIds[] = $stagingId;
            
            // Update status if customer pending
            if ($status === self::STATUS_CUSTOMER_PENDING) {
                $this->updateStatus($stagingId, self::STATUS_CUSTOMER_PENDING);
            }
        }
        
        return $stagedIds;
    }

    /**
     * Update staging record status
     */
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

    /**
     * Create order staging table if not exists
     * 
     * @since 1.0.0
     */
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

    /**
     * Process pending orders (after customers are resolved)
     * 
     * @since 1.0.0
     * @param callable $processCallback Function to call for each order: fn(stagedOrder, faCustomerId, faBranchRef)
     * @return array Results
     */
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