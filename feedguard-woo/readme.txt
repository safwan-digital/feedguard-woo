=== FeedGuard for WooCommerce ===
Contributors: safwan-digital
Tags: woocommerce, google merchant center, google shopping, product feed, seo
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Catch Google Merchant Center problems inside WooCommerce — missing GTINs, bad prices, duplicate SKUs, page-vs-data mismatches — before Google disapproves your products.

== Description ==

Store owners usually find out their product data is broken *after* Google Merchant Center disapproves the products: listings silently stop and Google's error messages don't say which WooCommerce field to fix.

FeedGuard runs a pre-flight check **at the source**, inside wp-admin:

* Missing GTIN / brand / MPN (identifiers)
* Missing or non-HTTPS product images
* Titles over 150 characters and keyword-stuffed titles
* Missing descriptions and SKUs, duplicate SKUs
* Missing/zero prices and sale prices that aren't lower than the regular price
* Store basics: currency, base country, shipping methods, tax status
* **Page-vs-data comparison:** FeedGuard fetches your product pages and compares the price and availability in the page's structured data with your product data — the mismatch that causes the most Merchant Center disapprovals.

Every finding is grouped by fix, in plain language, with a direct link to the product editor. One click exports the whole report as CSV — handy if you manage client stores.

**Please note:** FeedGuard is an early-warning tool. Merchant Center's own diagnostics are the only authoritative verdict. A clean FeedGuard report does not guarantee approval — it means the common, fixable problems were not found.

**Privacy:** FeedGuard makes no external network calls except fetching your own store's product pages. No telemetry, no accounts, no upsells, ever.

FeedGuard does not generate or sync a product feed. Use it alongside your feed plugin (Google Listings & Ads, CTX Feed, etc.) — FeedGuard checks the data, the feed plugin ships it.

== Installation ==

1. Upload the `feedguard-woo` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload Plugin.
2. Activate FeedGuard for WooCommerce (WooCommerce must be active).
3. Go to WooCommerce → FeedGuard and click "Run checks".

== Frequently Asked Questions ==

= Does FeedGuard replace my feed plugin? =

No. FeedGuard never generates or uploads a feed. It checks your WooCommerce data so the feed your plugin generates passes Merchant Center review more often.

= Does a clean report mean Google will approve my products? =

No. Merchant Center checks more than any pre-flight tool can (landing page quality, policies, account setup). FeedGuard removes the common data causes of disapproval early. Merchant Center diagnostics remain authoritative.

= Why does the page check say "could not be checked"? =

The product page could not be fetched (timeout, redirect, non-200 response) or contained no readable price. FeedGuard counts those separately and never treats them as failures.

= The GTIN check says a product has no GTIN, but I entered one. =

FeedGuard reads WooCommerce core's GTIN field (`_global_unique_id`) and the fields of common GTIN/feed plugins, plus `gtin`/`ean` attributes. If your plugin stores it elsewhere, the `feedguard_woo_gtin_meta_keys` filter lets you add the meta key.

= Does FeedGuard send my data anywhere? =

No. The only requests are to your own store's product pages. There is no telemetry and no third-party service.

== Screenshots ==

1. The FeedGuard report: findings grouped by fix, with severity and per-product rows.
2. Store-level findings and the summary header with the CSV download.

== Changelog ==

= 0.1.0 — 2026-11-02 =
* Initial release: identifier, image, title, description, SKU, price and store checks.
* Page-vs-data price and availability comparison via product structured data.
* Grouped wp-admin report and CSV export.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
