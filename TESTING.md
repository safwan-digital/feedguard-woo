# FeedGuard for WooCommerce — Testing Guide (for Safwan)

Goal: install the v0.1 zip on a throwaway WordPress + WooCommerce site, break products in specific ways, and confirm FeedGuard catches each one with the right message. ~30–45 minutes.

## 1. Install

**You need:** any WordPress site (staging, LocalWP, or a subdomain — *not* a live client store for the destructive scenarios), WooCommerce installed and active, admin access.

1. Take `feedguard-woo.zip` from this repo root.
2. WP Admin → **Plugins → Add New → Upload Plugin** → choose the zip → **Install Now** → **Activate**.
3. Confirm a **FeedGuard** item appears under the **WooCommerce** menu.
4. If the site has no products, create 3 simple products first (name, regular price, SKU, image, description) so you have a clean baseline. WooCommerce → Settings: currency set, at least one shipping zone with a flat-rate method (we will remove it later, in Scenario 8).

**Baseline run:** WooCommerce → FeedGuard → tick "Also fetch each product page" → **Run checks**. On a clean mini-store you should see the summary header and either "No product issues found" or only warnings you can explain (e.g. missing GTINs if you skipped them). Note the products-checked and pages-compared counts.

> One thing to update before any future WordPress.org submission: `readme.txt` lists the contributor as `safwan-digital` (your GitHub handle). Change it to your WordPress.org username when you create one.

## 2. Scenarios

Run the checks again after each change (results replace the previous run).

### Scenario 1 — Missing identifiers (error)
- Edit a product: clear the **GTIN/UPC/EAN/ISBN** field (Product data → Inventory), remove any brand (Brands panel / brand attribute) and any MPN.
- **Expect:** group **"No product identifiers (GTIN, brand, MPN)"**, severity **error**, this product listed, fix text starting "Add the GTIN (barcode) in the product's Inventory tab…".

### Scenario 2 — Identifier precedence (warning, not error)
- Same product: add **only a brand** (assign a brand, or add a product attribute named `brand`).
- **Expect:** the error from Scenario 1 is gone; instead a **"Missing GTIN"** warning appears for the product. This confirms brand-only products are warnings, not errors.

### Scenario 3 — GTIN from the core field clears the flag
- Same product: set the **GTIN, UPC, EAN, or ISBN** field (Inventory tab) to any value, e.g. `8901234567890`.
- **Expect:** no identifier findings for this product at all.
- *Please note in feedback:* if your test product's GTIN lives in a different field/plugin, tell me which — FeedGuard's field list may need extending.

### Scenario 4 — Sale price not lower (error)
- Edit a product: Regular price `1000`, Sale price `1200`.
- **Expect:** **"Sale price is not lower than regular price"**, error, detail showing both values.

### Scenario 5 — Duplicate SKU (error, both products)
- Give two different products the **same SKU**, e.g. `TEST-DUP`.
- **Expect:** **"Duplicate SKU"** group with **both** products listed, each naming the shared SKU.

### Scenario 6 — Missing image (error)
- Remove the product image from a product (leave gallery empty too).
- **Expect:** **"Missing product image"**, error.

### Scenario 7 — Stuffed title (warnings)
- Rename a product to a >150-character ALL-CAPS title repeating a word, e.g. repeat "BEST QUALITY PREMIUM SHOES SHOES SHOES …" until it is clearly over 150 characters.
- **Expect:** up to two warnings for this product: **"Title longer than 150 characters"** (with the real character count) and **"Title looks keyword-stuffed"** (reason stated: capitals, repetition, or promo phrases).

### Scenario 8 — No shipping methods (store-level error)
- WooCommerce → Settings → Shipping: delete/disable every shipping method in every zone (staging only!).
- **Expect:** a **"Store settings"** section above the product groups with **"No shipping methods configured"**, error, and the fix mentioning Merchant Center as the alternative place to define shipping.
- Restore a flat-rate method afterwards.

### Scenario 9 — Page-vs-data price mismatch (error)
This simulates a front-end price that disagrees with the product data (the #1 disapproval pattern):
1. Install/activate the **Code Snippets** plugin (or use your theme's `functions.php` on staging) and add this snippet, replacing `123` with one of your test product IDs:

```php
add_action( 'wp_head', function () {
    if ( is_product() && get_the_ID() === 123 ) {
        echo '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":"FeedGuard test","offers":{"@type":"Offer","price":"1.00","priceCurrency":"AED","availability":"https://schema.org/InStock"}}</script>';
    }
}, 99 );
```

2. Make sure that product's real price is **not** 1.00 and it is in stock. Run checks **with page checks on**.
3. **Expect:** **"Page price differs from product data price"**, error, detail showing "page shows 1" vs the data price.
4. **Delete the snippet** and re-run: the mismatch must disappear.
- *Note:* if your theme/SEO plugin also outputs Product JSON-LD, your snippet's block may not be the first one FeedGuard reads — if the scenario doesn't trigger, tell me which SEO plugin the test site uses.

### Scenario 10 — A "could not check" page is not an error
- Set one product's permalink target to break (e.g. temporarily password-protect the product, or test while the site is in maintenance mode), run with page checks on.
- **Expect:** summary line **"Pages that could not be checked: 1"** (or more) and **no** price/availability error for that product. Unreachable pages are never failures.

### Scenario 11 — Clean product passes clean
- One product with: GTIN + brand set, image, unique SKU, sane title under 150 chars, description, regular price, in stock, published and visible.
- **Expect:** that product appears in **no** group.

## 3. Verify the CSV

1. After any run with findings, click **Download CSV report**.
2. Open it in Excel/Sheets. **Expect** the header row exactly:
   `product_id, sku, product_name, check, severity, detail, fix, edit_url`
3. Row count = errors + warnings from the on-screen summary (store rows included, with `product_id` 0 and product name `(Store setting)`).
4. Click an `edit_url` — it should open that product's editor.

## 4. Send back as feedback

For each scenario: **Pass / Fail** + what you actually saw if it differs. Plus:
- WordPress, WooCommerce and PHP versions of the test site (Site Health → Info).
- Theme and any SEO/feed/GTIN plugins active (matters for Scenarios 3 and 9).
- Any **false positive** — a flag on a product you believe is genuinely fine — with the product's setup. False positives are the most valuable feedback for v0.2.
- Rough run time on your store size (products checked vs. seconds, page checks on).
