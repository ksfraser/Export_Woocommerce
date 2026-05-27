# Export WooCommerce - Functional Requirements

## Project Overview
FrontAccounting module to export product and order data to WooCommerce stores via REST API.

## Functional Requirements

### FR-1: Product Export
**Priority: High**
- Export simple products from FrontAccounting to WooCommerce
- Export variable products (products with variants) to WooCommerce
- Map FA stock_master fields to WooCommerce product attributes
- Support product updates (price, inventory, descriptions)

### FR-2: Category Management
**Priority: High**
- Export product categories to WooCommerce
- Maintain category mapping between FA and WooCommerce (woo_categories_xref table)
- Update category assignments on products

### FR-3: Inventory Synchronization
**Priority: High**
- Export Quantity On Hand (QOH) from FA to WooCommerce
- Support ksf_qoh module or included QOH class
- Update stock levels in WooCommerce in real-time or batch

### FR-4: Order Import/Export
**Priority: Medium**
- Import orders from WooCommerce to FrontAccounting
- Export order status updates from FA to WooCommerce
- Map WooCommerce order fields to FA sales orders/invoices
- Handle billing and shipping addresses
- Process order line items, coupons, taxes, shipping

### FR-5: Customer Synchronization
**Priority: Medium**
- Export customer data from FA to WooCommerce
- Import customer data from WooCommerce to FA
- Map billing addresses, shipping addresses

### FR-6: Price Management
**Priority: High**
- Export regular prices from FA prices table
- Support sale prices via FA specials table
- Handle date ranges for sale prices (date_on_sale_from, date_on_sale_to)

### FR-7: Tax Configuration
**Priority: Medium**
- Export tax status and tax class to WooCommerce
- Map FA tax configurations to WooCommerce tax classes

### FR-8: Shipping Dimensions
**Priority: Low**
- Export product dimensions (length, width, height, weight)
- Map shipping classes from FA to WooCommerce

### FR-9: Coupon Management
**Priority: Low**
- Export coupons from FA to WooCommerce
- Import coupon usage data from WooCommerce

### FR-10: REST API Integration
**Priority: High**
- Use WooCommerce REST API (WC REST v1/v2/v3)
- Support OAuth 1.0a authentication (consumer key/secret)
- Handle API rate limiting and errors gracefully
- Support GET, POST, PUT, DELETE operations

### FR-11: FrontAccounting Integration
**Priority: High**
- Install as FA extension via Extensions menu
- Add menu item under Banking and General Ledger
- Respect FA role-based access control (SA_EXPORTWOO security area)
- Hook into FA transactions for real-time updates (db_postwrite hooks)

### FR-12: Data Transformation
**Priority: Medium**
- Transform FA data formats to WooCommerce JSON format
- Handle data type conversions (dates, numbers, booleans)
- Support custom attributes and meta data

## Non-Functional Requirements

### NFR-1: Performance
- Target: ~13 products per minute (current baseline)
- Batch processing for large catalogs
- Asynchronous processing for large operations

### NFR-2: Reliability
- Graceful error handling for API failures
- Logging of all export/import operations
- Retry mechanism for failed API calls

### NFR-3: Security
- Secure storage of API credentials (consumer key/secret)
- Respect FA user permissions
- Validate all data before sending to WooCommerce

### NFR-4: Maintainability
- Follow MVC pattern (Model-View-Controller)
- Separate data access (table_interface) from business logic
- Clear separation of REST client code from business logic
