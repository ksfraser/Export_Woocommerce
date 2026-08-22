# Requirements Specification — ksf_FA_Woocommerce

> **Module Code**: WC
> **Version**: 2.4.3-0
> **Platform**: PHP 7.3 / FrontAccounting 2.4.19

---

## 1. Business Context

FrontAccounting (FA) needs to synchronize product catalog, orders, customers, and inventory with WooCommerce stores via REST API. The module provides bidirectional sync with a staging-first import workflow, sharing staging infrastructure with the Square module via ksf_FA_ImportStagingProcessing.

---

## 2. Functional Requirements

### FR-WC-001: Product Export (FA → WooCommerce)

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-001-001 | Export FA stock items to WooCommerce via REST API v3 `POST/PUT /products` | Must | Implemented |
| FR-WC-001-002 | Map FA `stock_id` to WooCommerce `sku` | Must | Implemented |
| FR-WC-001-003 | Map FA `description` to WooCommerce `name` and `description` | Must | Implemented |
| FR-WC-001-004 | Map FA `sales_price` to WooCommerce `regular_price` | Must | Implemented |
| FR-WC-001-005 | Map FA stock category to WooCommerce category via `0_woo_category_map` | Must | Implemented |
| FR-WC-001-006 | Export FA item images to WooCommerce `images` array | Should | Implemented |
| FR-WC-001-007 | Push FA QOH to WooCommerce stock quantity | Must | Implemented |
| FR-WC-001-008 | Support variable products (FA parent → WooCommerce variable) | Should | Implemented (legacy) |
| FR-WC-001-009 | Export weight/dimensions from FA to WooCommerce | Should | Implemented |
| FR-WC-001-010 | Set shipping class based on FA item properties | Could | Implemented (`hazardous`) |
| FR-WC-001-011 | Track sync status per item in `0_woo_product_map` | Must | Implemented |
| FR-WC-001-012 | Handle WooCommerce SKU duplicate errors gracefully | Must | Implemented |

**Code references:** `src/Ksfraser/FrontAccounting/Woocommerce/Export/ProductExporter.php`

---

### FR-WC-002: Order Import (WooCommerce → FA)

**Description**: Pull orders from WooCommerce and stage them through
ISU's staging infrastructure for review and processing into FA.

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-002-001 | Pull orders from WooCommerce via REST API v3 `GET /orders` | Must | Implemented |
| FR-WC-002-002 | Stage orders via `ksf_FA_ImportStagingProcessing_UI` hook interface (ISU as primary) | Must | Planned |
| FR-WC-002-003 | Support date range filtering for order pull | Must | Implemented |
| FR-WC-002-004 | Support pagination (100 orders per page) | Must | Implemented |
| FR-WC-002-005 | Map WooCommerce order line items to FA stock items | Must | Implemented |
| FR-WC-002-006 | Map WooCommerce billing/shipping to FA customer address | Should | Implemented |
| FR-WC-002-007 | Handle WooCommerce order statuses (processing, completed, on-hold) | Should | Implemented |
| FR-WC-002-008 | Create FA sales invoices from staged orders via ISU processing UI | Must | Via ISU |
| FR-WC-002-009 | Record WooCommerce order ID in staging for dedup | Must | Implemented |
| FR-WC-002-010 | Remove direct-import bypass (all orders stage first, then process via ISU) | Must | Planned |
| FR-WC-002-011 | Remove dormant local staging (`woo_order_staging` table, `stageOrderLegacy()`) | Should | Planned |
| FR-WC-002-012 | Fix lossy hook payload: include date, tax, discount, shipping, payment_id | Must | Planned |
| FR-WC-002-013 | Pass WooCommerce source config (GL/bank) to ISU at processing time | Must | Planned |

**Code references:** `src/Ksfraser/FrontAccounting/Woocommerce/Import/OrderExporter.php`, `src/Staging/OrderStaging.php`

---

### FR-WC-003: Customer Sync (Bidirectional)

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-003-001 | Pull WooCommerce customers via REST API v3 `GET /customers` | Must | Implemented |
| FR-WC-003-002 | Push FA debtors to WooCommerce as customers | Should | Implemented |
| FR-WC-003-003 | Match WooCommerce customers to FA debtors by email | Must | Implemented |
| FR-WC-003-004 | Fuzzy matching with scoring: EMAIL=30, PHONE=25, NAME=20, ADDRESS=15 | Must | Implemented |
| FR-WC-003-005 | Create new FA debtors from unmatched WooCommerce customers | Should | Implemented |
| FR-WC-003-006 | Store mapping in `0_woo_customer_map` | Must | Implemented |
| FR-WC-003-007 | Stage unmatched customers for review via ISU | Should | Implemented |
| FR-WC-003-008 | Support manual match/override in review UI | Should | Implemented |

**Code references:** `src/Ksfraser/FrontAccounting/Woocommerce/Import/CustomerExporter.php`, `src/Staging/CustomerStaging.php`, `src/Matching/CustomerMatcher.php`

---

### FR-WC-004: Event-Driven Item Sync

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-004-001 | Subscribe to FA `ItemEventPublisher` broadcasts | Must | Implemented |
| FR-WC-004-002 | Push item to WooCommerce on `item_created` event | Must | Implemented |
| FR-WC-004-003 | Push item to WooCommerce on `item_updated` event | Must | Implemented |
| FR-WC-004-004 | Skip push when no WooCommerce credentials configured | Must | Implemented |
| FR-WC-004-005 | Skip push when item is inactive or not found in Woo | Must | Implemented |
| FR-WC-004-006 | Log sync attempt in `0_woo_sync_log` | Should | Implemented |

**Code references:** `src/Ksfraser/FrontAccounting/Woocommerce/Sync/ItemEventListener.php`

---

### FR-WC-005: Category Mapping

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-005-001 | Store FA category ↔ WooCommerce category mapping in `0_woo_category_map` | Must | Implemented |
| FR-WC-005-002 | Create WooCommerce categories during product export if no mapping exists | Should | Implemented |
| FR-WC-005-003 | Support parent category hierarchy | Could | Implemented |
| FR-WC-005-004 | Export categories via REST API v3 `POST/PUT /products/categories` | Should | Implemented |

**Code references:** `src/Ksfraser/FrontAccounting/Woocommerce/Export/CategoryExporter.php`

---

### FR-WC-006: Cron/CLI Sync

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-006-001 | Provide CLI entry point for scheduled sync | Should | Implemented |
| FR-WC-006-002 | Support `export_products` action | Must | Implemented |
| FR-WC-006-003 | Support `import_orders` action | Must | Implemented |
| FR-WC-006-004 | Support `import_customers` action | Must | Implemented |
| FR-WC-006-005 | Support `sync_all` action (all of above) | Should | Implemented |
| FR-WC-006-006 | Idempotent re-runs do not create duplicates | Must | Implemented |

**Code references:** `cron_sync.php`

---

### FR-WC-007: Configuration & Credentials

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-007-001 | Store WooCommerce URL, consumer key, consumer secret | Must | Implemented |
| FR-WC-007-002 | Support environment switching (devel/accept/prod) | Should | Implemented (legacy) |
| FR-WC-007-003 | Provide admin UI for configuration | Should | Partial (legacy) |
| FR-WC-007-004 | Sync log viewable in admin | Could | Implemented |

**Code references:** `src/Config/`, legacy `class.EXPORT_WOO.php`

---

### FR-WC-008: Logging & Audit Trail

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-008-001 | Log all sync attempts in `0_woo_sync_log` | Must | Implemented |
| FR-WC-008-002 | Track sync_type (product/category/order/customer) | Must | Implemented |
| FR-WC-008-003 | Track action (export/import) | Must | Implemented |
| FR-WC-008-004 | Record success/failure with error message | Must | Implemented |
| FR-WC-008-005 | Provide file-based logging via `FileLogger` | Should | Implemented |

---

### FR-WC-009: WooCommerce Import Configuration

**Description**: Store WooCommerce-specific GL accounts, bank accounts,
and defaults used when processing staged transactions into FA.

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-009-001 | Store GL account for card/online transactions (`woo_gl`) | Must | Planned |
| FR-WC-009-002 | Store GL account for cash transactions (`woo_cash_gl`) | Should | Planned |
| FR-WC-009-003 | Store bank account for deposits (`woo_bank`) | Must | Planned |
| FR-WC-009-004 | Store transfer destination bank (`woo_xfer_to_bank`) | Should | Planned |
| FR-WC-009-005 | Store default debtor for unmatched customers (`woo_default_customer`) | Must | Planned |
| FR-WC-009-006 | Store default FA location code (`woo_default_location`) | Should | Planned |
| FR-WC-009-007 | Store default dimensions (`woo_default_dimension1`, `woo_default_dimension2`) | Should | Planned |
| FR-WC-009-008 | Config UI for all WooCommerce import settings | Must | Planned |
| FR-WC-009-009 | Pass source config to ISU processing methods via `$sourceConfig` array | Must | Planned |

---

### FR-WC-010: Staging-First Architecture

**Description**: All WooCommerce orders SHALL be staged through ISU
before processing into FA. Direct import to FA tables is removed.

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| FR-WC-010-001 | OrderExporter stages via ISU hook, not direct FA writes | Must | Planned |
| FR-WC-010-002 | Remove `woo_order_staging` local table and `ensureStagingTable()` | Should | Planned |
| FR-WC-010-003 | ISU Process Queue UI handles WooCommerce transaction processing | Must | Planned |
| FR-WC-010-004 | Consistent with Square staging pattern (same ISU tables, same process flow) | Must | Planned |
| FR-WC-010-005 | Cron sync (`CronSyncService`) stages via ISU instead of direct import | Must | Planned |

---

## 3. Non-Functional Requirements

| ID | Requirement | Details |
|----|-------------|---------|
| NFR-WC-001 | PHP Version | PHP 7.3+ compatible (no PHP 8-only features) |
| NFR-WC-002 | FA Version | FrontAccounting 2.4.19 |
| NFR-WC-003 | WooCommerce API | REST API v3 via `Automattic\WooCommerce` ^3.1 |
| NFR-WC-004 | Idempotency | All sync operations are idempotent |
| NFR-WC-005 | Database | FA `0_` table prefix convention |
| NFR-WC-006 | Security | API credentials not exposed in logs or UI |
| NFR-WC-007 | Error Handling | API errors caught, logged, and displayed |
| NFR-WC-008 | Performance | Batch processing for large catalogs |
| NFR-WC-009 | Architecture | PSR-4 autoloading, SOLID/DRY/DI |

---

## 4. Data Model

### 4.1 Module Tables

```sql
0_woo_customer_map    -- FA debtor_no ↔ WooCommerce customer ID
0_woo_product_map     -- FA stock_id ↔ WooCommerce product ID + sync status
0_woo_category_map    -- FA category_id ↔ WooCommerce category ID
0_woo_sync_log        -- Sync audit trail (type, action, reference, success, error)
```

### 4.2 Shared Staging Tables (via ISU)

All WooCommerce orders stage through ISU's source-agnostic tables.
Source-specific fields (GL accounts, bank accounts) are provided
by WooCommerce config at processing time, NOT stored in staging tables.

```sql
0_ksf_import_square_transactions  -- Shared staging (source='woocommerce' distinguishes origin)
0_ksf_import_square_items         -- Shared line items staging
0_ksf_import_external_customers   -- Customer mapping (type='WOOCOMMERCE')
```

---

## 5. Inter-Module Communication

### hook_invoke Usage

```php
// Delegate order staging to ISU
$data = ['source' => 'woocommerce', ...];
hook_invoke('ksf_FA_ImportStagingProcessing_UI', 'respondToCapabilityRequest', $data,
    ['request' => 'staging:stageOrder']);

// Check if ISU is available
$hasStaging = hook_invoke('ksf_FA_ImportStagingProcessing_UI', 'hasCapability', $data,
    ['capability' => 'staging']);
```

### Event Subscription

```php
// In hooks.php or service provider
$listener = new ItemEventListener($productExporter);
$eventPublisher->subscribe($listener);
```

---

## 6. Glossary

| Term | Definition |
|------|-----------|
| WooCommerce | WordPress e-commerce plugin providing REST API for products/orders/customers |
| Staging | Inserting imported data into intermediate tables before processing into FA ledger |
| Fuzzy Matching | Customer matching algorithm using weighted scoring across multiple fields |
| QOH | Quantity on Hand — FA stock quantity |

---

## Version History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-08-20 | KSFraser | Initial requirements |
