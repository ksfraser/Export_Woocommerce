# ksf_FA_Woocommerce → WooCommerce REST v3 — Product Sync Gap Matrix

> **Scope of audit (live push path, verified):**
> - `ProductExportService::buildProductData()` + helpers — the **only** product payload builder actually invoked at runtime (used by `exportProduct`, `exportAllSimpleProducts`, `updateSimpleProducts`, and the event listener via `ItemEventListener::sync`).
> - `ProductExportService::exportVariableProduct()` — inline variation POST payloads (lines 367–383).
> - `CategoryExporter` — product *categories* taxonomy resource.
> - **Dead/orphaned (defined, never called by hooks/dispatcher/event listener):** `ProductDTO::toArray()`, `ProductDataBuilder::build()`, `VariableProductService`, `ProductExportService::productTags()`, `productDefaultAttributes()`, `productVariations()`, `addProductAttributes()`, `createVariation()`. These are test-covered but not wired into the sync path — every field only "covered" by these is a **real gap**.
> - Legacy `class.*.php` V1-era classes are not referenced anywhere in the live path → out of scope as dead code.

**Legend:** `Writable` = writable in REST v3 schema+write-path (per `woo_product_fields.md`/`woo_attributes_fields.md`). `Covered` = actually emitted in a live push payload. `Gap?` = YES if a writable field is not covered, or covered incorrectly (BUG).

---

## 1. Products (`POST/PUT /products`) — simple + variable parent

| Woo field | Writable | Covered? | Where (file:method) | Gap? | Suggested home |
|---|---|---|---|---|---|
| `name` | yes | YES | ProductExportService::buildProductData (line 36) | no | n/a (FA `stock_master.description`) |
| `sku` | yes | YES | buildProductData (35) | no | n/a |
| `type` | yes | YES | buildProductData (37) / determineProductType (231) | no¹ | n/a |
| `status` | yes | no | — | YES (minor; always defaults `publish`) | Sales |
| `slug` | yes | no | — | YES (minor; server auto-gen) | n/a |
| `description` | yes | YES | buildProductData (42) | no | n/a (`long_description`) |
| `short_description` | yes | no | only dead `VariableProductService` parent (223) | YES | Sales |
| `regular_price` | yes | YES | buildProductData (38) | no | n/a (`stock_master.price`) |
| `sale_price` | yes | no | DTO stores it, `toArray()` never emits (196 vs 519) | **YES** | Sales (promo/sale pricing) |
| `date_on_sale_from` / `_gmt` | yes | no | DTO stores, never emits (197) | **YES** | Sales |
| `date_on_sale_to` / `_gmt` | yes | no | DTO stores, never emits (198) | **YES** | Sales |
| `manage_stock` | yes | YES | buildProductData (47) | no | n/a |
| `stock_quantity` | yes | YES | buildProductData (46) | no | n/a (`instock`) |
| `stock_status` | yes | no | — writes legacy `in_stock` instead (48) | **YES (BUG)** | Sales/CRM (inventory) |
| `in_stock` (legacy V1/V2) | **ignored by V3** | YES | buildProductData (48), ProductDataBuilder (35), ProductDTO::toArray (533) | **BUG — no-op, out-of-stock never propagates** | remove / replace with `stock_status` |
| `backorders` | yes | no | legacy set `"notify"`; new path none | YES | Sales/CRM (inventory policy) |
| `low_stock_amount` | yes | no | — | YES | Sales/CRM |
| `sold_individually` | yes | no | legacy set false; new path none | YES (minor) | Sales/CRM |
| `weight` | yes | YES | addDimensionsAndWeight (77, 97) | no | n/a |
| `weight_unit` | **no such field** | YES | addDimensionsAndWeight (80, 99) | BUG (ignored; units are store-level) | remove |
| `dimensions.length/width/height` | yes | YES | addDimensionsAndWeight (105–109) | no | n/a |
| `dimensions.unit` | **no such field** | YES | addDimensionsAndWeight (111) | BUG (ignored) | remove |
| `shipping_class` | yes | partial | addShippingAttributes (164) — hardcoded `'hazardous'` only | **YES** | Sales/CRM (shipping) |
| `tax_status` | yes | no | legacy wrote `"taxable"`; new path none | **YES** | Sales (tax) |
| `tax_class` | yes | no | legacy mapped `GST`→`Standard`; new path none | **YES** | Sales (tax) |
| `virtual` | yes | no | — | YES (FA doesn't model) | n/a |
| `downloadable` | yes | no | — | YES (FA doesn't model) | n/a |
| `downloads` / `download_limit` / `download_expiry` | yes | no | — | YES (FA doesn't model) | n/a |
| `external_url` / `button_text` | yes | no | — | YES (external type never produced) | n/a |
| `purchase_note` | yes | no | — | YES (minor) | n/a |
| `reviews_allowed` | yes | no | — | YES (minor) | n/a |
| `post_password` | yes | no | — | YES (minor) | n/a |
| `menu_order` | yes | no | only dead `productVariations`; DTO stores never emits (245) | YES | Sales |
| `date_created` / `_gmt` | yes (schema says ro, write path sets it) | no | — | YES (minor) | n/a |
| `featured` | yes | no | — | YES (FA doesn't model) | n/a |
| `catalog_visibility` | yes | no | — | YES (defaults `visible`, fine) | n/a |
| `categories` | yes | no | **CategoryExporter creates taxonomy terms but never attaches `categories` to the product payload** | **YES (high)** | Sales (stock_category ↔ woo_categories_xref already exists) |
| `tags` | yes | no | dead `productTags()` (615) never called | **YES** | Sales (item tags) |
| `brands` | yes (schema) / no write path | no | — | no (write path absent upstream) | n/a |
| `upsell_ids` / `cross_sell_ids` | yes | no | DTO stores, never emits (235–236) | YES | n/a |
| `parent_id` | yes | no | DTO stores (237), never emits | YES | n/a |
| `grouped_products` | yes (schema ro, code writes) | no | — | no (grouped type not produced) | n/a |
| `attributes` | yes | YES (variable only) | getProductAttributes (284–318) | **partial** — simple products never get attributes; no `id`/`position` | FA_ProductAttributes |
| `default_attributes` | yes (variable) | no | dead `productDefaultAttributes()` (651) never called | **YES** | FA_ProductAttributes |
| `images` | yes | YES | addImages (120–143) | no² | n/a (product_media) |
| `meta_data` | yes | YES | addShippingAttributes (168), addProductIdentifiers (203–210) | partial — see `global_unique_id` | n/a |
| `global_unique_id` (GTIN/UPC/EAN) | yes | no | written as `meta_data._gtin/_upc/_ean` instead | **YES** | FA_ProductAttributes (product_identifiers) |
| `inventory_delta` | write-only | no | — | no (optional) | Sales/CRM |

¹ `determineProductType` can return `'variation'`, which is **not a valid `/products` `type` enum** (simple/grouped/external/variable). Children are skipped by the event listener and `exportAllSimpleProducts` filters them, but any direct call on a child sends an invalid payload (latent hazard).
² `images.src` only works if it's an absolute URL — relative `media_url`/`file_path` values are silently dropped by server-side upload.

**Fields WRITTEN that are read-only / non-schema (BUGS):** `in_stock` (V3 ignores it — products stay `instock` even at qty 0), `weight_unit` (top-level), `dimensions.unit`. The module writes no genuine read-only field (no `grouped_products`/`date_created`/`shipping_class_id` write).

---

## 2. Variations (`POST/PUT /products/{id}/variations`)

Two live builders: `ProductExportService::exportVariableProduct` (lines 367–383) and (dead) `VariableProductService::buildVariations` (265–295). The **live** one (ProductExportService) is the gap source.

| Woo field | Writable | Covered (live path) | Where | Gap? | Suggested home |
|---|---|---|---|---|---|
| `sku` | yes | YES | exportVariableProduct (369) / buildVariations (279) | no | n/a |
| `regular_price` | yes | YES | (370) / (280) | no | n/a |
| `sale_price` | yes | no | — | **YES** | Sales (variation-level sale) |
| `date_on_sale_from` / `_gmt` | yes | no | — | **YES** | Sales |
| `date_on_sale_to` / `_gmt` | yes | no | — | **YES** | Sales |
| `status` | yes | no (live) | dead buildVariations sets `publish` (281) | YES (minor) | n/a |
| `stock_quantity` | yes | **ignored** (live) | exportVariableProduct (372) sends it **without `manage_stock`**; variations handler only applies qty when `manage_stock` true → silently dropped | **YES (BUG)** | Sales/CRM |
| `manage_stock` | yes | no (live) / YES (dead) | dead buildVariations (286) | **YES** | Sales/CRM |
| `stock_status` | yes | no | — | **YES (BUG)** | Sales/CRM |
| `in_stock` | ignored by V3 | no | — | no | — |
| `backorders` | yes | no | — | YES | Sales/CRM |
| `low_stock_amount` | yes | no | — | YES | Sales/CRM |
| `attributes` | yes | YES | (371) / getAttributesForVariation (337) — `{name, option}` (term name), correct | no | FA_ProductAttributes |
| `weight` / `dimensions` | yes | no | — | YES | FA_ProductAttributes / Sales |
| `shipping_class` | yes | no | — | YES | Sales/CRM |
| `image` | yes | no | — | YES (minor) | n/a (parent images inherited) |
| `gallery_image_ids` | yes | no | — | YES (minor) | n/a |
| `virtual` / `downloadable` / `downloads` / `download_limit` / `download_expiry` | yes | no | — | YES (FA doesn't model) | n/a |
| `tax_class` | yes | no | — | YES | Sales (tax) |
| `tax_status` | **effectively read-only** (schema has it, **no `set_tax_status` in write handler** — inherits parent) | no | — | no (do NOT write) | n/a |
| `menu_order` | yes | no | — | YES (minor) | n/a |
| `meta_data` | yes | no | — | YES | n/a |
| `inventory_delta` | write-only | no | — | no | Sales/CRM |

---

## 3. Attribute / taxonomy / tax resources

| Resource | Fields covered (live) | Uncovered writable fields | Gap? | Suggested home |
|---|---|---|---|---|
| `/products/attributes` | — (module only sends per-product `attributes` with `name`, never global attributes) | `name`, `slug`, `type`, `order_by`, `has_archives` | **YES — no global-attribute registry, no `id` reuse; Woo creates custom per-product attributes each sync** | FA_ProductAttributes |
| `/products/attributes/{id}/terms` | — | `name`, `slug`, `description`, `menu_order` | YES | FA_ProductAttributes |
| `/products/shipping_classes` | — (only hardcoded slug `'hazardous'` in product payload) | `name`, `slug`, `description` | **YES** | Sales/CRM |
| `/products/categories` | `name`, `slug`, `description`, `menu_order` (CategoryExporter 116–121) | `parent`, `display`, `image`, `menu_order` (parent mapping not exported) | partial — terms created, never linked to products | Sales (stock_category) |
| `/products/tags` | — (dead `productTags()` returns `{name, slug}` for Woo v1 shape) | `name`, `slug`, `description` | **YES** | Sales (item tags) |
| `/taxes` (tax rates) | — | `country,state,postcode(s),city,cities,rate,name,priority,compound,shipping,order,class` | **YES — no tax-rate sync at all; `tax_class`/`tax_status` on products unsent** | Sales (tax) / CRM |

---

## 4. Field coverage summary

- **Live product payload fields actually written (14):** `sku, name, type, regular_price, description, stock_quantity, manage_stock, in_stock*, weight, dimensions, images, shipping_class, meta_data, attributes` (variable only). (*`in_stock`, plus `weight_unit`/`dimensions.unit`, are non-functional writes.)
- **Writable product fields NOT covered:** `stock_status, backorders, low_stock_amount, sold_individually, sale_price, date_on_sale_from/to, short_description, status, slug, menu_order, tax_status, tax_class, categories, tags, default_attributes, global_unique_id, featured, catalog_visibility, virtual, downloadable, downloads, download_limit, download_expiry, external_url, button_text, purchase_note, reviews_allowed, post_password, date_created, upsell_ids, cross_sell_ids, parent_id`.
- **Variation writable fields NOT covered (live):** `sale_price, date_on_sale_from/to, stock_status, backorders, low_stock_amount, manage_stock (live path), weight, dimensions, shipping_class, tax_class, image, gallery_image_ids, menu_order, meta_data`; `stock_quantity` sent but **ignored** (no `manage_stock`).

**Gap count (writable fields with a real coverage gap, incl. BUG rows):**
Products **33**, Variations **18**, related resources **5** (attributes, terms, shipping-classes, tags, taxes) → **56 total**. Of these, **4 are BUGS** (fields written incorrectly/ineffectively): `in_stock` no-op, variation `stock_quantity` without `manage_stock` (dropped), `weight_unit`, `dimensions.unit`.

---

## 5. Top 10 gaps by sync-completeness impact

| # | Gap | Impact | Home |
|---|---|---|---|
| 1 | **`categories` never attached to products** — CategoryExporter makes terms, but product payloads carry no `categories`; products land uncategorised | Products invisible in storefront category browsing | Sales (stock_category + woo_categories_xref) |
| 2 | **`sale_price` + `date_on_sale_*` entirely unused** — DTO parses them but `toArray()`/`buildProductData()` never emit; no promo-sale sync (legacy `class.woo.php` used to write from a sale table) | No sale pricing reaches Woo | Sales (promo pricing) |
| 3 | **`stock_status` never set (`in_stock` no-op)** — out-of-stock items stay `instock`; `stock_quantity` only saved when `manage_stock` is on, which it is, but the derived status flag is wrong | OOS items sold | Sales/CRM |
| 4 | **Variation `stock_quantity` dropped (no `manage_stock`)** in the live `exportVariableProduct` | Variations always appear in stock with no qty | Sales/CRM |
| 5 | **`tax_class` / `tax_status` / `/taxes` not synced** — legacy `GST→Standard` mapping lost; FA tax types never pushed | Wrong tax applied on checkout | Sales (tax) |
| 6 | **`attributes` only for variable parents, never simple products; no global attribute registry / `id` reuse** — per-product custom attributes multiply | Attribute filtering/search across simple products broken | FA_ProductAttributes |
| 7 | **`default_attributes` never sent** (dead `productDefaultAttributes()`) — no pre-selected variation on frontend | Customer must pick every option | FA_ProductAttributes |
| 8 | **`tags` never sent** (dead `productTags()`, Woo-v1 shape) | No product tagging/search | Sales (item tags) |
| 9 | **`global_unique_id` unused — GTIN/UPC/EAN written as `meta_data._gtin/_upc/_ean`** (underscore-internal keys unreliable for core props) | GTIN not exposed for Square/WC sync feeds | FA_ProductAttributes (product_identifiers) |
| 10 | **`shipping_class` hardcoded `'hazardous'`; no shipping-class or weight/dimension sync per variation** | Incorrect shipping cost/eligibility | Sales/CRM (shipping) |

---

## 6. Read-only / non-schema fields we WRITE (bugs)

1. **`in_stock`** — `ProductExportService::buildProductData` (48), `ProductDataBuilder::build` (35), `ProductDTO::toArray` (533). Legacy V1/V2 body param; **V3 products & variations write handlers ignore it** (only `stock_status` is applied — `class-wc-rest-products-controller.php:964-987`). Consequence: stock status stays `instock` regardless of quantity.
2. **`weight_unit`** (top-level) — `addDimensionsAndWeight` (80, 99). Not a schema field; units are store-level.
3. **`dimensions.unit`** — `addDimensionsAndWeight` (111), `ProductDataBuilder` (101). Not a schema field.
4. *(Not written by us, but watchlist for future work):* `grouped_products` is `readonly:true` in schema yet has a live `set_children()` write path (products controller 1138–1139); variation `tax_status` appears writable in schema but has **no `set_tax_status`** in the variations write handler → effectively read-only; `date_created`/`date_created_gmt` are writable despite docs marking them read-only.
