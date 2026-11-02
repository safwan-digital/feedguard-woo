# Changelog

All notable changes to FeedGuard for WooCommerce are documented here.

## [0.1.0] — 2026-11-02

Initial public release.

### Added
- Pre-flight check engine (`FeedGuard_Woo_Checks`) covering:
  - Identifiers: missing GTIN / brand / MPN, with WooCommerce core's `_global_unique_id` field, common GTIN-plugin meta keys, brand taxonomy/attributes — all filterable.
  - Images: missing main image, non-HTTPS image URL.
  - Titles: >150 characters, keyword-stuffing heuristic (caps ratio, repeated words, promotional phrases).
  - Content: missing description (long and short both empty).
  - SKUs: missing SKU, duplicate SKU (full-catalogue census, all offenders flagged).
  - Prices: missing price, zero price, sale price not lower than regular price. Grouped products skipped for price checks.
  - Publication: not published, hidden/search-only catalogue visibility.
  - Store settings: currency, base country, shipping methods, tax-enabled flag (with the Merchant Center alternative noted in the fix text).
- Page-vs-data comparison: fetches product pages via the WP HTTP API, reads JSON-LD Product offers (with `product:price:amount` fallback), compares price against raw and tax-display prices and availability against stock status. Unfetchable pages are reported as "could not be checked", never as errors.
- Admin report at WooCommerce → FeedGuard: run form (page checks toggle + limit), summary counts, store-level section, findings grouped by fix with severity badges and product edit links.
- CSV export (admin-post handler, nonce + `manage_woocommerce`): `product_id, sku, product_name, check, severity, detail, fix, edit_url`, exporting exactly the run shown on screen.
- HPOS compatibility declaration; graceful admin notice when WooCommerce is inactive.
- Privacy by design: no external calls except the store's own product pages; no telemetry.

### Notes
- Merchant Center's own diagnostics remain authoritative; FeedGuard is an early-warning tool and does not guarantee approval. This disclaimer appears in the plugin UI and documentation by design.
