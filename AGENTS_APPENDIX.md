# AGENTS_APPENDIX.md — ksf_FA_Woocommerce

## Module Overview

FrontAccounting module for bidirectional sync with WooCommerce stores via REST API v3.

## Namespace

```
ksfraser\FrontAccounting\Woocommerce\
```

## Architecture

### New Codebase (`src/`)
PSR-4 autoloading with layered architecture:
- **Contracts/** — Interfaces (`WooRestClientInterface`, `DatabaseInterface`, `LoggerInterface`)
- **DTOs/** — Immutable data containers (`ProductDTO`, `OrderDTO`, `CustomerDTO`)
- **Services/** — Business logic (`ProductExportService`, `OrderExporter`, `CustomerExporter`, `CategoryExporter`)
- **Staging/** — Two-stage import flow (`CustomerStaging`, `OrderStaging`)
- **Dao/** — Data access (`SyncDao`, `StockItemDao`)
- **Workflow/** — State machine (`WooSyncStateMachine`)
- **Push/** — Outbound sync (`CatalogExporter`, `ItemEventSyncService`, `TerminalPayment`)
- **Pull/** — Inbound sync (`OrderImporter`)
- **UI/** — Action dispatcher (`ImportExportDispatcher`)

### Legacy Code (root)
40+ `class.woo_*.php` files — deprecated, do not modify. New development goes in `src/`.

### Key Patterns
- **Staging-first import**: Orders and customers go through staging tables before FA processing
- **Event-driven export**: `ItemEventListener` hooks into FA's `item_created`/`item_updated` events
- **State machine**: `WooSyncStateMachine` manages sync lifecycle states

## Database Tables

| Table | Purpose |
|-------|---------|
| `0_woo_customer_map` | WC ↔ FA customer ID mapping |
| `0_woo_product_map` | WC ↔ FA product ID mapping |
| `0_woo_category_map` | WC ↔ FA category ID mapping |
| `0_woo_sync_log` | Sync audit trail |

## Security Constants

```php
SS_WOOCOMMERCE_SYNC = 116 << 8
SA_WOOCOMMERCE_SYNC = SS_WOOCOMMERCE_SYNC | 1
SA_WOOCOMMERCE_IMPORT = SS_WOOCOMMERCE_SYNC | 2
SA_WOOCOMMERCE_EXPORT = SS_WOOCOMMERCE_SYNC | 4
SA_WOOCOMMERCE_STAGING = SS_WOOCOMMERCE_SYNC | 8
```

## Known Issues

- `ProductDTO` does not emit `sale_price` or `tax_status` in `toArray()` (dead code)
- `VariableProductService` exists but is not wired into the live export path
- DTOs have 0% test coverage
- `ProductDataBuilder::build()` is dead code (not called by live path)
- Legacy `class.woo_*.php` files (~17.5k lines) still present
