# WooCommerce REST API v3 — Attributes, Terms, Variations, Shipping Classes, Categories, Tags, Taxes

> **Ground truth sources** (all `plugins/woocommerce/includes/rest-api/Controllers/`)
> - Attributes: `Version3/class-wc-rest-product-attributes-controller.php` + `Version1/class-wc-rest-product-attributes-v1-controller.php` (`get_item_schema()` line 480).
> - Attribute terms: `Version3/class-wc-rest-product-attribute-terms-controller.php` + `Version1/class-wc-rest-product-attribute-terms-v1-controller.php` (`get_item_schema()` line 191).
> - Variations: `Version3/class-wc-rest-product-variations-controller.php` (`get_item_schema()` line 558; write handler line 203).
> - Shipping classes: `Version3/class-wc-rest-product-shipping-classes-controller.php` + `Version1/class-wc-rest-product-shipping-classes-v1-controller.php` (`get_item_schema()` line 87).
> - Categories: `Version3/class-wc-rest-product-categories-controller.php` (`get_item_schema()` line 96; meta write handler line 222).
> - Tags: `Version3/class-wc-rest-product-tags-controller.php` + `Version1/class-wc-rest-product-tags-v1-controller.php` (`get_item_schema()` line 87).
> - Taxes: `Version3/class-wc-rest-taxes-controller.php` (`get_item_schema()` line 73) + `Version1/class-wc-rest-taxes-v1-controller.php` (`get_item_schema()` line 644).
> - Docs: https://woocommerce.github.io/woocommerce-rest-api-docs/

---

## 1. Product attributes (`POST/PUT /products/attributes`)

Schema: `class-wc-rest-product-attributes-v1-controller.php:480`.

| Field | Type | Default / enum | Writable | Read-only |
|---|---|---|---|---|
| `id` | integer | — | | ✔ (server-assigned) |
| `name` | string | — | ✔ | |
| `slug` | string | — | ✔ (sanitized; validated for reserved names/length) | |
| `type` | string | `select` | ✔ | Enum: `select`, `text` (from `wc_get_attribute_types()`, extensible). |
| `order_by` | string | `menu_order` | ✔ | Enum: `menu_order`, `name`, `name_num`, `id`. |
| `has_archives` | boolean | `false` | ✔ | |

Write-only request params (not in schema):
- `generate_slug` — V3 `create_item()` reads it (`true` → auto-generates a unique slug from the name).

**Field counts:** 6 total; **5 writable**, 1 read-only.

---

## 2. Attribute terms (`POST/PUT /products/attributes/{attribute_id}/terms`)

Schema: `class-wc-rest-product-attribute-terms-v1-controller.php:191`.

| Field | Type | Writable | Read-only |
|---|---|---|---|
| `id` | integer | | ✔ |
| `name` | string | ✔ | |
| `slug` | string | ✔ (sanitized) | |
| `description` | string | ✔ (KSES-filtered) | |
| `menu_order` | integer | ✔ | |
| `count` | integer | | ✔ (computed: published products) |

**Field counts:** 6 total; **4 writable**, 2 read-only.

---

## 3. Product variations (`POST/PUT /products/{product_id}/variations`, `/variations/{id}`)

Schema: `class-wc-rest-product-variations-controller.php:558` (V3). Write handler line 203.

### Writable (create + update)
| Field | Type | Default / enum | Notes |
|---|---|---|---|
| `description` | string | — | Variation description (KSES). |
| `sku` | string | — | |
| `global_unique_id` | string | — | GTIN/UPC/EAN/ISBN. |
| `regular_price` | string | — | |
| `sale_price` | string | — | |
| `date_on_sale_from` / `_gmt` | date-time | — | Sale window start (site tz / GMT). |
| `date_on_sale_to` / `_gmt` | date-time | — | Sale window end. |
| `status` | string | `publish` | Enum: post statuses. |
| `virtual` | boolean | `false` | |
| `downloadable` | boolean | `false` | |
| `downloads` | array | — | `{ id, name, file }`. |
| `download_limit` | integer | `-1` | |
| `download_expiry` | integer | `-1` | |
| `tax_class` | string | — | |
| `manage_stock` | boolean \| string | `false` | |
| `stock_quantity` | integer | — | |
| `stock_status` | string | `instock` | Enum: `instock`, `outofstock`, `onbackorder`. |
| `backorders` | string | `no` | Enum: `no`, `notify`, `yes`. |
| `low_stock_amount` | integer \| `null` | — | **Missing from official docs.** Send `null` to clear. |
| `weight` | string | — | Via `save_product_shipping_data`. |
| `dimensions` | object | — | `{ length, width, height }`. |
| `shipping_class` | string | — | Slug. |
| `image` | object | — | `{ id, src, name, alt }` writable; date fields read-only. |
| `gallery_image_ids` | array of int | — | **Newer field, missing from official docs.** |
| `attributes` | array | — | Variation attributes `{ id, name, option }` — option is the term **name** (resolved to slug server-side). Only attributes marked `variation: true` on the parent are applied. |
| `menu_order` | integer | — | |
| `meta_data` | array | — | `{ id (ro), key, value }`. |

Write-only request params: `inventory_delta` (delta stock adjustment when `manage_stock`).

Conditional COGS field when enabled: `cost_of_goods_sold` (same shape as products; see `woo_product_fields.md`).

### Read-only
`id`, `type`, `date_created`, `date_modified`, `permalink`, `price`, `on_sale`, `purchasable`, `backorders_allowed`, `backordered`, `shipping_class_id`, `parent_id`.

> **Schema-vs-write-path anomaly:** `tax_status` is declared in the variations schema (`view`/`edit`, default `taxable`) but **no `set_tax_status` call exists** in the variation write handler — variations inherit tax status from the parent. Treat as effectively read-only.

### Field counts
~39 top-level schema props; **~25 writable** (+ COGS), **12 read-only** (2 of which, `type`/`parent_id`, are omitted from the official docs).

---

## 4. Shipping classes (`POST/PUT /products/shipping_classes`)

Schema: `class-wc-rest-product-shipping-classes-v1-controller.php:87`. V3 controller only adds a read-only `/slug-suggestion` route.

| Field | Type | Writable | Read-only |
|---|---|---|---|
| `id` | integer | | ✔ |
| `name` | string | ✔ | |
| `slug` | string | ✔ (sanitized) | |
| `description` | string | ✔ (KSES) | |
| `count` | integer | | ✔ (computed) |

**Field counts:** 5 total; **3 writable**, 2 read-only.

---

## 5. Product categories (`POST/PUT /products/categories`)

Schema: `class-wc-rest-product-categories-controller.php:96` (V3). Meta write handler `update_term_meta_fields()` line 222.

| Field | Type | Default / enum | Writable | Read-only |
|---|---|---|---|---|
| `id` | integer | — | | ✔ |
| `name` | string | — | ✔ | |
| `slug` | string | — | ✔ (sanitized) | |
| `parent` | integer | — | ✔ | Parent category ID. |
| `description` | string | — | ✔ (KSES) | |
| `display` | string | `default` | ✔ | Enum: `default`, `products`, `subcategories`, `both` (stored as `display_type` term meta). |
| `image` | object | — | ✔ | `{ id, src, alt, name }` writable; date fields read-only. `src` triggers server-side upload. |
| `menu_order` | integer | — | ✔ | Stored as `order` term meta. |
| `count` | integer | — | | ✔ (computed) |

**Field counts:** 9 total; **7 writable**, 2 read-only.

---

## 6. Product tags (`POST/PUT /products/tags`)

Schema: `class-wc-rest-product-tags-v1-controller.php:87`.

| Field | Type | Writable | Read-only |
|---|---|---|---|
| `id` | integer | | ✔ |
| `name` | string | ✔ | |
| `slug` | string | ✔ (sanitized) | |
| `description` | string | ✔ (KSES) | |
| `count` | integer | | ✔ (computed) |

**Field counts:** 5 total; **3 writable**, 2 read-only.

---

## 7. Tax rates (`POST/PUT /taxes`)

Schema: `class-wc-rest-taxes-v1-controller.php:644` + V3 additions at `class-wc-rest-taxes-controller.php:73`.

| Field | Type | Default / enum | Writable | Read-only | Notes |
|---|---|---|---|---|---|
| `id` | integer | — | | ✔ | |
| `country` | string | — | ✔ | | ISO 3166 code. |
| `state` | string | — | ✔ | | |
| `postcode` | string | — | ✔ | | **Deprecated (WC 5.3)** — single value only; use `postcodes`. Still writable. |
| `city` | string | — | ✔ | | **Deprecated (WC 5.3)** — use `cities`. Still writable. |
| `postcodes` | array of string | — | ✔ | | V3-only; joined with `;` into `postcode` server-side. |
| `cities` | array of string | — | ✔ | | V3-only; joined into `city`. |
| `rate` | string | — | ✔ | | Tax rate percent. |
| `name` | string | — | ✔ | | |
| `priority` | integer | `1` | ✔ | | |
| `compound` | boolean | `false` | ✔ | | Compound rate. |
| `shipping` | boolean | `true` | ✔ | | Applied to shipping too. |
| `order` | integer | — | ✔ | | Display order. |
| `class` | string | `standard` | ✔ | | Enum: `standard` + registered tax class slugs. |

**Field counts:** 14 total; **13 writable**, 1 read-only.

---

## Cross-resource summary

| Resource | Endpoint | Total fields | Writable | Read-only |
|---|---|---|---|---|
| Products | `/products` | ~52 | ~41 | ~20 |
| Attributes | `/products/attributes` | 6 | 5 | 1 |
| Attribute terms | `/products/attributes/{id}/terms` | 6 | 4 | 2 |
| Variations | `/products/{id}/variations` | ~39 | ~25 | 12 |
| Shipping classes | `/products/shipping_classes` | 5 | 3 | 2 |
| Categories | `/products/categories` | 9 | 7 | 2 |
| Tags | `/products/tags` | 5 | 3 | 2 |
| Tax rates | `/taxes` | 14 | 13 | 1 |

### Read-only fields (never write these)
- `id`, `count` (all term/tax resources), `price`, `on_sale`, `permalink`, `date_modified`, `purchasable`, `total_sales`, `backorders_allowed`, `backordered`, `shipping_required`, `shipping_taxable`, `shipping_class_id`, `average_rating`, `rating_count`, `related_ids`, `variations`, `has_options`, `permalink_template`, `generated_slug`, `type`/`parent_id` (variations), `total_value`/`effective_value` (COGS).

### Notable writable fields often missed
- `low_stock_amount` (products + variations) — in source schema, missing from official docs.
- `sold_individually` (products) — docs list it, easy to overlook.
- `menu_order` (products, variations, categories, terms) — sort control.
- `meta_data` (products, variations) — custom fields; internal `_`-prefixed keys accepted but not reliable for core props.
- Tax-rate `compound` and `shipping` booleans, and V3 `postcodes`/`cities` arrays.
- `post_password` (products).
- Variations `gallery_image_ids`.
