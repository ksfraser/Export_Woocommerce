# Export WooCommerce - System Architecture

## Architecture Overview

```
+-------------------+       +-------------------+       +-------------------+
| FrontAccounting   |       | Export Woo Module |       | WooCommerce      |
| (FA Database)     | <---> | (This Module)    | <---> | (REST API)       |
+-------------------+       +-------------------+       +-------------------+
        |                           |                           |
        |                           |                           |
        v                           v                           v
+-------------------+       +-------------------+       +-------------------+
| stock_master      |       | woo table         |       | Products         |
| prices            | <---> | woo_categories    | <---> | Categories       |
| specials          |       | _xref             |       | Orders           |
| stock_category    |       | woo_orders        |       | Customers        |
+-------------------+       +-------------------+       +-------------------+
```

## Component Architecture

### 1. Presentation Layer (FA Integration)
**Files**: EXPORT_WOO.php, hooks.php, woo_form_handler.php

- Integrates with FrontAccounting menu system
- Handles HTTP requests and form submissions
- Uses FA UI components (display_notification, start_form, etc.)
- Implements FA hooks (install_options, install_access, db_postwrite)

### 2. Business Logic Layer (Models)
**Files**: class.woo.php, class.woo_product.php, class.woo_category.php, etc.

- **woo**: Main product model, database operations on woo table
- **woo_product**: Product export logic, data transformation
- **woo_category**: Category export logic
- **woo_orders**: Order import/export logic
- **woo_customer**: Customer synchronization
- **woo_interface**: Base class with common functionality

### 3. Data Access Layer
**Files**: class.woo_interface.php (extends table_interface)

- Database operations via FrontAccounting's db_query functions
- Table definition and schema management
- CRUD operations on module tables
- Depends on ksf_modules_common/table_interface.php

### 4. API Client Layer
**Files**: class.woo_rest.php (extends rest_client)

- REST API communication with WooCommerce
- HTTP methods: GET, POST, PUT, DELETE
- Authentication handling (OAuth 1.0a)
- JSON encoding/decoding
- Error handling and logging
- Depends on ksf_modules_common/rest_client.php

## Data Flow

### Product Export Flow
```
1. User triggers export in FA
   |
2. woo->insert_product() - Populate woo table from stock_master
   |
3. woo->update_product_details() - Update from stock_master
   |
4. woo->update_prices() - Update from prices table
   |
5. woo->update_qoh_count() - Update from QOH
   |
6. woo->update_specials() - Update from specials table
   |
7. woo_product->export() - Transform to WC format
   |
8. woo_rest->post() - Send to WooCommerce API
   |
9. Update woo_id and woo_last_update in woo table
```

### Order Import Flow
```
1. woo_rest->get('orders') - Fetch from WooCommerce
   |
2. woo_orders->parse() - Transform to FA format
   |
3. Create sales order/invoice in FA
   |
4. Map customer data to FA
   |
5. Store order mapping in woo_orders table
```

## Design Patterns

### 1. MVC (Model-View-Controller)
- **Model**: woo, woo_product, etc. (data and business logic)
- **View**: FA UI components, form handlers (needs separation)
- **Controller**: EXPORT_WOO.php, eventloop

### 2. Inheritance Hierarchy
```
table_interface
    |
    v
woo_interface
    |
    +---> woo (product model)
    |
    +---> woo_product (product export)
    |
    +---> woo_category
    |
    +---> woo_orders
    |
    +---> etc.
```

### 3. Event Loop Pattern
- class.eventloop.php handles module execution
- Listens for events (WOO_PRODUCT_INSERT, etc.)
- Triggers registered handlers

## Database Schema Relationships

```
stock_master (FA) ----< woo >---- WooCommerce Products
      |                     |
      v                     v
prices (FA)           woo_categories_xref
      |                     |
      v                     v
specials (FA)         stock_category (FA)
```

## Module Dependencies

### Internal Dependencies
- EXPORT_WOO_prefs: Configuration storage
- woo table: Main product mapping
- woo_categories_xref: Category mapping

### External Dependencies (ksf_modules_common)
- class.table_interface.php: Database operations
- class.rest_client.php: HTTP client
- class.eventloop.php: Event handling
- defines.inc.php: Constants

### Optional Dependencies
- ksf_qoh: Advanced QOH management
- ksf_generate_catalogue: Price book generation

## Security Architecture

### Authentication
- WooCommerce: OAuth 1.0a (consumer key + secret)
- FrontAccounting: Role-based (SA_EXPORTWOO)

### Data Validation
- Input validation in form handlers
- SQL injection prevention (parameterized queries via FA db_query)
- Output escaping for HTML

### Credential Storage
- Stored in EXPORT_WOO_prefs table
- Should be encrypted (currently plaintext - security enhancement needed)

## Scalability Considerations

### Current Limitations
- Synchronous API calls (blocking)
- No queue/batching mechanism
- ~13 products/minute performance
- MySQL direct queries (no ORM)

### Improvement Opportunities
- Implement message queue for large exports
- Add caching layer for API responses
- Batch API operations
- Async processing via cron/background jobs
