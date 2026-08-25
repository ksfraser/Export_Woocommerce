<?php
declare(strict_types=1);

namespace ksfraser\FrontAccounting\Woocommerce\Staging;

/**
 * Gateway to ISU staging — all staging operations go through ISU hooks.
 *
 * All staging operations go through ksf_FA_ImportStagingProcessing (ISU),
 * which is THE generic staging layer for all external partners.
 *
 * WooCommerce-specific data is stored in ISU's raw_json column.
 *
 * @package Ksfraser\FrontAccounting\Woocommerce\Staging
 * @since 1.2.0
 */
class IsuStagingGateway
{
    private const HOOK_MODULE = 'ksf_FA_ImportStagingProcessing';

    /**
     * Stage a WooCommerce customer via ISU hooks.
     *
     * @param array $customerData Customer data (source_customer_id, name, email, phone, address, etc.)
     * @return int ISU staging ID, or 0 on failure
     */
    public function stageCustomer(array $customerData): int
    {
        if (!function_exists('hook_invoke')) {
            return 0;
        }
        $data = [];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:stageCustomer',
            'source' => 'woocommerce',
            'customer' => $customerData,
        ]);
        if (!empty($data['success']) && isset($data['result'])) {
            $result = is_array($data['result']) ? $data['result'] : [];
            return (int)($result['id'] ?? $data['result'] ?? 0);
        }
        return 0;
    }

    /**
     * Get all staged customers.
     *
     * @param array $filters Optional filters (status, source, etc.)
     * @return array
     */
    public function getStagedCustomers(array $filters = []): array
    {
        if (!function_exists('hook_invoke')) {
            return [];
        }
        $params = array_merge(['source' => 'woocommerce'], $filters);
        $data = ['filters' => $params];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:getStagedCustomers',
            'filters' => $params,
        ]);
        return $data['result'] ?? [];
    }

    /**
     * Get a staged customer by ID.
     *
     * @param int $id ISU staging ID
     * @return array|null
     */
    public function getCustomerById(int $id): ?array
    {
        if (!function_exists('hook_invoke')) {
            return null;
        }
        $data = ['id' => $id, 'entity_type' => 'customer'];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:getById',
            'id' => $id,
            'entity_type' => 'customer',
        ]);
        return $data['result'] ?? null;
    }

    /**
     * Stage a WooCommerce order via ISU hooks using STAGE_ENTITY with DTO.
     *
     * @param array $orderData WooCommerce order data
     * @param array $lineItems Formatted line items for DTO
     * @return int ISU staging ID, or 0 on failure
     */
    public function stageOrder(array $orderData, array $lineItems = []): int
    {
        if (!function_exists('hook_invoke')) {
            return 0;
        }

        $dtoLineItems = [];
        foreach ($lineItems as $item) {
            $dtoLineItems[] = new \Ksfraser\StagingDto\StagingLineItem(
                'woocommerce',
                $item['source_id'] ?? '',
                $item['transaction_source_id'] ?? '',
                $item['sku'] ?? '',
                $item['name'] ?? '',
                $item['description'] ?? '',
                (int)($item['quantity'] ?? 1),
                (float)($item['unit_price'] ?? 0),
                (float)($item['discount'] ?? 0),
                (float)($item['tax'] ?? 0)
            );
        }

        $dto = new \Ksfraser\StagingDto\StagingOrder(
            'woocommerce',
            $orderData['source_order_id'] ?? '',
            (float)($orderData['total_amount'] ?? 0),
            $orderData['currency'] ?? 'USD',
            $orderData['status'] ?? 'staged',
            'card',
            $dtoLineItems,
            $orderData['customer_id'] ?? '',
            [],
            [],
            $orderData['created_at'] ?? ''
        );

        $data = $dto;
        hook_invoke(self::HOOK_MODULE, 'STAGE_ENTITY', $data);

        if (!empty($data['success']) && isset($data['result']['stagingId'])) {
            return (int)$data['result']['stagingId'];
        }
        return 0;
    }

    /**
     * Get a staged transaction by ID.
     *
     * @param int $id ISU staging ID
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        if (!function_exists('hook_invoke')) {
            return null;
        }
        $data = ['id' => $id, 'entity_type' => 'transaction'];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:getById',
            'id' => $id,
            'entity_type' => 'transaction',
        ]);
        return $data['result'] ?? null;
    }

    /**
     * Get staged transactions by status.
     *
     * @param string $status Status filter (e.g., 'staged', 'customer_pending', 'customer_matched', 'imported')
     * @param string|null $fromDate From date (Y-m-d)
     * @param string|null $toDate To date (Y-m-d)
     * @return array
     */
    public function getByStatus(
        string $status,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        if (!function_exists('hook_invoke')) {
            return [];
        }
        $filters = ['status' => $status, 'source' => 'woocommerce'];
        if ($fromDate !== null) {
            $filters['from_date'] = $fromDate;
        }
        if ($toDate !== null) {
            $filters['to_date'] = $toDate;
        }
        $data = ['filters' => $filters];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:getStagedTransactions',
            'filters' => $filters,
        ]);
        return $data['result'] ?? [];
    }

    /**
     * Get staged transactions (all statuses, no filter).
     *
     * @return array
     */
    public function getStagedOrders(): array
    {
        if (!function_exists('hook_invoke')) {
            return [];
        }
        $data = ['filters' => ['source' => 'woocommerce']];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:getStagedTransactions',
            'filters' => ['source' => 'woocommerce'],
        ]);
        return $data['result'] ?? [];
    }

    /**
     * Update staging record status.
     *
     * @param int $id ISU staging ID
     * @param string $status New status
     * @param array $extraFields Additional fields to update
     * @return void
     */
    public function updateStatus(int $id, string $status, array $extraFields = []): void
    {
        if (!function_exists('hook_invoke')) {
            return;
        }
        if (!empty($extraFields)) {
            $this->updateFields($id, $extraFields);
        }
        $data = ['id' => $id, 'status' => $status];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:updateStatus',
            'id' => $id,
            'status' => $status,
        ]);
    }

    /**
     * Update staging record fields.
     *
     * @param int $id ISU staging ID
     * @param array $fields Fields to update
     * @return void
     */
    public function updateFields(int $id, array $fields): void
    {
        if (!function_exists('hook_invoke')) {
            return;
        }
        $data = ['id' => $id, 'fields' => $fields, 'entity_type' => 'transaction'];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:updateFields',
            'id' => $id,
            'fields' => $fields,
            'entity_type' => 'transaction',
        ]);
    }

    /**
     * Get line items by ISU staging transaction ID.
     *
     * @param int $stagingId ISU staging transaction ID
     * @return array
     */
    public function getLineItems(int $stagingId): array
    {
        if (!function_exists('hook_invoke')) {
            return [];
        }
        $data = ['staging_id' => $stagingId];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:getItemsByTransaction',
            'staging_id' => $stagingId,
        ]);
        return $data['result'] ?? [];
    }

    /**
     * Get staging status counts grouped by status.
     *
     * @param string|null $source Source filter (e.g., 'woocommerce')
     * @return array [status => count]
     */
    public function getStatusCounts(?string $source = null): array
    {
        if (!function_exists('hook_invoke')) {
            return [];
        }
        $data = ['source' => $source];
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, [
            'request' => 'staging:getStatusCounts',
            'source' => $source,
        ]);
        return $data['result'] ?? [];
    }
}
