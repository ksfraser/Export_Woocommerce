# Patch 002: source_updated_at + Upsert API for Staging Module

**Apply to:** `ksf_FA_ImportStagingProcessing`

**Goal:** Allow source modules (WooCommerce, Square, etc.) to detect and re-stage changed data. WooCommerce orders have `date_modified`; when a re-import finds newer data, the staging module should update the existing record instead of throwing `DuplicateTransactionException`.

---

## 1. SQL Schema — `sql/install.sql`

### staging_customers — add `source_updated_at`

After `source_customer_id` column (line 12), add:

```sql
source_updated_at DATETIME DEFAULT NULL COMMENT 'Last modified timestamp from source system',
```

### staging_transactions — add `source_updated_at`

After `source_payment_id` column (line 43), add:

```sql
source_updated_at DATETIME DEFAULT NULL COMMENT 'Last modified timestamp from source system',
```

### staging_payments — add `source_updated_at`

After `staging_transaction_id` column, add:

```sql
source_updated_at DATETIME DEFAULT NULL COMMENT 'Last modified timestamp from source system',
```

---

## 2. Model: `src/Models/StagingTransaction.php`

### A) Add property (after line 29, before `$createdAt`)

```php
    private ?\DateTimeInterface $sourceUpdatedAt;
```

### B) Initialize in constructor (after `$this->errorLog = null;`)

```php
        $this->sourceUpdatedAt = null;
```

### C) Add getter/setter (after `setErrorLog`)

```php
    public function getSourceUpdatedAt(): ?\DateTimeInterface { return $this->sourceUpdatedAt; }
    public function setSourceUpdatedAt(?\DateTimeInterface $dt): void { $this->sourceUpdatedAt = $dt; }
```

### D) Add to `toArray()` (before `'status'`)

```php
            'source_updated_at' => $this->sourceUpdatedAt ? $this->sourceUpdatedAt->format('Y-m-d H:i:s') : null,
```

### E) Add to `fromArray()` (before `if (isset($data['status']))`)

```php
        if (isset($data['source_updated_at'])) $txn->setSourceUpdatedAt(new \DateTimeImmutable($data['source_updated_at']));
```

---

## 3. Model: `src/Models/StagingCustomer.php`

### A) Add property (after `$country`, before `$rawJson`)

```php
    private ?\DateTimeInterface $sourceUpdatedAt;
```

### B) Initialize in constructor

```php
        $this->sourceUpdatedAt = null;
```

### C) Add getter/setter

```php
    public function getSourceUpdatedAt(): ?\DateTimeInterface { return $this->sourceUpdatedAt; }
    public function setSourceUpdatedAt(?\DateTimeInterface $dt): void { $this->sourceUpdatedAt = $dt; }
```

### D) Add to `toArray()` (before `'raw_json'`)

```php
            'source_updated_at' => $this->sourceUpdatedAt ? $this->sourceUpdatedAt->format('Y-m-d H:i:s') : null,
```

### E) Add to `fromArray()` (before `if (isset($data['raw_json']))`)

```php
        if (isset($data['source_updated_at'])) $customer->setSourceUpdatedAt(new \DateTimeImmutable($data['source_updated_at']));
```

---

## 4. Model: `src/Models/StagingPayment.php`

(Same pattern — add `sourceUpdatedAt` property, constructor init, getter/setter, toArray/fromArray entries.)

---

## 5. DAO: `src/DAO/StagingTransactionDAO.php`

### A) Update `insert()` — add `source_updated_at` to the INSERT

Change the INSERT column list (line 61-65) to include `source_updated_at`:

```php
        $sql = "INSERT INTO {$this->tableName} 
            (source, source_transaction_id, source_order_id, source_payment_id,
             source_updated_at, transaction_date,
             total_amount, tax_amount, tip_amount, discount_amount, shipping_amount, currency,
             customer_name, customer_email, customer_id, raw_json, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
```

Add the parameter (after `$transaction->getSourcePaymentId()`):

```php
            $transaction->getSourceUpdatedAt() ? $transaction->getSourceUpdatedAt()->format('Y-m-d H:i:s') : null,
```

### B) Add `updateBySource()` method

Add after `updateStatus()` (after line 142):

```php
    public function updateBySource(string $source, string $sourceTransactionId, array $data): void
    {
        $fields = [];
        $params = [];
        $fieldMap = [
            'source_order_id' => 'source_order_id',
            'source_payment_id' => 'source_payment_id',
            'source_updated_at' => 'source_updated_at',
            'transaction_date' => 'transaction_date',
            'total_amount' => 'total_amount',
            'tax_amount' => 'tax_amount',
            'tip_amount' => 'tip_amount',
            'discount_amount' => 'discount_amount',
            'shipping_amount' => 'shipping_amount',
            'currency' => 'currency',
            'customer_name' => 'customer_name',
            'customer_email' => 'customer_email',
            'customer_id' => 'customer_id',
            'raw_json' => 'raw_json',
            'status' => 'status',
        ];
        foreach ($fieldMap as $key => $column) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if ($key === 'source_updated_at' && $value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                }
                if ($key === 'transaction_date' && $value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d');
                }
                $fields[] = "{$column} = ?";
                $params[] = $value;
            }
        }
        if (empty($fields)) {
            return;
        }
        $params[] = $source;
        $params[] = $sourceTransactionId;
        $sql = "UPDATE {$this->tableName} SET " . implode(', ', $fields)
            . " WHERE source = ? AND source_transaction_id = ?";
        $this->db->query($sql, $params);
    }
```

---

## 6. DAO: `src/DAO/StagingCustomerDAO.php`

### A) Update `insert()` — add `source_updated_at` to INSERT

Change column list to include `source_updated_at` and add parameter (same pattern as transaction DAO).

### B) Add `updateBySource()` method

Same pattern as transaction DAO but using `source_customer_id` and customer-specific field map.

---

## 7. Interface: `src/Contracts/StagingManagerInterface.php`

Add after `stageTransaction()` (line 13):

```php
    public function stageOrUpdateTransaction(array $data, string $source): StagingTransaction;

    public function stageOrUpdateCustomer(array $data, string $source): StagingCustomer;
```

---

## 8. Service: `src/Services/StagingService.php`

### A) Implement `stageOrUpdateTransaction()`

Add after `stageTransaction()` (after line 91):

```php
    public function stageOrUpdateTransaction(array $data, string $source): StagingTransaction
    {
        $this->validateSource($source);
        $transaction = StagingTransaction::fromArray(array_merge($data, ['source' => $source]));
        $validation = $this->transactionValidator->validate($transaction->toArray());
        if (!$validation->isSuccess()) {
            throw \Ksfraser\ImportStaging\Exceptions\StagingException::validationFailed($validation->getErrors());
        }

        $sourceTxnId = $transaction->getSourceTransactionId();
        if ($sourceTxnId) {
            $existing = $this->transactionDAO->findBySource($source, $sourceTxnId);
            if ($existing) {
                $sourceUpdated = $transaction->getSourceUpdatedAt();
                $existingUpdated = $existing->getSourceUpdatedAt();
                $isNewer = $sourceUpdated !== null
                    && ($existingUpdated === null || $sourceUpdated > $existingUpdated);

                if (!$isNewer) {
                    throw DuplicateTransactionException::forSource($source, $sourceTxnId);
                }

                $this->transactionDAO->updateBySource($source, $sourceTxnId, $transaction->toArray());
                $this->logDAO->log('transaction', $existing->getId(), 'updated', $source, [
                    'source_updated_at' => $sourceUpdated->format('Y-m-d H:i:s'),
                ]);
                $updated = $this->transactionDAO->findBySource($source, $sourceTxnId);
                return $updated ?? $transaction;
            }
        }

        $id = $this->transactionDAO->insert($transaction);
        $this->logDAO->log('transaction', $id, 'staged', $source);
        return $transaction;
    }
```

### B) Implement `stageOrUpdateCustomer()`

Add after `stageOrUpdateTransaction()`:

```php
    public function stageOrUpdateCustomer(array $data, string $source): StagingCustomer
    {
        $this->validateSource($source);
        $customer = StagingCustomer::fromArray(array_merge($data, ['source' => $source]));
        $validation = $this->customerValidator->validate($customer->toArray());
        if (!$validation->isSuccess()) {
            throw \Ksfraser\ImportStaging\Exceptions\StagingException::validationFailed($validation->getErrors());
        }

        $sourceCustId = $customer->getSourceCustomerId();
        if ($sourceCustId) {
            $existing = $this->customerDAO->findBySource($source, $sourceCustId);
            if ($existing) {
                $sourceUpdated = $customer->getSourceUpdatedAt();
                $existingUpdated = $existing->getSourceUpdatedAt();
                $isNewer = $sourceUpdated !== null
                    && ($existingUpdated === null || $sourceUpdated > $existingUpdated);

                if (!$isNewer) {
                    throw \Ksfraser\ImportStaging\Exceptions\DuplicateTransactionException::forSource($source, $sourceCustId);
                }

                $this->customerDAO->updateBySource($source, $sourceCustId, $customer->toArray());
                $this->logDAO->log('customer', $existing->getId(), 'updated', $source, [
                    'source_updated_at' => $sourceUpdated->format('Y-m-d H:i:s'),
                ]);
                $updated = $this->customerDAO->findBySource($source, $sourceCustId);
                return $updated ?? $customer;
            }
        }

        $id = $this->customerDAO->insert($customer);
        $this->logDAO->log('customer', $id, 'staged', $source);
        return $customer;
    }
```

---

## Usage from a source module (e.g., WooCommerce)

```php
$result = hook_invoke('ksf_FA_ImportStagingProcessing', 'respondToCapabilityRequest', [
    'request' => 'staging:stageOrUpdateTransaction',
    'source'  => 'woocommerce',
    'data'    => [
        'source_transaction_id' => (string)$order['id'],
        'source_order_id'       => (string)$order['id'],
        'source_updated_at'     => $order['date_modified'],  // e.g. '2026-05-25T14:30:00'
        'total_amount'          => (float)$order['total'],
        'transaction_date'      => substr($order['date_created'], 0, 10),
        'customer_name'         => $order['billing']['first_name'] . ' ' . $order['billing']['last_name'],
        'customer_email'        => $order['billing']['email'],
        'raw_json'              => json_encode($order),
        // ...
    ],
]);
```

On first import: inserts as "staged".
On re-import with newer `date_modified`: updates existing record, logs "updated".
On re-import with same/older `date_modified`: throws `DuplicateTransactionException` (caught and skipped by the source module).
