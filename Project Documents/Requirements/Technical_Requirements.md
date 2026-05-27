# Export WooCommerce - Technical Requirements

## System Architecture

### Technology Stack
- **Platform**: PHP 5.6+ (FrontAccounting compatibility)
- **Database**: MySQL/MariaDB (FrontAccounting database)
- **API**: WooCommerce REST API (v1, v2, or v3)
- **Framework**: FrontAccounting extension system
- **Dependencies**: 
  - ksf_modules_common (table_interface, rest_client, eventloop)
  - ksf_qoh (optional, for advanced QOH)
  - ksf_generate_catalogue (optional, for price books)

### Database Tables

#### woo (main product table)
| Field | Type | Description |
|-------|------|-------------|
| stock_id | varchar(STOCK_ID_LENGTH) | FA Stock ID (SKU) - Primary Key |
| updated_ts | timestamp | Last update in FA |
| woo_last_update | timestamp | Last update sent to WooCommerce |
| woo_id | varchar(32) | WooCommerce product ID |
| category_id | int(11) | FA category ID |
| category | varchar(64) | Category name |
| woo_category_id | int(11) | WooCommerce category ID |
| description | varchar(200) | Product name |
| long_description | varchar(500) | Product description |
| units | varchar(20) | Unit of measure |
| price | double | Regular price |
| instock | int(11) | Quantity on hand |
| saleprice | float | Sale price |
| date_on_sale_from | date | Sale start date |
| date_on_sale_to | date | Sale end date |
| external_url | varchar(128) | External product URL |
| tax_status | varchar(32) | Tax status (taxable, etc.) |
| tax_class | varchar(32) | Tax class |
| weight | float | Product weight |
| length | float | Product length |
| width | float | Product width |
| height | float | Product height |
| shipping_class | varchar(32) | Shipping class |
| upsell_ids | varchar(128) | Upsell product IDs |
| crosssell_ids | varchar(128) | Cross-sell product IDs |
| parent_id | varchar(32) | Parent product (for variations) |
| attributes | varchar(255) | Product attributes |
| default_attributes | varchar(255) | Default variation attributes |
| variations | varchar(255) | Product variations |

#### woo_categories_xref
Maps FA categories to WooCommerce categories.

#### woo_prod_variable_master
Defines variable products (products with variants).

#### woo_prod_variable_sku_*
Tables for managing product variations and SKU combinations.

## Class Structure

### Core Classes
1. **woo_interface** (class.woo_interface.php)
   - Base class for all WooCommerce objects
   - Extends table_interface for database operations
   - Handles REST client initialization
   - Manages write properties array for API calls

2. **woo_rest** (class.woo_rest.php)
   - Extends rest_client
   - Handles WooCommerce REST API communication
   - Methods: GET, POST, PUT, DELETE
   - Builds API URLs (serverURL + woo_rest_path + endpoint)

3. **woo** (class.woo.php)
   - Main model class for product data
   - Handles product table operations
   - Methods for inserting, updating products
   - QOH integration

4. **woo_product** (class.woo_product.php)
   - Handles product export to WooCommerce
   - Maps FA product data to WC product structure
   - Handles both simple and variable products
   - Builds JSON data for API calls

### Supporting Classes
- **woo_category**: Category export/management
- **woo_orders**: Order import/export
- **woo_customer**: Customer data synchronization
- **woo_coupons**: Coupon management
- **woo_line_items**, **woo_shipping_lines**, **woo_tax_lines**: Order components
- **woo_billing**, **woo_shipping_address**: Address handling
- **woo_images**: Product image handling
- **qoh**: Quantity on hand (if ksf_qoh not available)

## API Integration

### WooCommerce REST API
- **Base URL**: `{serverURL}/wp-json/wc/v1/`
- **Authentication**: OAuth 1.0a (consumer key + secret)
- **Endpoints Used**:
  - `/products` - Product operations
  - `/products/{id}` - Individual product
  - `/products/categories` - Category operations
  - `/orders` - Order operations
  - `/customers` - Customer operations
  - `/coupons` - Coupon operations

### Legacy WC API (Deprecated)
- Older implementation using wc-master/lib/woocommerce-api.php
- Being migrated to REST API

## Configuration

### Connection Settings (EXPORT_WOO_prefs table)
- serverURL: WooCommerce store URL
- consumer key: API consumer key
- consumer secret: API consumer secret
- woo_rest_path: API path (default: /wp-json/wc/v1/)
- environment: devel or PROD (affects SSL verification)

### FrontAccounting Settings
- Security Area: SA_EXPORTWOO
- Menu Location: Banking and General Ledger
- Hooks: db_postwrite for transaction integration

## Current Limitations
1. Rudimentary simple product export only (variable products incomplete)
2. Mixed MVC implementation (some GUI code in model classes)
3. Duplicate files (class.EXPORT_WOO.php vs class.EXPORT_WOO - Copy.php)
4. Hardcoded debug limits (LIMIT 10 in product queries)
5. Legacy WC API still in use alongside REST API
