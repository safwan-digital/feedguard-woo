# FeedGuard for WooCommerce — Product Design (v0.1)

**One line:** FeedGuard checks your WooCommerce product data for the problems that get products disapproved in Google Merchant Center — *before* you submit the feed, inside wp-admin, in plain language.

**Honest disclaimer (product law, appears in the plugin UI and both READMEs):** FeedGuard is an early-warning tool. Merchant Center's own diagnostics remain the only authoritative verdict. A clean FeedGuard report does **not** guarantee approval, and FeedGuard never promises it. FeedGuard promises to catch the common, fixable data problems early and explain each fix in WooCommerce terms.

---

## 1. Problem

Store owners learn their product data is broken only *after* Merchant Center disapproves products: listings silently stop, Performance Max starves, and Google's error messages ("Mismatched value (page crawl) [price]") don't say which WooCommerce field to change. Existing validators (NextFeed, FeedPreflight, launched 2026) check an exported feed *file*. Nobody free and open-source checks the data **at the source**, inside WooCommerce — including the single most damaging pattern, the feed price disagreeing with the price the live product page actually renders.

## 2. Target users

1. **WooCommerce store owners** (primary) selling via Google Shopping / free listings / Performance Max — especially COD-heavy stores in India and the Gulf running their own stores without an agency.
2. **Freelancers and small agencies** managing several client stores: the CSV export is the per-client deliverable ("here are the 14 things to fix before we touch the feed").
3. **Feed-plugin users** (Google Listings & Ads, CTX Feed, etc.): FeedGuard complements the feed plugin; it never generates or syncs a feed itself.

## 3. Principles

- **Fix at the source.** Every finding names the WooCommerce field/setting to change. Never "edit the feed to match" — the store data is the truth.
- **Plain language.** No Merchant Center jargon without an explanation. Every group of findings carries a one-line "why Google cares".
- **Never cry wolf.** A page that could not be fetched is reported as *not checked*, never as an error.
- **Private by design.** No external network calls except the store's own product pages. No telemetry, no accounts, no upsells, no nags — forever (this is the differentiator against commercial rivals).
- **Free means free.** All checks are in the open-source plugin. There is no pro tier.

## 4. Rule catalogue (v0.1 — complete)

Severity model: **error** = will very likely cause a disapproval or a missing item; **warning** = limits performance or becomes an error in common feed setups. Grouping and ordering in the report follow severity, then affected-product count.

### 4.1 Identifier checks

| Check | Severity | Exact logic | Why Google cares | Fix message shown (abridged) |
|---|---|---|---|---|
| `missing_identifiers` | error | GTIN **and** brand **and** MPN all empty (see §4.1.1 for sources) | Products without identifiers are limited/disapproved unless the feed sets `identifier_exists = no` | "Add the GTIN (barcode) in the product's Inventory tab. If the product has no GTIN, add its brand and MPN instead — or, if it truly has no identifiers, set identifier_exists to "No" for it in your feed plugin." |
| `missing_gtin` | warning | Brand or MPN present, GTIN empty (only fired when `missing_identifiers` did not) | GTIN is the strongest catalogue match key; required for most manufactured goods | "If the product has a barcode, add it as the GTIN in the Inventory tab…" |
| `missing_brand` | warning | GTIN/MPN present, brand empty (same precedence) | Google expects brand alongside GTIN/MPN | "Assign the product's brand (Products → Brands, or a "brand" attribute)…" |

**4.1.1 Where identifiers are read from** (first non-empty wins; all filterable for developers):
- **GTIN:** meta `_global_unique_id` (WooCommerce core's "GTIN, UPC, EAN, or ISBN" field), `_gtin`, `_ean`, `_upc`, `_isbn`, `_product_gtin`, `_wps_gtin`, `gtin`; attributes `gtin`/`ean`/`upc`/`isbn` (plain or `pa_` prefixed).
- **Brand:** `product_brand` taxonomy terms (WooCommerce native brands and brand plugins), attributes `brand`/`pa_brand`, meta `_brand`, `brand`.
- **MPN:** meta `_mpn`, `mpn`; attributes `mpn`/`pa_mpn`.

### 4.2 Image checks

| Check | Severity | Logic | Fix message (abridged) |
|---|---|---|---|
| `missing_image` | error | `get_image_id()` is empty | "Set a product image. Google requires image_link for every item." |
| `image_not_https` | warning | Main image URL does not start with `https://` (actual URL shown in the detail) | "Serve your site and media over HTTPS…" |

### 4.3 Title checks

| Check | Severity | Logic |
|---|---|---|
| `title_too_long` | warning | Title length (`mb_strlen`) > **150** characters; detail shows the actual length. Fix: "brand + product + one or two searchable attributes; move the rest to the description." |
| `title_stuffing` | warning | Heuristic, any one of: (a) >60% of letters uppercase with ≥20 letters; (b) any word ≥3 letters repeated ≥3 times (word + count shown); (c) ≥2 promotional phrases from a built-in list (`free shipping`, `best seller`, `on sale`, `discount`, `cheap`, `buy now`, `limited offer`, `lowest price`, `100% original`, `cash on delivery`, …). Fix: "Rewrite the title in normal sentence case without repeated keywords or promotional phrases." |

The heuristic is deliberately conservative — it would rather miss mild stuffing than flag honest titles. It is documented as a heuristic in the report's "why" text, never presented as Google's own verdict.

### 4.4 Content check

| Check | Severity | Logic |
|---|---|---|
| `missing_description` | warning | Both description and short description are empty after stripping tags. Fix: "Write a real description… Google uses it for matching." |

### 4.5 SKU / ID checks

| Check | Severity | Logic |
|---|---|---|
| `missing_sku` | warning | SKU empty. Rationale shown: most feed plugins use the SKU as the stable Merchant Center item ID. |
| `duplicate_sku` | error | Same SKU (case-insensitive) on 2+ checked products; **every** product sharing it is flagged. Built from a full-catalogue SKU census taken before per-product checks run. |

### 4.6 Price checks (skipped for grouped products, whose price comes from children)

| Check | Severity | Logic |
|---|---|---|
| `missing_price` | error | `get_price()` returns `''` |
| `zero_price` | warning | Price is exactly `0` |
| `sale_price_invalid` | error | Sale price set, regular price > 0, and sale price ≥ regular price; both values shown |

### 4.7 Publication checks

| Check | Severity | Logic |
|---|---|---|
| `not_published` | error | Status ≠ `publish` (only reachable when unpublished products are included via the developer filter/args; the default run covers published products) |
| `not_visible` | warning | Catalogue visibility is `hidden` or `search` |

### 4.8 Store-level checks (reported once, above product groups)

| Check | Severity | Logic | Note in fix message |
|---|---|---|---|
| `store_no_currency` | error | `get_woocommerce_currency()` empty | Set currency in WooCommerce → Settings → General |
| `store_no_country` | warning | `woocommerce_default_country` option empty | Set Base location |
| `store_no_shipping` | error | No shipping zone (including the "rest of the world" zone) has any method | "…or define shipping in Merchant Center instead — Google needs it in one of the two places." |
| `store_tax_disabled` | warning | `wc_tax_enabled()` is false | Explicitly framed as *not always a problem*: fine if tax is configured in Merchant Center; a problem if in neither place |

### 4.9 Page-vs-data checks (see §6 for design)

| Check | Severity | Logic |
|---|---|---|
| `page_price_mismatch` | error | Page price (JSON-LD `offers.price` / `lowPrice`, fallback `product:price:amount` meta) matches **neither** the raw data price **nor** the tax-display price (tolerance 0.01). Detail shows both values + currency. Fix directs the user to price fields, tax-display settings, and price-changing plugins — never to editing the feed. |
| `page_availability_mismatch` | warning | JSON-LD `offers.availability` (InStock vs OutOfStock/SoldOut) disagrees with `is_in_stock()`. |

## 5. Architecture

```
feedguard-woo/
├── feedguard-woo.php                  Bootstrap: constants, HPOS declaration,
│                                      WooCommerce presence check, activation.
├── includes/
│   ├── class-feedguard-checks.php     The rule engine. Pure checks over
│   │                                  WC_Product + store options; page check
│   │                                  via WP HTTP API. No output, no globals
│   │                                  beyond WP/WC APIs — unit-testable shape.
│   └── class-feedguard-admin.php      Menu page, run form, grouped report,
│                                      CSV export (admin-post handler).
├── assets/admin.css                   Small report stylesheet, loaded only
│                                      on the FeedGuard page.
└── readme.txt                         WordPress.org readme.
```

- **Data flow:** Run form (nonce) → `FeedGuard_Woo_Checks::run_all()` → results stored in a **per-user transient (1 hour)** → report renders from the transient → CSV export downloads *the same* results (if the transient expired, export performs a data-only run so the button never dead-ends).
- **Catalogue handling:** products are paged 100 at a time (SKU census first, checks second) so memory stays flat on large stores. Worst case is still O(products) HTTP-free work plus the capped page checks.
- **Capabilities & security:** everything requires `manage_woocommerce`; both forms use nonces; all output escaped (`esc_html`/`esc_url`/`esc_attr`); CSV goes through `fputcsv`.
- **HPOS:** compatibility declared; the plugin never touches orders, so HPOS is a non-issue by design.
- **Extensibility (already in v0.1):** filters `feedguard_woo_gtin_meta_keys`, `feedguard_woo_brand_meta_keys`, `feedguard_woo_mpn_meta_keys`; `run_all()` args (`page_checks`, `page_check_limit`, `include_unpublished`).

## 6. Page-vs-data comparison — design and limits

**Design.** For published, catalogue-visible products (in catalogue order, up to the run's page-check limit; default 25, 0 = all):

1. `wp_remote_get(permalink)` — timeout 10s, max 3 redirects, honest user-agent identifying FeedGuard and the store's own home URL.
2. Non-200 responses, WP_Error and empty bodies → the product is counted as **"could not be checked"** with the reason kept internally. Never an issue row.
3. Price extraction order: JSON-LD Product `offers.price` → AggregateOffer `lowPrice` → `product:price:amount` meta tag. Availability from JSON-LD when present.
4. Comparison accepts a match against raw price **or** tax-display price (`wc_get_price_to_display`), because which one the feed carries depends on store settings — flagging the legitimate one would be a false positive.

**Limits (stated in the README, not hidden):**
- Prices rendered only by JavaScript, or personalised by geolocation/currency switchers/logged-in role plugins, can differ from what the fetch sees. A mismatch is a *prompt to investigate*, with both values shown.
- The fetch is unauthenticated; login-only or members-only stores will mostly land in "could not be checked".
- Variable products: the JSON-LD price is usually the "from" price; FeedGuard compares against the parent price the same way the feed does, and grouped products are skipped.
- Caching/CDN can serve a stale page right after a price change; re-running after the cache clears is the documented remedy.
- Page checks are capped for a reason: each is a real HTTP request. On a 1,000-product store, "all" is an overnight decision, not a default.

## 7. Admin UX

**Location:** WooCommerce → FeedGuard. One page, no settings maze, no dashboard widgets, no notices outside the page.

```
FeedGuard — Merchant Center pre-flight checks
[Disclaimer banner: early warning only; Merchant Center diagnostics are authoritative]
[Run form: ☑ compare live pages | Max products to page-check: 25 | Run checks]

Results — run at 2026-11-02 23:41
Products checked: 128 · Errors: 9 · Warnings: 21 · Pages compared: 25 · (Pages that could not be checked: 2)
[Download CSV report]

Store settings
  [table: setting issue rows]

Missing GTIN (14 products)
  Why Google cares: …
  [table: Product (edit link, #ID) | SKU | Severity | What was found | How to fix it]
Page price differs from product data price (3 products)
  …
No product issues found.  ← when clean, said plainly, with the disclaimer repeated
```

- **Grouping:** by check (i.e., by *fix*), errors first, then by affected count. An owner fixes one kind of problem across the catalogue in one pass.
- **Per-product row:** name (links to the product editor), ID, SKU, severity badge, exact detail (values included), exact fix.
- **Severity:** text badge (error/warning), colour as reinforcement only.

## 8. CSV export format

One row per issue (store issues included), header row first, UTF-8:

```
product_id, sku, product_name, check, severity, detail, fix, edit_url
```

- Store-level rows: `product_id` = 0, `sku` empty, `product_name` = `(Store setting)`, `edit_url` empty.
- File name: `feedguard-report-YYYY-MM-DD-HHMMSS.csv`.
- Purpose: agency deliverable and offline triage. It is a snapshot of the run shown on screen — same transient, same numbers.

## 9. Roadmap

**v0.2** — make it a habit, not a one-off:
- Scheduled weekly check (WP-Cron) with an email summary to the store admin when errors appear.
- Settings: default page-check limit, exclude specific products/categories.
- Variation-level identifier checks (GTIN per variation).
- "Last run" timestamp + result history summary on the page.
- WordPress.org submission (readme already in format).

**v0.3** — widen carefully:
- Python CLI twin (`feedguard check feed.xml`) implementing the *file-side* subset of the same rule catalogue for stores that already export feeds — one rule spec, two runners.
- Optional Meta Catalog rules as a clearly separate module (Google stays the default and the core).
- Per-category rules contributed by the community (e.g., apparel size/colour attributes) via the documented check-definition filter pattern.

## 10. Risks

| Risk | Mitigation |
|---|---|
| False positives erode trust (esp. page price) | Accept raw **or** tax-display price; show both values; "could not check" is never an error; limits documented in README |
| Sites with unusual GTIN storage are missed | Broad built-in field list + public filters; TESTING.md asks testers to report their GTIN plugin |
| Large catalogues make runs slow | Paged queries; page checks capped (default 25); data checks are DB-only |
| Google changes its rules | Rule catalogue is data, not scattered conditionals; CHANGELOG documents rule changes per version; the disclaimer prevents over-promising in the meantime |
| "Another feed plugin" positioning confusion | README and UI state FeedGuard generates/syncs nothing; it sits *before* the feed plugin |

## 11. Out of scope for v0.1 (explicit)

Generating or syncing feeds · Meta/TikTok catalog rules · fixing data automatically (FeedGuard reports; humans fix) · scheduled checks (v0.2) · the Python CLI (v0.3, optional in the original spec) · anything that phones home.
