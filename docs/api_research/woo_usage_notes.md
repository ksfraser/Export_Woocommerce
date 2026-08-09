# ksf_FA_Woocommerce → WooCommerce — Sync Usage Notes

> Companion to `woo_gap_analysis.md`. Answers: which builder methods exist, hardcoded vs mapped fields, where variation/sale-pricing would land, and version-drift hazards.

---

## 1. Builder methods inventory

### Live (actually invoked at runtime)
| Method | File:line | Role | Fields emitted |
|---|---|---|---|
| `ProductExportService::buildProductData()` | ProductExportService.php:30 | **The one true payload builder** — used by exportProduct, exportAllSimpleProducts, updateSimpleProducts, exportVariableProduct (parent), and the event listener | `sku,name,type,regular_price,description,stock_quantity,manage_stock,in_stock,weight[,weight_unit],dimensions[,unit],images,shipping_class,meta_data,attributes(variable only)` |
| `addDimensionsAndWeight()` | :74 | weight + L×W×H from `product_dimensions` / `stock_master.weight` | `weight, weight_unit*, dimensions{length,width,height,unit*}` |
| `addImages()` | :120 | images from `product_media` | `images[{src,name,alt}]` |
| `addShippingAttributes()` | :148 | from `product_shipping_attributes` | `shipping_class='hazardous'`, `meta_data[hs_code]` |
| `addProductIdentifiers()` | :187 | from `product_identifiers` | `meta_data[_upc,_ean,_gtin]` |
| `getProductAttributes()` | :284 | FA ProductAttributes tables (`product_attribute_assignments`/`_categories`/`_values`) | `attributes[{name,options,visible,variation}]` (variable type only) |
| `determineProductType()` | :231 | product_hierarchy child/parent lookup | `type: simple|variable|variation` |
| `ProductExportService::exportVariableProduct()` | :331 | parent + inline variations | parent: same as buildProductData; variations: `{sku,regular_price,attributes,stock_quantity}` |
| `CategoryExporter::sendNewCategoriesToWoo/exportCategory` | CategoryExporter.php:92/153 | taxonomy terms from `stock_category` | `{name,slug,description,menu_order}` |

### Dead / orphaned (defined, test-covered, but **never called** by hooks, cron, dispatcher, or event listener)
| Method | File:line | Why it matters |
|---|---|---|
| `ProductDTO::toArray()` | DTO/ProductDTO.php:517 | DTO has 40+ fields (sale_price, tax, backorders, menu_order, default_attributes, grouped_products…) but `toArray()` emits only a 9-field subset → the DTO is **not** the push shape. Also references undefined `$this->images` (PHP 7.3: null, silently drops images). |
| `ProductDataBuilder::build()` | ProductDataBuilder.php:17 | Exact duplicate of `buildProductData()` (even has a broken `getTableName('')` double-prefix in `determineProductType`). Redundant copy — drift risk. |
| `VariableProductService::exportVariableProduct/buildVariations` | VariableProductService.php:202/265 | The *good* variable path (sets `manage_stock`, `status`, proper attributes) — but nothing wires it up. Dispatcher's `export_products` only calls `exportAllSimpleProducts`. |
| `productTags()` | ProductExportService.php:615 | returns Woo-v1 shape `{name,slug}`; never attached to a product payload. |
| `productDefaultAttributes()` | :651 | ready-made `default_attributes` query; never called. |
| `productVariations()` | :691 | ready-made variations reader; never called. |
| `addProductAttributes()` / `createVariation()` | :493/:508 | thin wrappers; never called. |

**Conclusion:** `ProductExportService::buildProductData()` is the single choke point. All new fields (sale pricing, stock_status, categories, tags, tax, default_attributes) can be added there + a couple of named builder methods, mirroring the existing `add*()` pattern.

---

## 2. Hardcoded vs mapped

**Hardcoded / magic values (no FA source):**
- `shipping_class = 'hazardous'` whenever `is_hazardous` — assumes a shipping class with that exact slug exists in Woo; no create-if-missing, no mapping table.
- `manage_stock = true`, `in_stock = bool` — stock policy hardcoded per push; `backorders` (legacy `"notify"`) and `sold_individually` (legacy `false`) were dropped entirely in the new path.
- Legacy (now dead) hardcoded mapping: `tax_class = "GST"` → `"Standard"` (Woo ships standard/reduced-rate/zero-rate). **Lost** in the new path — no tax mapping at all.
- Category `menu_order = category_id`, slug = sanitized description — a heuristic, no FA field.

**Mapped from FA tables:**
- `sku` ← `stock_master.stock_id`; `name` ← `description`; `regular_price` ← `price`; `stock_quantity` ← `instock`; `description` ← `long_description`.
- `weight`/`dimensions` ← `product_dimensions` (falls back to `stock_master.weight`).
- `images` ← `product_media` (sort_order, media_url/file_path, alt_text).
- `attributes` ← `product_attribute_assignments/categories/values` (FA Product Attributes module) — **only** when type = variable.
- `categories` (terms only) ← `stock_category`.
- `meta_data.hs_code/_upc/_ean/_gtin` ← `product_shipping_attributes` / `product_identifiers`.

---

## 3. Where new field coverage would land

- **Sale pricing (simple products):** extend `buildProductData()` — add `sale_price`, `date_on_sale_from/to` from the sales/promo source. Legacy `class.woo.php` had the pattern (`woo.sale_price = s.sale_price, woo.date_on_sale_from = s.start, woo.date_on_sale_to = s.end`) → resurrect against a Sales/promo table. Read `woo_pricing_fields.md` gotchas: never put sale fields on variable **parents** (silently cleared), clear sales with `sale_price:""`, prefer `_gmt` forms, prices are 2-dp strings, guard `sale_price > regular_price`.
- **Sale pricing (variations):** add to **both** variation builders — `VariableProductService::buildVariations()` (dead but correct shape) and `ProductExportService::exportVariableProduct()`. Target the `/variations/{id}` PUT (already matching by SKU) — sale fields per variation.
- **Stock correctness:** replace `in_stock` with `stock_status` (`instock/outofstock/onbackorder`) in `buildProductData` + `ProductDTO::toArray`; add `backorders`, `low_stock_amount`, `sold_individually`; **always** set `manage_stock` alongside `stock_quantity` in `exportVariableProduct`.
- **Attributes for simple products + default attributes:** call `getProductAttributes()` for non-variable too; wire `productDefaultAttributes()` into the variable parent payload; consider a global attribute registry (POST `/products/attributes` + term sync) so `attributes[].id` is reused instead of per-product custom attributes (Square catalog mapping will thank you).
- **Categories/tags:** attach `categories` (id from `woo_categories_xref`) and `tags` to the product payload in `buildProductData`; fix `productTags()` output shape (v3 tags accept `{id}` or `{name}`).
- **GTIN:** emit `global_unique_id` (the typed field) instead of / in addition to `meta_data._gtin`.
- **Tax:** map FA tax type → `tax_class` slug + `tax_status`; optionally sync `/taxes` rates (`country,state,postcodes,cities,rate,name,priority,compound,shipping,order,class`).
- **Wiring:** either switch Dispatcher's `export_products` to `VariableProductService::exportAllVariableProducts()` or delete it to avoid two competing variable paths. Remove `ProductDataBuilder` duplication.

---

## 4. Version-drift hazards

1. **REST version mixed.** Runtime client is wired to V3 (`wc/v3/` in cron_sync.php:31 / hooks config) but `in_stock` is a V1/V2 body param — V3 ignores it (verified: no `in_stock` handling in the V3 products/variations write paths; only `stock_status`). Legacy `class.*.php` files are literal V1 API-docs copies and are dead, so they won't fix it.
2. **`grouped_products`:** schema declares `readonly:true` but `prepare_object_for_database()` DOES `set_children()` (products controller 1138–1139). Safe to write in practice; flag it in code review so nobody "fixes" it.
3. **Variation `tax_status`:** present in variations schema with default `taxable`, but the write handler has **no `set_tax_status`** — variations inherit parent tax status. Do not write it on variations (effectively read-only).
4. **`date_created`/`date_created_gmt`:** official docs mark read-only; V3 write path sets them (products controller 1150–1162). Writable in practice.
5. **`low_stock_amount`** and **`post_password`:** writable in source schema but missing from official docs — easy to under-use/over-scrub in validation layers.
6. **`inventory_delta`:** write-only request param (not in schema) — the correct way to adjust stock without a full GET/PUT round-trip; the module currently sends absolute `stock_quantity` (fine) but does a GET-then-PUT per variation.
7. **Docs-vs-code drift applies per release** (this audit used WooCommerce `trunk`); the module hard-codes nothing version-specific except the legacy `in_stock`, so pinning to V3 is stable.
8. **Schema fields with no write path:** `brands` is declared writable (id) but has no write implementation in the products controller — don't attempt it via this endpoint.
9. **COGS:** if WC ≥ 9.x with COGS enabled, `cost_of_goods_sold.values[].defined_value` is writable (products + variations) — not currently used; FA purchase cost could feed it later.
10. **`meta_data` ordering:** applied last in the write handler; underscore-internal keys (`_upc/_ean/_gtin/_sale_price/…`) are accepted but typed getters read product props, so they're unreliable for core props — never route core fields through `meta_data`.
