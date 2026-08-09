# WooCommerce REST API v3 — Products (`POST /products`, `PUT/PATCH /products/{id}`)

> **Ground truth sources**
> - Schema: `plugins/woocommerce/includes/rest-api/Controllers/Version3/class-wc-rest-products-controller.php` — `get_item_schema()` (trunk, lines 1188–1866). The V3 controller **fully overrides** the V2 schema; V2 (`class-wc-rest-products-v2-controller.php`) only contributes shared write helpers (`save_product_shipping_data`, `save_taxonomy_terms`, `save_default_attributes`, `save_downloadable_files`).
> - Write handler: `prepare_object_for_database()` in the V3 controller (lines ~595–1187).
> - Docs: https://woocommerce.github.io/woocommerce-rest-api-docs/ (Product properties section).
> - Branch: `trunk` (WooCommerce dev; per-version behavior may lag/lead by one release).

**Classification rule:** a field is *Writable* if it appears in `get_item_schema()` with `context: ['view','edit']`, has **no** `readonly => true`, AND has a corresponding write path in `prepare_object_for_database()`. Fields with `readonly => true` are *Read-only*.

---

## Writable fields (create + update)

### General
| Field | Type | Default / enum | Notes |
|---|---|---|---|
| `name` | string | — | Product title. |
| `slug` | string | — | URL slug. |
| `type` | string | `simple` | Enum: `simple`, `grouped`, `external`, `variable` (from `wc_get_product_types()`). Changing type swaps the underlying product class. |
| `status` | string | `publish` | Post statuses + `future`, `auto-draft`, `trash`. |
| `featured` | boolean | `false` | |
| `catalog_visibility` | string | `visible` | Enum: `visible`, `catalog`, `search`, `hidden`. |
| `description` | string | — | Full description (`post_content`), KSES-filtered. |
| `short_description` | string | — | Excerpt (`post_excerpt`), KSES-filtered. |
| `reviews_allowed` | boolean | `true` | Comment status. |
| `post_password` | string | — | **Missing from official docs** but present in schema + write path. |
| `menu_order` | integer | — | Custom sort order. **Often missed by sync tools.** |
| `date_created` | date-time | — | Site timezone. **Docs mark read-only, but the V3 write path sets it** (`set_date_created`). |
| `date_created_gmt` | date-time | — | GMT form of `date_created`. Also actually writable. |

### Pricing & sale pricing (see `woo_pricing_fields.md`)
| Field | Type | Notes |
|---|---|---|
| `regular_price` | string | Not settable for `variable`/`grouped` (cleared on those types — set on variations instead). |
| `sale_price` | string | Not settable for `variable`/`grouped`. Empty string clears sale. |
| `date_on_sale_from` / `date_on_sale_from_gmt` | date-time | Site timezone / GMT start of sale window. |
| `date_on_sale_to` / `date_on_sale_to_gmt` | date-time | Site timezone / GMT end of sale window. |

### Inventory / stock
| Field | Type | Default / enum | Notes |
|---|---|---|---|
| `manage_stock` | boolean | `false` | |
| `stock_quantity` | integer or number | — | Number type depends on `woocommerce_manage_stock` "stock amount precision" setting. |
| `stock_status` | string | `instock` | Enum: `instock`, `outofstock`, `onbackorder`. |
| `backorders` | string | `no` | Enum: `no`, `notify`, `yes`. Only applied when `manage_stock` is on. |
| `low_stock_amount` | integer \| `null` | — | **Missing from official docs.** Writable; send `null` to clear. **Often missed.** |
| `sold_individually` | boolean | `false` | **Often missed.** |
| `sku` | string | — | Unique; empty to clear. |
| `global_unique_id` | string | — | GTIN/UPC/EAN/ISBN (newer field, in docs). |

Write-only (not in schema, accepted in request body):
| Param | Type | Notes |
|---|---|---|
| `inventory_delta` | number | Adjust stock by delta instead of absolute `stock_quantity` (only when `manage_stock`). |

### Shipping / weight
| Field | Type | Notes |
|---|---|---|
| `weight` | string | Cleared when `virtual: true`. |
| `dimensions` | object | `{ length, width, height }` — all strings. **No `unit` field** (units come from store settings, not per-product). |
| `shipping_class` | string | Slug; resolved to ID server-side (`set_shipping_class_id`). |

### External products only
| Field | Type | Notes |
|---|---|---|
| `external_url` | string (uri) | |
| `button_text` | string | |

### Tax
| Field | Type | Default / enum |
|---|---|---|
| `tax_status` | string | `taxable` — Enum: `taxable`, `shipping`, `none`. |
| `tax_class` | string | Tax class slug (e.g. `standard`, `reduced-rate`, `zero-rate`). |

### Virtual / downloadable
| Field | Type | Notes |
|---|---|---|
| `virtual` | boolean | |
| `downloadable` | boolean | `downloads`/`download_limit`/`download_expiry` only applied when downloadable. |
| `downloads` | array | Items `{ id, name, file }` (all writable). |
| `download_limit` | integer | Default `-1` (unlimited). |
| `download_expiry` | integer | Default `-1`. |

### Relations / taxonomy
| Field | Type | Notes |
|---|---|---|
| `categories` | array | Items `{ id }` — only `id` is writable; `name`/`slug` are read-only echoes. |
| `tags` | array | Items `{ id }`; also accepts `{ name }` → tag auto-created if it doesn't exist. `name`/`slug` read-only echoes. |
| `brands` | array | Items `{ id }` writable in schema, `name`/`slug` read-only. **No write path exists in the products controller** (output-only in practice, set via the brands feature elsewhere). |
| `upsell_ids` | array of int | |
| `cross_sell_ids` | array of int | |
| `parent_id` | integer | Write path exists (`set_parent_id`). |
| `grouped_products` | array of int | Grouped type only. **Schema marks `readonly => true`, but `prepare_object_for_database()` DOES write it** (`set_children`) — a documented-vs-code discrepancy. |
| `attributes` | array | Items `{ id, name, position, visible, variation, options[] }` — all writable. `id` = global attribute ID; omit and use `name` for custom per-product attributes. |
| `default_attributes` | array | Variable only. Items `{ id, name, option }`. |

### Images
| Field | Type | Notes |
|---|---|---|
| `images` | array | Items `{ id, src, name, alt }` writable; `date_created*`/`date_modified*` read-only. First item becomes featured image. `id=0` + `src` triggers server-side upload. |

### Meta
| Field | Type | Notes |
|---|---|---|
| `meta_data` | array | Items `{ id (read-only), key, value }`. Applied **last** in the write handler via `Automattic\WooCommerce\Utilities\MetaDataUtil::update()` → `WC_Data::update_meta_data()` (postmeta table). Underscore-prefixed internal keys (`_stock`, `_sale_price`, `_regular_price`, `_price`, `_manage_stock`, …) are accepted but the typed getters (`get_price()` etc.) read from product props, so raw postmeta overrides on those keys are not reliable. Use the dedicated fields above for core props. |

### Conditional (COGS feature only — `cost_of_goods_sold`, WC ≥ 9.x)
| Field | Type | Notes |
|---|---|---|
| `cost_of_goods_sold.values[].defined_value` | number | Writable. |
| `cost_of_goods_sold.defined_value_is_additive` | boolean | Writable (variations). |
| `cost_of_goods_sold.values[].effective_value` | number | Read-only. |
| `cost_of_goods_sold.total_value` | number | Read-only. |

---

## Read-only fields (output only — do NOT attempt to write)

| Field | Type | Reason |
|---|---|---|
| `id` | integer | Server-assigned. |
| `permalink` | string | |
| `date_modified` / `date_modified_gmt` | date-time | |
| `price` | string | Computed (regular or active sale). |
| `price_html` | string | |
| `on_sale` | boolean | |
| `purchasable` | boolean | |
| `total_sales` | integer | |
| `backorders_allowed` | boolean | |
| `backordered` | boolean | |
| `shipping_required` | boolean | |
| `shipping_taxable` | boolean | |
| `shipping_class_id` | string | Use `shipping_class` (slug) to write. |
| `average_rating` | string | |
| `rating_count` | integer | |
| `related_ids` | array of int | |
| `variations` | array of int | Child IDs; manage children via `/products/{id}/variations`. |
| `has_options` | boolean | |
| `permalink_template` | string | `edit` context only. |
| `generated_slug` | string | `edit` context only. |

---

## Field counts

- **Total schema properties (V3):** ~52 top-level (+ nested `downloads`/`dimensions`/`images`/`categories`/`tags`/`brands`/`attributes`/`default_attributes`/`meta_data`).
- **Writable:** 41 (46 counting the COGS subfields when enabled).
- **Read-only:** 20 (+ `grouped_products` declared read-only in schema but actually writable).

## Notable findings / discrepancies
1. `low_stock_amount` and `post_password` are **writable in the source schema but omitted from the official docs**.
2. Docs mark `date_created`/`date_created_gmt` as read-only; the V3 write path **does set them**.
3. `grouped_products` is flagged `readonly => true` in the schema but has a live write path.
4. `brands` is declared writable (id) in schema but has **no write implementation** in this controller.
5. `meta_data` writes are applied after all typed setters.
6. For `variable`/`grouped` products the write handler **clears** `regular_price`, `sale_price`, and sale dates at the product level — pricing must be set per-variation.
7. `inventory_delta` is a write-only request param not present in the schema.
