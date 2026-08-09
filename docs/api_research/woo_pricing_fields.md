# WooCommerce REST API v3 — Sale Pricing Mechanism

> Sources: `class-wc-rest-products-controller.php` (price/sale write block, lines ~810–860), `class-wc-rest-product-variations-controller.php` (lines 333–357), official docs (Product properties / Product variation properties).

## The mechanism (simple two-field set + optional schedule)

A sale is driven by exactly **five REST fields** (per product or per variation):

| Field | Type | Meaning |
|---|---|---|
| `sale_price` | string | The discounted price. Empty string `""` (or omitting it) = no sale. |
| `date_on_sale_from` | date-time | Start of sale window, in the **site's timezone**. |
| `date_on_sale_from_gmt` | date-time | Start of sale window, **as GMT**. |
| `date_on_sale_to` | date-time | End of sale window, site timezone. |
| `date_on_sale_to_gmt` | date-time | End of sale window, GMT. |

Server-side mapping (products controller `prepare_object_for_database()`):

```php
$product->set_regular_price( $request['regular_price'] );   // if present
$product->set_sale_price( $request['sale_price'] );          // if present
$product->set_date_on_sale_from( $request['date_on_sale_from'] );           // site tz
$product->set_date_on_sale_from( strtotime( $request['date_on_sale_from_gmt'] ) ); // GMT → timestamp
$product->set_date_on_sale_to( $request['date_on_sale_to'] );
$product->set_date_on_sale_to( strtotime( $request['date_on_sale_to_gmt'] ) ?: null );
```

Notes:
- `date_on_sale_*` are **optional**. Set `sale_price` alone → permanent sale. Clear the sale by sending `sale_price: ""`.
- The `_gmt` variants and the non-GMT variants are independent; both are written. For timezone-safe syncing, prefer the `_gmt` forms (or set both consistently).
- These map to product postmeta `_sale_price`, `_sale_price_dates_from`, `_sale_price_dates_to`. WooCommerce derives the live `_price` (sale when `_sale_price_dates_from` ≤ now ≤ `_sale_price_dates_to`). The API output `price` field reflects the *effective* price; `on_sale` reflects whether a sale is currently active.

## Variable & grouped products — sale price lives on variations

In the products controller, for type `variable` or `grouped`:

```php
$product->set_regular_price( '' );
$product->set_sale_price( '' );
$product->set_date_on_sale_to( '' );
$product->set_date_on_sale_from( '' );
$product->set_price( '' );
```

I.e. the API **silently clears** sale fields sent to the parent of a variable/grouped product. To run a sale on a variable product you must set `regular_price`/`sale_price`/`date_on_sale_from/to` on each variation (`PUT /products/{id}/variations/{vid}`), not on the parent. `price` on the parent is derived from its variations.

## End-of-sale handling

- Sale ends when `date_on_sale_to`/`date_on_sale_to_gmt` passes. WooCommerce flips the effective price back to `regular_price` and removes `_sale_price` from the meta lookup on cron (`wc_scheduled_sales`-related events / `wc_delete_products_sale` cache handling).
- API does **not** auto-clear the `sale_price` string value — the stored `_sale_price` remains, but the effective price returned in `price`/`on_sale` reflects the schedule.

## Scheduled sales in WooCommerce 8.x+

- There is **no separate REST endpoint or field set** for "scheduled sales." The new block/Gutenberg product editor's *Schedule sale* UI (Woo 8.x+) writes to the **same** `sale_price` + `date_on_sale_from`/`date_on_sale_to` fields described above.
- Therefore a sync tool can fully replicate scheduled sales using the standard `PUT /products/{id}` (and per-variation) payload — no extra fields, no new endpoint.
- `wc_get_product_ids_on_sale()` / the `on_sale` API flag already respect the schedule, so querying `GET /products?on_sale=true` reflects only *currently active* sales.

## Gotchas for a sale-pricing sync tool

1. **Parent vs variation:** never write sale fields on a variable product's parent — they get cleared. Target each variation.
2. **Clearing a sale:** send `sale_price: ""` (empty string), and optionally `date_on_sale_from`/`date_on_sale_to` as `null`/`""`.
3. **Timezone:** use `date_on_sale_from_gmt` / `date_on_sale_to_gmt` (or keep the store and API client timezones aligned) to avoid off-by-hour scheduling.
4. **Precision:** prices are strings with 2 decimals (e.g. `"19.99"`); sending floats is tolerated but strings are canonical.
5. **Ordering within a request:** on the products write path, sale/price setters run *before* `meta_data` is applied; do not try to sneak `_sale_price` through `meta_data` on the same request — the effective price is computed from the typed props.
6. **`sale_price` greater than `regular_price`:** not rejected by the API, but yields no sale (and can confuse `price` computation); guard in the sync layer.
7. No "bulk scheduled sale" resource exists; apply per-product / per-variation updates (batch endpoints accept the same fields).
