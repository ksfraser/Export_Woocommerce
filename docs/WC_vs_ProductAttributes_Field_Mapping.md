# WooCommerce vs FA Product Attributes - Field Mapping Analysis

**Date**: 2026-05-08  
**Purpose**: Identify gaps between WooCommerce REST API product fields and FA Product Attributes module tables.

## WooCommerce Product Fields (via automattic/woocommerce v3.1.0)

### Core Fields
| WC Field | WC Type | Description | FA Product Attributes Table | Status |
|----------|---------|-------------|----------------------|--------|
| `id` | int | WooCommerce product ID | - | **Not stored** (Woo ID stored in FA `stock_master.woo_id`?) |
| `name` | string | Product name | `stock_master.description` | ✅ Covered |
| `slug` | string | URL slug | - | **Not in FA** |
| `permalink` | string | Product URL | - | **Not in FA** |
| `date_created` | string | Creation date | `stock_master.inactive_since`? | ⚠️ Partial |
| `date_modified` | string | Last modified | - | **Not in FA** |
| `type` | string | simple/variable/grouped/external/variation | `product_hierarchy` + custom field? | ⚠️ Via table |
| `status` | string | draft/pending/trash/publish | `stock_master.inactive`? | ⚠️ Partial |
| `featured` | bool | Featured product | - | **Not in FA** |
| `catalog_visibility` | string | visible/catalog/search/hidden | - | **Not in FA** |

### Description Fields
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `description` | string | `stock_master.long_description` | ✅ Covered |
| `short_description` | string | - | **Not in FA** |

### SKU and Inventory
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `sku` | string | `stock_master.stock_id` | ✅ Covered |
| `manage_stock` | bool | `stock_master.mb_flag`? | ⚠️ Indirect |
| `stock_quantity` | int | `stock_master.instock` | ✅ Covered |
| `stock_status` | string | `stock_master.instock` > 0 | ⚠️ Derived |
| `backorders` | string | - | **Not in FA** |
| `low_stock_amount` | int | - | **Not in FA** |

### Pricing
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `regular_price` | string | `stock_master.price` | ✅ Covered |
| `sale_price` | string | - | **Not in FA** (needs `prices` table?) |
| `date_on_sale_from` | string | - | **Not in FA** |
| `date_on_sale_to` | string | - | **Not in FA** |
| `price` | string | `stock_master.price` | ✅ Covered (derived) |
| `tax_status` | string | - | **Not in FA** |
| `tax_class` | string | - | **Not in FA** |

### Product Images
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `images` | array | **Not in FA Product Attributes** | ❌ **GAP** - Need `product_images` table |
| `images[].id` | int | - | ❌ **GAP** |
| `images[].src` | string | - | ❌ **GAP** |
| `images[].name` | string | - | ❌ **GAP** |
| `images[].alt` | string | - | ❌ **GAP** |

### Product Dimensions
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `weight` | string | **Not in FA Product Attributes** | ❌ **GAP** - Needs `product_dimensions` table |
| `dimensions.length` | string | - | ❌ **GAP** |
| `dimensions.width` | string | - | ❌ **GAP** |
| `dimensions.height` | string | - | ❌ **GAP** |

### Shipping / Dimensions (from legacy class.woo_product.php)
Based on the legacy code, these should be in FA Product Attributes:

| Field | WC Field | Recommended FA Table | Status |
|-------|----------|-------------------|--------|
| Shipping class | `shipping_class` | `product_shipping` table? | ❌ **GAP** |
| Length | `dimensions.length` | `product_dimensions.length` | ❌ **GAP** |
| Width | `dimensions.width` | `product_dimensions.width` | ❌ **GAP** |
| Height | `dimensions.height` | `product_dimensions.height` | ❌ **GAP** |
| Weight | `weight` | `product_dimensions.weight` | ❌ **GAP** |

### Product Attributes (Variable Products)
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `attributes` | array | `product_attribute_categories` + `product_attribute_values` + `product_attribute_assignments` | ✅ **Covered by FA Product Attributes** |
| `attributes[].name` | string | `product_attribute_categories.label` | ✅ Covered |
| `attributes[].options` | array | `product_attribute_values.value` | ✅ Covered |
| `attributes[].position` | int | `product_attribute_assignments.sort_order` | ✅ Covered |
| `attributes[].visible` | bool | - | ⚠️ Could add column |
| `attributes[].variation` | bool | - | ⚠️ Could add column |

### Default Attributes (for Variations)
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `default_attributes` | array | - | ❌ **GAP** - Need `product_default_attributes` table |

### Variations (Child Products)
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `variations` | array | `product_hierarchy` (parent_stock_id) | ✅ **Covered** |
| Variation `attributes` | array | `product_attribute_assignments` | ✅ Covered |
| Variation `sku` | string | `stock_master.stock_id` | ✅ Covered |
| Variation `price` | string | `stock_master.price` | ✅ Covered |

### Categories and Tags
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `categories` | array | **Not in FA Product Attributes** | ❌ **GAP** - Needs `product_categories` table |
| `tags` | array | **Not in FA Product Attributes** | ❌ **GAP** - Needs `product_tags` table |

### Linked Products
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `upsell_ids` | array | - | ❌ **GAP** |
| `cross_sell_ids` | array | - | ❌ **GAP** |

### Product Data (Meta)
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `meta_data` | array | - | ❌ **GAP** - Could use `product_meta` table |

### Downloads (Virtual Products)
| WC Field | WC Type | FA Product Attributes Table | Status |
|----------|---------|----------------------|--------|
| `downloads` | array | - | ❌ **GAP** |
| `download_limit` | int | - | ❌ **GAP** |
| `download_expiry` | int | - | ❌ **GAP** |

## Recommended New Tables for FA Product Attributes

### 1. `product_images`
```sql
CREATE TABLE `product_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stock_id` VARCHAR(20) NOT NULL,
    `image_url` VARCHAR(500),
    `image_path` VARCHAR(500),
    `alt_text` VARCHAR(255),
    `sort_order` INT DEFAULT 0,
    `is_featured` TINYINT DEFAULT 0,
    INDEX idx_stock (`stock_id`)
);
```

### 2. `product_dimensions`
```sql
CREATE TABLE `product_dimensions` (
    `stock_id` VARCHAR(20) PRIMARY KEY,
    `weight` DECIMAL(10,4) DEFAULT NULL,
    `length` DECIMAL(10,4) DEFAULT NULL,
    `width` DECIMAL(10,4) DEFAULT NULL,
    `height` DECIMAL(10,4) DEFAULT NULL,
    `weight_unit` VARCHAR(10) DEFAULT 'kg',
    `dimension_unit` VARCHAR(10) DEFAULT 'cm'
);
```

### 3. `product_categories` (if not using FA's built-in categories)
```sql
CREATE TABLE `product_categories` (
    `stock_id` VARCHAR(20) NOT NULL,
    `category_id` INT NOT NULL,
    `is_primary` TINYINT DEFAULT 0,
    PRIMARY KEY (`stock_id`, `category_id`)
);
```

### 4. `product_tags`
```sql
CREATE TABLE `product_tags` (
    `stock_id` VARCHAR(20) NOT NULL,
    `tag` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`stock_id`, `tag`)
);
```

## Summary of Gaps

### High Priority (Required for basic sync)
1. ❌ **Product Images** - No table exists
2. ❌ **Product Dimensions** (weight, length, width, height) - No table exists

### Medium Priority
3. ❌ **Product Categories** - Not in FA Product Attributes (but FA has its own category system)
4. ❌ **Product Tags** - No table exists
5. ❌ **Sale Price** - No table for pricing rules

### Low Priority (Advanced Features)
6. ❌ **Product Meta/Custom Fields** - No generic meta table
7. ❌ **Linked Products** (upsells, cross-sells) - Not critical
8. ❌ **Downloads** - Only for virtual products

## Recommendation for FA Product Attributes Module

Please add these tables to the FA Product Attributes module:

1. **`product_dimensions`** - For weight, length, width, height (essential for shipping)
2. **`product_images`** - For product gallery images
3. **`product_shipping_class`** - If shipping classes needed

The export_woocommerce module can then query these tables when building WooCommerce product data.

## Fields We CAN Handle Now (without new tables)

From the existing FA Product Attributes tables:
- ✅ Attributes/Variations (via `product_attribute_*`)
- ✅ Parent-child relationships (via `product_hierarchy`)
- ✅ Basic product data (via `stock_master`)

## Next Steps

1. FA Product Attributes team: Add `product_dimensions` and `product_images` tables
2. export_woocommerce: Update `ProductExportService::buildProductData()` to query these new tables
3. Consider: Should dimensions/images be part of FA Product Attributes or a separate module?
