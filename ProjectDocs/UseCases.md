# Use Cases — ksf_FA_Woocommerce

> **Module Code**: WC
> **Version**: 2.4.3-0

---

## UC-WC-001: Export FA Products to WooCommerce

| Element | Value |
|---------|-------|
| **ID** | UC-WC-001 |
| **Name** | Export FA Stock Items to WooCommerce |
| **Trigger** | Admin clicks "Export Products" or cron runs `export_products` |
| **Primary Actor** | FA Administrator / Cron |
| **Preconditions** | WooCommerce API credentials configured; FA stock items exist |
| **Postconditions** | Products created/updated in WooCommerce; mapping stored |

### Basic Flow
1. Admin navigates to WooCommerce sync page or cron triggers export
2. System reads FA stock items (filtered by category, pattern, active status)
3. For each item, System calls `ProductExporter::export()`
4. System maps FA fields to WooCommerce product fields (SKU, name, price, description, images, weight)
5. System checks `0_woo_product_map` for existing mapping
6. **If mapped:** `PUT /products/{id}` (update)
7. **If not mapped:** `POST /products` (create)
8. System stores mapping in `0_woo_product_map`
9. System pushes QOH via stock quantity update
10. System logs result in `0_woo_sync_log`

### Alternative Flows
- **7a. SKU duplicate error:** System logs warning, skips item
- **7b. API rate limit:** System retries with backoff
- **6a. Product deleted in WooCommerce:** System re-creates, updates mapping

---

## UC-WC-002: Import WooCommerce Orders via Staging

| Element | Value |
|---------|-------|
| **ID** | UC-WC-002 |
| **Name** | Pull WooCommerce Orders into FA via Staging |
| **Trigger** | Admin clicks "Import Orders" or cron runs `import_orders` |
| **Primary Actor** | FA Administrator / Cron |
| **Preconditions** | WooCommerce API credentials configured; orders exist in WooCommerce |
| **Postconditions** | Orders staged in ISU tables; available for review and processing |

### Basic Flow
1. Admin initiates order import
2. System calls `GET /orders` with date range filter
3. System paginates through results (100 per page)
4. For each order, System calls `OrderExporter::stageOrder()`
5. System delegates to ISU staging via `hook_invoke('ksf_FA_ImportStagingProcessing_UI', 'respondToCapabilityRequest', $data, ['request' => 'staging:stageOrder'])`
6. ISU checks for duplicate (WooCommerce order ID)
7. ISU inserts into staging table
8. System logs import summary
9. Admin reviews staged orders in ISU Process Queue
10. Admin processes selected orders into FA sales invoices

### Alternative Flows
- **2a. API error:** System logs error, notifies admin
- **5a. ISU unavailable:** System falls back to local staging tables
- **9a. Customer not matched:** Admin selects/creates customer in review UI

---

## UC-WC-003: Synchronize Customers Bidirectionally

| Element | Value |
|---------|-------|
| **ID** | UC-WC-003 |
| **Name** | Sync Customers Between FA and WooCommerce |
| **Trigger** | Admin clicks "Import Customers" or cron runs `import_customers` |
| **Primary Actor** | FA Administrator / Cron |
| **Preconditions** | WooCommerce API credentials; customers exist in one or both systems |
| **Postconditions** | Customers matched/created; mapping stored in `0_woo_customer_map` |

### Basic Flow
1. System pulls WooCommerce customers via `GET /customers`
2. For each WooCommerce customer, System calls `CustomerMatcher::match()`
3. Matcher checks `0_woo_customer_map` for existing link
4. **If mapped:** Skip (already synced)
5. **If not mapped:** Score against FA debtors:
   a. Email match → 30 points
   b. Phone match → 25 points
   c. Name match → 20 points
   d. Address match → 15 points
6. **Score ≥ 50:** Auto-match to best candidate
7. **Score < 50:** Stage for manual review
8. **No candidates:** Create new FA debtor from WooCommerce data
9. System stores mapping in `0_woo_customer_map`

### Alternative Flows
- **8a. Manual review:** Admin picks match or creates new customer in review UI
- **5a. Email exact match:** Highest priority, usually sufficient alone

---

## UC-WC-004: Event-Driven Product Update

| Element | Value |
|---------|-------|
| **ID** | UC-WC-004 |
| **Name** | Push Item Changes to WooCommerce in Real-Time |
| **Trigger** | Admin creates or updates an FA stock item |
| **Primary Actor** | FA system (automatic) |
| **Preconditions** | WooCommerce API credentials; item exists in WooCommerce |
| **Postconditions** | WooCommerce product updated immediately |

### Basic Flow
1. Admin saves FA stock item (create or update)
2. FA fires item event via `ItemEventPublisher`
3. `ItemEventListener::handle()` receives event
4. Listener checks WooCommerce credentials and item sync status
5. Listener calls `ProductExporter::export()` for the item
6. WooCommerce product is updated via API
7. Sync status updated in `0_woo_product_map`
8. Event logged in `0_woo_sync_log`

### Alternative Flows
- **4a. No credentials:** Skip silently (log debug)
- **4b. Item inactive:** Skip (don't export inactive items)
- **4c. Item not in WooCommerce:** Create new product
- **5a. API error:** Log error, do not block FA save

---

## UC-WC-005: Configure Category Mapping

| Element | Value |
|---------|-------|
| **ID** | UC-WC-005 |
| **Name** | Map FA Categories to WooCommerce Categories |
| **Trigger** | Admin configures category mapping or auto-created during export |
| **Primary Actor** | FA Administrator / System |
| **Preconditions** | Categories exist in both FA and WooCommerce |
| **Postconditions** | Category mapping stored in `0_woo_category_map` |

### Basic Flow
1. Admin navigates to category mapping section (or export auto-creates)
2. System displays FA categories and available WooCommerce categories
3. Admin selects mapping (FA category → WooCommerce category)
4. System stores in `0_woo_category_map`
5. Subsequent product exports use mapped category

### Alternative Flows
- **2a. No WooCommerce category exists:** System creates one via `POST /products/categories`
- **5a. Parent categories:** Hierarchy preserved

---

## UC-WC-006: Scheduled Sync via Cron

| Element | Value |
|---------|-------|
| **ID** | UC-WC-006 |
| **Name** | Run Scheduled Synchronization |
| **Trigger** | Cron job or manual CLI execution |
| **Primary Actor** | System / FA Administrator |
| **Preconditions** | API credentials configured; cron set up |
| **Postconditions** | Products exported, orders imported, customers synced |

### Basic Flow
1. Cron triggers `php cron_sync.php sync_all`
2. System runs `export_products` action
3. System runs `import_orders` action
4. System runs `import_customers` action
5. System logs summary of all actions

### Alternative Flows
- **2a. Single action:** `php cron_sync.php export_products` runs only that action
- **3a. API error on one action:** Other actions continue; errors logged

---

## Version History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-08-20 | KSFraser | Initial use cases |
