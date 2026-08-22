# User Acceptance Test Cases — ksf_FA_Woocommerce

> **Module Code**: WC
> **Version**: 2.4.3-0
> **Platform**: PHP 7.3 / FrontAccounting 2.4.19

---

## Test Environment Requirements

- FA 2.4.19 installed with sample data
- ksf_FA_Woocommerce module activated with Composer dependencies
- WooCommerce store accessible with REST API v3 enabled
- API credentials (consumer key/secret) configured
- ksf_FA_ImportStagingProcessing module installed (for staging)
- Test products in FA and WooCommerce
- Test customers in WooCommerce

---

## UAT-WC-001: Export FA Product to WooCommerce

**@BABOK Related: FR-WC-001-001**

| Field | Value |
|-------|-------|
| **Actor** | FA Administrator |
| **Preconditions** | WooCommerce API configured; at least one active FA stock item with price; category mapped |
| **Priority** | High |

### Steps

| Step | Action | Expected Result |
|------|--------|----------------|
| 1 | Navigate to WooCommerce sync page | Page loads with export options |
| 2 | Select a category filter (or all) | Filter is applied |
| 3 | Click "Export Products" | System begins export with progress notifications |
| 4 | Verify notification shows product exported | SKU, name, price logged |
| 5 | Open WooCommerce admin → Products | Product appears with correct SKU, name, price |
| 6 | Verify `0_woo_product_map` has entry | stock_id ↔ woo_product_id mapping exists |
| 7 | Verify `0_woo_sync_log` has entry | sync_type=product, action=export, success=1 |

### Pass Criteria
- Product exists in WooCommerce with correct fields
- Mapping table has entry
- Sync log records success

---

## UAT-WC-002: Update Existing Product in WooCommerce

**@BABOK Related: FR-WC-001-001**

| Field | Value |
|-------|-------|
| **Actor** | FA Administrator |
| **Preconditions** | Product already exported (exists in `0_woo_product_map`) |
| **Priority** | High |

### Steps

| Step | Action | Expected Result |
|------|--------|----------------|
| 1 | Change FA stock item price | Price updated in FA |
| 2 | Trigger product export | System detects existing mapping |
| 3 | Verify WooCommerce product updated | WooCommerce shows new price |
| 4 | Verify no duplicate created | Only one product in WooCommerce for this SKU |

### Pass Criteria
- WooCommerce product is updated (not duplicated)
- Mapping unchanged
- Price matches FA

---

## UAT-WC-003: Import WooCommerce Order via Staging

**@BABOK Related: FR-WC-002-001**

| Field | Value |
|-------|-------|
| **Actor** | FA Administrator |
| **Preconditions** | WooCommerce order exists; ISU staging module active |
| **Priority** | High |

### Steps

| Step | Action | Expected Result |
|------|--------|----------------|
| 1 | Navigate to WooCommerce import page | Import options displayed |
| 2 | Set date range to include test order | Date range set |
| 3 | Click "Import Orders" | System pulls orders from WooCommerce API |
| 4 | Verify notification shows orders staged | Count of staged orders displayed |
| 5 | Navigate to ISU Process Queue | Staged WooCommerce orders visible |
| 6 | Click View on staged order | Order details, line items, customer info shown |
| 7 | Select customer match | Customer dropdown populated |
| 8 | Click Process | FA sales invoice created |
| 9 | Verify FA invoice exists | Invoice with correct line items and amounts |
| 10 | Verify `0_woo_sync_log` entry | sync_type=order, action=import, success=1 |

### Pass Criteria
- Order staged via ISU
- FA invoice created with correct items and amounts
- Sync log records success

---

## UAT-WC-004: Import WooCommerce Order Dedup

**@BABOK Related: FR-WC-002-009**

| Field | Value |
|-------|-------|
| **Actor** | FA Administrator |
| **Preconditions** | Same WooCommerce order imported once already |
| **Priority** | High |

### Steps

| Step | Action | Expected Result |
|------|--------|----------------|
| 1 | Re-run import for same date range | System processes orders |
| 2 | Verify notification shows duplicates skipped | Skip message for already-staged order |
| 3 | Query staging table | No duplicate entry for same WooCommerce order ID |

### Pass Criteria
- Duplicate detected and skipped
- No duplicate staging record
- User notified

---

## UAT-WC-005: Import WooCommerce Customers with Fuzzy Match

**@BABOK Related: FR-WC-003-003**

| Field | Value |
|-------|-------|
| **Actor** | FA Administrator |
| **Preconditions** | WooCommerce customers exist; matching FA debtors exist |
| **Priority** | High |

### Steps

| Step | Action | Expected Result |
|------|--------|----------------|
| 1 | Run customer import | System pulls WooCommerce customers |
| 2 | Verify matching customer linked | `0_woo_customer_map` has entry for matched customer |
| 3 | Verify no duplicate FA debtor created | Existing debtor reused |
| 4 | Import customer with no FA match | Customer staged for manual review |
| 5 | Open customer review UI | Unmatched customer displayed with scoring details |
| 6 | Select "Create New Customer" | New FA debtor created from WooCommerce data |
| 7 | Verify `0_woo_customer_map` updated | Mapping exists for new debtor |

### Pass Criteria
- Matched customers linked without duplication
- Unmatched customers available for review
- New customers created on demand

---

## UAT-WC-006: Event-Driven Product Push on FA Save

**@BABOK Related: FR-WC-004-001**

| Field | Value |
|-------|-------|
| **Actor** | FA Administrator |
| **Preconditions** | WooCommerce API configured; ItemEventListener registered |
| **Priority** | Medium |

### Steps

| Step | Action | Expected Result |
|------|--------|----------------|
| 1 | Create a new FA stock item | Item saved successfully |
| 2 | Verify WooCommerce product created | New product in WooCommerce with correct fields |
| 3 | Update the FA item price | Price changed |
| 4 | Verify WooCommerce product updated | Price matches new FA price |
| 5 | Verify `0_woo_sync_log` entries | Two entries: one create, one update |

### Pass Criteria
- Real-time push works on create and update
- No manual export trigger needed
- Sync log tracks both events

---

## UAT-WC-007: Category Mapping Applied During Export

**@BABOK Related: FR-WC-005-001**

| Field | Value |
|-------|-------|
| **Actor** | FA Administrator |
| **Preconditions** | FA category and WooCommerce category exist; mapping configured |
| **Priority** | Medium |

### Steps

| Step | Action | Expected Result |
|------|--------|----------------|
| 1 | Export product in mapped category | Product exported |
| 2 | Verify WooCommerce product has correct category | Category matches mapped WooCommerce category |
| 3 | Export product in unmapped category | System creates WooCommerce category automatically |
| 4 | Verify `0_woo_category_map` updated | New mapping stored |

### Pass Criteria
- Mapped categories applied correctly
- Unmapped categories auto-created
- Mapping table maintained

---

## UAT-WC-008: Cron Sync All Actions

**@BABOK Related: FR-WC-006-001**

| Field | Value |
|-------|-------|
| **Actor** | System (CLI) |
| **Preconditions** | API credentials configured; cron accessible |
| **Priority** | Medium |

### Steps

| Step | Action | Expected Result |
|------|--------|----------------|
| 1 | Run `php cron_sync.php sync_all` | Command executes |
| 2 | Verify products exported | Products in WooCommerce match FA |
| 3 | Verify orders imported | New orders staged in ISU |
| 4 | Verify customers synced | `0_woo_customer_map` updated |
| 5 | Verify `0_woo_sync_log` entries | Entries for all three actions |
| 6 | Re-run same command | No duplicate products/orders/customers |

### Pass Criteria
- All three sync actions complete
- Idempotent re-runs
- Sync log records all actions

---

## UAT-WC-009: API Error Handling

**@BABOK Related: FR-WC-001-012**

| Field | Value |
|-------|-------|
| **Actor** | FA Administrator |
| **Preconditions** | Invalid or expired API credentials |
| **Priority** | High |

### Steps

| Step | Action | Expected Result |
|------|--------|----------------|
| 1 | Set invalid API credentials | Credentials saved |
| 2 | Attempt product export | System attempts API call |
| 3 | Verify error message displayed | Meaningful error (auth failure, not generic) |
| 4 | Verify no partial products created | WooCommerce unchanged |
| 5 | Verify `0_woo_sync_log` records failure | success=0, error_message populated |
| 6 | Fix credentials, re-export | Export succeeds |

### Pass Criteria
- Errors caught and displayed clearly
- No partial/corrupt state
- Log records failure details
- Recovery works after fix

---

## Summary

| UAT ID | Description | FR | Priority |
|--------|-------------|----|----------|
| UAT-WC-001 | Export FA product to WooCommerce | FR-WC-001 | High |
| UAT-WC-002 | Update existing product | FR-WC-001 | High |
| UAT-WC-003 | Import order via staging | FR-WC-002 | High |
| UAT-WC-004 | Order import dedup | FR-WC-002 | High |
| UAT-WC-005 | Customer fuzzy matching | FR-WC-003 | High |
| UAT-WC-006 | Event-driven product push | FR-WC-004 | Medium |
| UAT-WC-007 | Category mapping | FR-WC-005 | Medium |
| UAT-WC-008 | Cron sync all | FR-WC-006 | Medium |
| UAT-WC-009 | API error handling | FR-WC-001 | High |

---

## Version History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-08-20 | KSFraser | Initial UAT plan |
