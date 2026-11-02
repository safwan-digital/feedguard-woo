# FeedGuard for WooCommerce

**Catch Google Merchant Center problems inside WooCommerce — before Google disapproves your products.**

Store owners usually learn their product data is broken *after* Merchant Center disapproves it: listings silently stop, and Google's error messages never say which WooCommerce field to fix. Feed validators that check an exported feed file exist — FeedGuard checks the data **at the source**, inside wp-admin, and tells you exactly what to change.

> **Honest disclaimer:** FeedGuard is an early-warning tool. Merchant Center's own diagnostics are the only authoritative verdict. A clean FeedGuard report does **not** guarantee approval — it means the common, fixable data problems were not found. FeedGuard never promises approval.

## What it checks

**Product data**
- Missing identifiers: GTIN, brand, MPN (including WooCommerce core's GTIN field and common plugin fields)
- Missing or non-HTTPS main image
- Titles over 150 characters; keyword-stuffing heuristic (ALL-CAPS blocks, repeated words, promo phrases)
- Missing description, missing SKU, **duplicate SKUs**
- Missing/zero price; sale price not lower than the regular price
- Products not published or hidden from the catalogue

**Store settings**
- Currency not set · base country not set · no shipping methods in any zone · taxes disabled (with the "or set it in Merchant Center" caveat)

**Page vs. data** (the one that matters most)
- Fetches each product page and compares the price in its structured data (JSON-LD, with a `product:price:amount` fallback) against the product data price — accepting either the raw or the tax-display price, so legitimate setups don't false-positive.
- Compares page availability vs. WooCommerce stock status.
- Pages that can't be fetched are reported as **"could not be checked"**, never as failures.

## Install

1. Download `feedguard-woo.zip` (or clone this repo and zip the `feedguard-woo/` folder).
2. WordPress admin → **Plugins → Add New → Upload Plugin** → choose the zip → **Activate**. (WooCommerce must be active; FeedGuard is HPOS-compatible.)
3. Go to **WooCommerce → FeedGuard**.

## Run a check

1. Tick **"Also fetch each product page…"** (recommended) and set the page-check limit (default 25 products, 0 = all — each page check is a real HTTP request to your own store, so big limits take time).
2. Click **Run checks**.
3. Read the report top-down: errors first, grouped by the fix, each product row linking straight to its editor.
4. **Download CSV report** exports exactly what you see (`product_id, sku, product_name, check, severity, detail, fix, edit_url`) — the per-client deliverable if you manage stores for others.

## Reading the report

- **Error** = will very likely cause a disapproval or a missing item. **Warning** = limits performance or becomes an error in common setups.
- Findings are grouped by *check*, i.e. by fix: fix one kind of problem across the whole catalogue in one pass.
- The "Why Google cares" line under each group explains the Merchant Center reasoning in one sentence.

## Known limits of the page check

- Prices rendered only by JavaScript, or changed by geolocation/currency/role plugins, can differ from what the fetch sees. A mismatch shows **both values** — treat it as "investigate", starting with the price fields, tax-display settings, and price-changing plugins. Never edit the feed to hide a mismatch.
- The fetch is unauthenticated: members-only stores will mostly show "could not be checked".
- Variable products compare at the parent ("from") price, like the feed does. Grouped products are price-skipped.

## Privacy

No external network calls except your own store's product pages. No telemetry. No accounts. No upsells, no nags — this is the point of the project.

## For developers

- GTIN/brand/MPN sources are filterable: `feedguard_woo_gtin_meta_keys`, `feedguard_woo_brand_meta_keys`, `feedguard_woo_mpn_meta_keys`.
- `FeedGuard_Woo_Checks::run_all()` accepts `page_checks`, `page_check_limit`, `include_unpublished`.
- Full design, rule catalogue and roadmap: [DESIGN.md](DESIGN.md). Testing guide: [TESTING.md](TESTING.md).

## What FeedGuard is not

It does not generate or sync feeds (use Google Listings & Ads, CTX Feed, …), does not do Meta/TikTok catalog rules, and does not fix data automatically. It reports; you fix — at the source.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
