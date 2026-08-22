# BABOK Business Analysis — ksf_FA_Woocommerce

> **Module Code**: WC
> **Version**: 2.4.3-0
> **Aligned to BABOK v3 knowledge areas**

---

## 1. Stakeholders

| Stakeholder | Role | Interest |
|-------------|------|----------|
| FA Administrator | Configures WooCommerce sync | API credentials, sync settings, cron schedules |
| Sales / E-Commerce Operator | Manages online store products | Product sync, order processing |
| Accountant / Bookkeeper | Posts imported orders to ledger | Accurate invoice/payment recording |
| Business Owner | Reviews reports | Inventory accuracy, order revenue |
| IT / Developer | Maintains module | Code quality, API compatibility |
| Square Integration (ksf_FA_Square) | Shares staging infrastructure | Avoids import conflicts |

---

## 2. Business Needs

### 2.1 Current State (As-Is)

- WooCommerce products maintained separately from FA stock items
- Orders placed online require manual data entry into FA
- Customer data duplicated across both systems
- No inventory sync — overselling risk
- Manual reconciliation of WooCommerce payments
- No unified staging across Square and WooCommerce imports

### 2.2 Desired State (To-Be)

- Bidirectional product sync (FA → WooCommerce)
- Automated order import with staging-first workflow
- Customer matching and creation across systems
- QOH pushed to WooCommerce for inventory accuracy
- Category mapping between FA and WooCommerce
- Event-driven real-time item updates (FA save → WooCommerce push)
- Shared staging infrastructure with Square imports via ksf_FA_ImportStagingProcessing

### 2.3 Gap Analysis

| Gap | Current | Target | Solution |
|-----|---------|--------|----------|
| Product Sync | Manual dual entry | Automated FA → Woo push | ProductExporter + CatalogExporter |
| Order Import | Manual data entry | Automated API pull + stage | OrderExporter → ISU staging |
| Customer Match | None | Bidirectional fuzzy matching | CustomerSyncService with scoring |
| Inventory | No sync | QOH pushed on change | ItemEventListener + stock push |
| Categories | Separate systems | Cross-reference mapping | CategoryExporter with xref table |
| Staging | Ad-hoc tables | Shared with Square | ISU staging tables |

---

## 3. Business Requirements

### BR-WC-001: Product Catalog Synchronization

**Statement:** The system shall synchronize FA stock items to WooCommerce as products, including descriptions, prices, categories, images, and inventory quantities.

**Rationale:** Dual product management creates discrepancies and wastes staff time. One-way sync (FA → Woo) ensures FA is the single source of truth for product data.

**Acceptance Criteria:**
- Simple and variable products are exported via WooCommerce REST API v3
- SKU, description, price, weight, dimensions, and images are mapped
- Categories are cross-referenced via `0_woo_category_map`
- QOH is pushed to WooCommerce for stock management
- Product mapping stored in `0_woo_product_map`

**Related FRs:** FR-WC-001

---

### BR-WC-002: Order Import with Staging

**Statement:** The system shall import WooCommerce orders into FrontAccounting via a staging-first workflow, allowing review before posting to the ledger.

**Rationale:** Direct posting risks incorrect customer/vendor assignment, unreviewed amounts, and missed items. Staging provides a safety net.

**Acceptance Criteria:**
- Orders pulled via WooCommerce REST API
- Staged via `ksf_FA_ImportStagingProcessing` hook interface
- Customer matching with fuzzy scoring algorithm
- Review UI for unmatched customers/orders
- Process into FA sales invoices, payments

**Related FRs:** FR-WC-002

---

### BR-WC-003: Customer Synchronization

**Statement:** The system shall synchronize customer data bidirectionally between FA and WooCommerce, matching by email, phone, name, or reference ID.

**Rationale:** Customers place orders in WooCommerce; their details must exist in FA for invoicing and reporting.

**Acceptance Criteria:**
- FA debtors pushed to WooCommerce as customers
- WooCommerce customers matched to FA debtors
- Fuzzy matching with configurable scoring (EMAIL=30, PHONE=25, NAME=20, ADDRESS=15)
- New FA debtors created from unmatched WooCommerce customers
- Mapping stored in `0_woo_customer_map`

**Related FRs:** FR-WC-003

---

### BR-WC-004: Event-Driven Item Updates

**Statement:** The system shall listen to FA item create/update events and immediately push changes to WooCommerce.

**Rationale:** Batch-only sync causes stale data. Event-driven updates keep WooCommerce current.

**Acceptance Criteria:**
- `ItemEventListener` subscribes to `ItemEventPublisher` from ksf_FA_Common
- Item changes trigger immediate WooCommerce product update
- Respects sync status (skips inactive/error items)
- Graceful failure handling (log, don't block FA save)

**Related FRs:** FR-WC-004

---

### BR-WC-005: Category Cross-Reference Mapping

**Statement:** The system shall maintain a cross-reference table mapping FA stock categories to WooCommerce product categories.

**Rationale:** FA and WooCommerce use different category IDs and structures. A mapping table enables consistent categorization.

**Acceptance Criteria:**
- `0_woo_category_map` stores FA category → WooCommerce category mapping
- Categories created in WooCommerce during product export if no mapping exists
- Parent category hierarchy supported

**Related FRs:** FR-WC-005

---

### BR-WC-006: Staging-First Import Architecture

**Statement:** The system SHALL stage all WooCommerce orders through
ISU's staging infrastructure before processing into FA, removing the
direct-import bypass and dormant local staging tables.

**Rationale:** Direct posting bypasses review, mismatch detection, and
GL comparison. Staging-first ensures all imports follow the same path,
regardless of source. Consistency with Square import flow.

**Acceptance Criteria:**
- All orders stage through ISU hook interface
- Direct FA table writes removed from OrderExporter
- Local `woo_order_staging` table removed
- Processing happens through ISU Process Queue UI
- Cron sync stages via ISU instead of direct import

**Related FRs:** FR-WC-002, FR-WC-010

---

### BR-WC-007: WooCommerce Import Configuration

**Statement:** The system SHALL store WooCommerce-specific GL accounts,
bank accounts, and processing defaults in its own config, and pass them
to ISU at processing time.

**Rationale:** Like Square, WooCommerce has its own GL routing and bank
accounts. ISU is generic and does not own source-specific config. Each
importing module owns its own config and provides it via the delegation
pattern.

**Acceptance Criteria:**
- GL account for online/card transactions configurable
- Bank account for deposits configurable
- Default debtor for unmatched customers configurable
- Default location and dimensions configurable
- Config UI accessible from WooCommerce module settings
- Config passed to ISU via `$sourceConfig` array at processing time

**Related FRs:** FR-WC-009

---

## 4. Business Rules

| Rule ID | Description |
|---------|------------|
| BR-WC-R001 | FA is the single source of truth for product data (one-way sync FA → Woo) |
| BR-WC-R002 | Orders are always staged before posting to FA (no direct import) |
| BR-WC-R003 | Customer matching uses the highest-score match above HIGH_MATCH threshold (50) |
| BR-WC-R004 | Product sync status is tracked per item (`pending`, `synced`, `error`) |
| BR-WC-R005 | All WooCommerce API calls use REST API v3 via `Automattic\WooCommerce\Client` |
| BR-WC-R006 | Staging delegates to `ksf_FA_ImportStagingProcessing` when available; falls back to local tables |
| BR-WC-R007 | Cron sync operations are idempotent (re-running does not create duplicates) |
| BR-WC-R008 | All orders stage through ISU — no direct FA table writes |
| BR-WC-R009 | WooCommerce owns its GL/bank config; ISU does not store source-specific fields |
| BR-WC-R010 | Direct import bypass is removed; all paths go through ISU staging |

---

## 5. Solution Approach

### 5.1 Architecture

```
src/Ksfraser/FrontAccounting/Woocommerce/
├── Export/
│   ├── ProductExporter.php         — FA stock → WooCommerce product
│   ├── CategoryExporter.php        — FA categories → WooCommerce categories
│   └── InventoryExporter.php       — FA QOH → WooCommerce stock
├── Import/
│   ├── OrderExporter.php           — WooCommerce orders → staging
│   └── CustomerExporter.php        — WooCommerce customers → FA debtors
├── Sync/
│   ├── ItemEventListener.php       — FA event → immediate Woo push
│   └── CronSyncService.php         — Scheduled batch sync
├── Staging/
│   ├── OrderStaging.php            — Delegates to ISU staging
│   └── CustomerStaging.php         — Delegates to ISU staging
├── Matching/
│   └── CustomerMatcher.php         — Fuzzy customer matching
├── DTO/                            — Data Transfer Objects
├── DAO/                            — Data Access Objects
└── WooRestClient.php               — REST API wrapper
```

### 5.2 Data Flow

```
FA Stock Item
  ↓ (ItemEventListener or manual export)
ProductExporter → WooCommerce REST API → WooCommerce Product
  ↓
0_woo_product_map (stock_id ↔ woo_product_id)

WooCommerce Order
  ↓ (API pull or cron)
OrderExporter → ISU Staging Tables → Process UI → FA Sales Invoice
  ↓
0_woo_sync_log (audit trail)

WooCommerce Customer
  ↓ (API pull)
CustomerMatcher → Fuzzy Score → Match/Create FA Debtor
  ↓
0_woo_customer_map (debtor_no ↔ woo_customer_id)
```

### 5.3 External Dependencies

- **ksf_FA_ImportStagingProcessing** — Generic staging infrastructure (order/customer staging)
- **ksf_FA_Common** — `ItemEventPublisher` for event-driven sync
- **ksf_modules_common** — Shared FA interface classes
- **Automattic\WooCommerce** — PHP client for WooCommerce REST API
- **FA Core** — Sales, stock, customer modules

### 5.4 Customer Matching Algorithm

| Field | Score | Weight |
|-------|-------|--------|
| Email exact match | 30 | High |
| Phone match | 25 | High |
| Name + Company | 20 | Medium |
| Address match | 15 | Medium |
| HIGH_MATCH threshold | 50 | Must exceed for auto-match |
| Levenshtein distance | <20% of length | Fuzzy tolerance |

---

## 6. Risks

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| WooCommerce REST API changes | Export/import breaks | Medium | Pin WooCommerce PHP client version |
| Large product catalogs timeout | Incomplete sync | Medium | Batch processing, max_items limit |
| Customer fuzzy matching false positives | Wrong customer linked | Low | Threshold scoring, manual review UI |
| PHP 7.3 limits modern dependencies | Missing features | High | Target PHP 7.3-compatible packages |
| Staging module unavailable | Orders can't be processed | Low | Local fallback tables |
| Credential exposure in legacy code | Security breach | Medium | Remove hardcoded credentials, use env vars |

---

## 7. Traceability

| BR | FR | UC | UAT |
|----|----|----|-----|
| BR-WC-001 | FR-WC-001 | UC-WC-001 | UAT-WC-001 |
| BR-WC-002 | FR-WC-002 | UC-WC-002 | UAT-WC-002 |
| BR-WC-003 | FR-WC-003 | UC-WC-003 | UAT-WC-003 |
| BR-WC-004 | FR-WC-004 | UC-WC-004 | UAT-WC-004 |
| BR-WC-005 | FR-WC-005 | UC-WC-005 | UAT-WC-005 |

---

## Version History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-08-20 | KSFraser | Initial BABOK alignment |
| 1.1 | 2026-08-20 | KSFraser | Added FR-WC-009 (import config), FR-WC-010 (staging-first architecture), BR-WC-006/007 |
