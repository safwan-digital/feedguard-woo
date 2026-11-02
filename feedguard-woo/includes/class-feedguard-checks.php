<?php
/**
 * FeedGuard rule engine.
 *
 * All checks operate on data already inside WordPress/WooCommerce. The only
 * network access in this class is the page check, which fetches the store's
 * own product pages through the WordPress HTTP API. Nothing is ever sent to
 * a third party, and there is no telemetry.
 *
 * Every issue is returned as an array:
 *   product_id   int     WooCommerce product ID (0 for store-level issues)
 *   sku          string  Product SKU ('' for store-level issues)
 *   product_name string  Product name ('(Store setting)' for store issues)
 *   check        string  Check slug, see get_check_definitions()
 *   severity     string  'error' | 'warning'
 *   detail       string  What was found, in plain language
 *   fix          string  What to change, in plain language
 *   edit_url     string  wp-admin edit link ('' for store-level issues)
 *
 * @package FeedGuard_Woo
 */

defined( 'ABSPATH' ) || exit;

class FeedGuard_Woo_Checks {

	const SEVERITY_ERROR   = 'error';
	const SEVERITY_WARNING = 'warning';

	/**
	 * Meta keys commonly used to store a GTIN, in priority order.
	 * Includes WooCommerce core's own field (`_global_unique_id`, WC 9.2+)
	 * and the fields written by popular feed/GTIN plugins. Filterable.
	 *
	 * @return string[]
	 */
	public static function gtin_meta_keys() {
		$keys = array(
			'_global_unique_id', // WooCommerce core: "GTIN, UPC, EAN, or ISBN".
			'_gtin',
			'_ean',
			'_upc',
			'_isbn',
			'_product_gtin',
			'_wps_gtin',
			'gtin',
		);
		/**
		 * Filter the product meta keys FeedGuard reads a GTIN from.
		 *
		 * @param string[] $keys Meta keys.
		 */
		return apply_filters( 'feedguard_woo_gtin_meta_keys', $keys );
	}

	/**
	 * Meta keys commonly used to store a brand name. Filterable.
	 *
	 * @return string[]
	 */
	public static function brand_meta_keys() {
		$keys = array( '_brand', 'brand' );
		/**
		 * Filter the product meta keys FeedGuard reads a brand from.
		 *
		 * @param string[] $keys Meta keys.
		 */
		return apply_filters( 'feedguard_woo_brand_meta_keys', $keys );
	}

	/**
	 * Meta keys commonly used to store an MPN. Filterable.
	 *
	 * @return string[]
	 */
	public static function mpn_meta_keys() {
		$keys = array( '_mpn', 'mpn' );
		/**
		 * Filter the product meta keys FeedGuard reads an MPN from.
		 *
		 * @param string[] $keys Meta keys.
		 */
		return apply_filters( 'feedguard_woo_mpn_meta_keys', $keys );
	}

	/**
	 * Human-readable catalogue of every check.
	 *
	 * @return array<string,array{label:string,why:string}>
	 */
	public static function get_check_definitions() {
		return array(
			'missing_identifiers'    => array(
				'label' => __( 'No product identifiers (GTIN, brand, MPN)', 'feedguard-woo' ),
				'why'   => __( 'Google uses GTIN/brand/MPN to match your product to its catalogue. Products with none of them are limited or disapproved unless the feed explicitly sets identifier_exists to "no".', 'feedguard-woo' ),
			),
			'missing_gtin'           => array(
				'label' => __( 'Missing GTIN', 'feedguard-woo' ),
				'why'   => __( 'For products that have a GTIN (most manufactured goods), Google requires it. Missing GTINs are a top cause of limited performance and disapprovals.', 'feedguard-woo' ),
			),
			'missing_brand'          => array(
				'label' => __( 'Missing brand', 'feedguard-woo' ),
				'why'   => __( 'Brand is a required identifier companion: Google expects brand together with a GTIN or MPN.', 'feedguard-woo' ),
			),
			'missing_image'          => array(
				'label' => __( 'Missing product image', 'feedguard-woo' ),
				'why'   => __( 'image_link is required. A product without a main image cannot be listed.', 'feedguard-woo' ),
			),
			'image_not_https'        => array(
				'label' => __( 'Product image is not HTTPS', 'feedguard-woo' ),
				'why'   => __( 'Google crawls image_link over HTTPS. Non-HTTPS image URLs are rejected or fail to load in listings.', 'feedguard-woo' ),
			),
			'title_too_long'         => array(
				'label' => __( 'Title longer than 150 characters', 'feedguard-woo' ),
				'why'   => __( 'Google truncates and may flag titles over 150 characters; the useful keywords get cut off.', 'feedguard-woo' ),
			),
			'title_stuffing'         => array(
				'label' => __( 'Title looks keyword-stuffed', 'feedguard-woo' ),
				'why'   => __( 'ALL-CAPS blocks, repeated words and promotional phrases ("sale", "free shipping") breach Google\'s title guidelines and can get the item disapproved.', 'feedguard-woo' ),
			),
			'missing_description'    => array(
				'label' => __( 'Missing description', 'feedguard-woo' ),
				'why'   => __( 'Google uses the description for matching and quality ranking. Empty descriptions reduce visibility.', 'feedguard-woo' ),
			),
			'missing_sku'            => array(
				'label' => __( 'Missing SKU', 'feedguard-woo' ),
				'why'   => __( 'The SKU becomes the feed item ID in most feed plugins. A missing SKU means an unstable or duplicated ID, which breaks item history in Merchant Center.', 'feedguard-woo' ),
			),
			'duplicate_sku'          => array(
				'label' => __( 'Duplicate SKU', 'feedguard-woo' ),
				'why'   => __( 'Two products sharing an item ID collide in the feed: one overwrites the other in Merchant Center.', 'feedguard-woo' ),
			),
			'missing_price'          => array(
				'label' => __( 'Missing price', 'feedguard-woo' ),
				'why'   => __( 'price is a required attribute. A product with no price cannot be approved.', 'feedguard-woo' ),
			),
			'zero_price'             => array(
				'label' => __( 'Price is zero', 'feedguard-woo' ),
				'why'   => __( 'Google disapproves items priced at 0 unless they are genuinely free, which is rare for shippable goods.', 'feedguard-woo' ),
			),
			'sale_price_invalid'     => array(
				'label' => __( 'Sale price is not lower than regular price', 'feedguard-woo' ),
				'why'   => __( 'A sale_price that is equal to or higher than the regular price is invalid and gets the sale annotation (and often the item) rejected.', 'feedguard-woo' ),
			),
			'not_published'          => array(
				'label' => __( 'Product is not published', 'feedguard-woo' ),
				'why'   => __( 'Draft/private/pending products must not reach the feed; if a feed plugin includes them, Google lands on a page it cannot buy from.', 'feedguard-woo' ),
			),
			'not_visible'            => array(
				'label' => __( 'Product hidden from catalogue', 'feedguard-woo' ),
				'why'   => __( 'A published product whose catalogue visibility is "hidden" or "search results only" usually should not be in the shopping feed.', 'feedguard-woo' ),
			),
			'page_price_mismatch'    => array(
				'label' => __( 'Page price differs from product data price', 'feedguard-woo' ),
				'why'   => __( 'Price mismatch between the feed and the landing page is the most common Merchant Center disapproval. Google compares the two and trusts the page.', 'feedguard-woo' ),
			),
			'page_availability_mismatch' => array(
				'label' => __( 'Page availability differs from stock status', 'feedguard-woo' ),
				'why'   => __( 'If the page structured data says out of stock while the feed says in stock (or the reverse), Google flags an availability mismatch.', 'feedguard-woo' ),
			),
			'store_no_currency'      => array(
				'label' => __( 'Store currency is not set', 'feedguard-woo' ),
				'why'   => __( 'Merchant Center requires every price to carry a currency. WooCommerce must have one configured.', 'feedguard-woo' ),
			),
			'store_no_country'       => array(
				'label' => __( 'Store base country is not set', 'feedguard-woo' ),
				'why'   => __( 'The base location drives tax/shipping defaults the feed relies on.', 'feedguard-woo' ),
			),
			'store_no_shipping'      => array(
				'label' => __( 'No shipping methods configured', 'feedguard-woo' ),
				'why'   => __( 'Google requires working shipping information, either in WooCommerce or in Merchant Center. With neither, products are disapproved for missing shipping.', 'feedguard-woo' ),
			),
			'store_tax_disabled'     => array(
				'label' => __( 'Taxes are not enabled in WooCommerce', 'feedguard-woo' ),
				'why'   => __( 'Not always a problem — tax can be configured in Merchant Center instead — but if it is set in neither place, prices mismatch and items are disapproved (notably for US feeds).', 'feedguard-woo' ),
			),
		);
	}

	/**
	 * Build one issue row.
	 *
	 * @param WC_Product|null $product Product, or null for store-level issues.
	 * @param string          $check   Check slug.
	 * @param string          $severity 'error'|'warning'.
	 * @param string          $detail  What was found.
	 * @param string          $fix     What to change.
	 * @return array
	 */
	private static function issue( $product, $check, $severity, $detail, $fix ) {
		if ( $product instanceof WC_Product ) {
			return array(
				'product_id'   => $product->get_id(),
				'sku'          => (string) $product->get_sku(),
				'product_name' => $product->get_name(),
				'check'        => $check,
				'severity'     => $severity,
				'detail'       => $detail,
				'fix'          => $fix,
				'edit_url'     => (string) get_edit_post_link( $product->get_id(), 'raw' ),
			);
		}
		return array(
			'product_id'   => 0,
			'sku'          => '',
			'product_name' => __( '(Store setting)', 'feedguard-woo' ),
			'check'        => $check,
			'severity'     => $severity,
			'detail'       => $detail,
			'fix'          => $fix,
			'edit_url'     => '',
		);
	}

	/**
	 * First non-empty value among the given meta keys / product attributes.
	 *
	 * @param WC_Product $product  Product.
	 * @param string[]   $meta_keys Meta keys to try.
	 * @param string[]   $attributes Attribute names to try (with and without pa_ prefix).
	 * @return string Trimmed value, '' when nothing is set.
	 */
	private static function first_identifier_value( $product, $meta_keys, $attributes ) {
		foreach ( $meta_keys as $key ) {
			$value = trim( (string) $product->get_meta( $key ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
		foreach ( $attributes as $attribute ) {
			$value = trim( (string) $product->get_attribute( $attribute ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * The product's GTIN, from core/plugin meta or an attribute.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function get_gtin( $product ) {
		return self::first_identifier_value( $product, self::gtin_meta_keys(), array( 'gtin', 'pa_gtin', 'ean', 'pa_ean', 'upc', 'isbn' ) );
	}

	/**
	 * The product's brand: native brands taxonomy, attribute, or meta.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function get_brand( $product ) {
		// WooCommerce native brands (WC 9.4+) and brand plugins use this taxonomy.
		if ( taxonomy_exists( 'product_brand' ) ) {
			$terms = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				return trim( (string) $terms[0] );
			}
		}
		return self::first_identifier_value( $product, self::brand_meta_keys(), array( 'brand', 'pa_brand' ) );
	}

	/**
	 * The product's MPN.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function get_mpn( $product ) {
		return self::first_identifier_value( $product, self::mpn_meta_keys(), array( 'mpn', 'pa_mpn' ) );
	}

	/**
	 * Keyword-stuffing heuristic for titles.
	 *
	 * Flags a title when any of these hold:
	 *  - more than 60% of its letters are uppercase (and it has 20+ letters), or
	 *  - the same word (3+ letters) appears 3 or more times, or
	 *  - it contains 2 or more promotional phrases Google discourages.
	 *
	 * @param string $title Product title.
	 * @return string Reason the title was flagged, '' when clean.
	 */
	public static function title_stuffing_reason( $title ) {
		$letters = preg_replace( '/[^a-zA-Z]/', '', $title );
		if ( strlen( $letters ) >= 20 ) {
			$upper = preg_replace( '/[^A-Z]/', '', $letters );
			if ( strlen( $upper ) / strlen( $letters ) > 0.6 ) {
				return __( 'Most of the title is in capital letters.', 'feedguard-woo' );
			}
		}
		$words = preg_split( '/[^a-z0-9]+/i', strtolower( $title ), -1, PREG_SPLIT_NO_EMPTY );
		if ( is_array( $words ) ) {
			$counts = array_count_values( array_filter( $words, static function ( $w ) {
				return strlen( $w ) >= 3;
			} ) );
			foreach ( $counts as $word => $count ) {
				if ( $count >= 3 ) {
					return sprintf(
						/* translators: %s: repeated word */
						__( 'The word "%s" is repeated %d times.', 'feedguard-woo' ),
						$word,
						$count
					);
				}
			}
		}
		$promo_phrases = array( 'free shipping', 'best seller', 'best-selling', 'on sale', 'sale!', 'discount', 'cheap', 'buy now', 'limited offer', 'lowest price', '100% original', 'cash on delivery' );
		$hits          = 0;
		$lower         = strtolower( $title );
		foreach ( $promo_phrases as $phrase ) {
			if ( false !== strpos( $lower, $phrase ) ) {
				$hits++;
			}
		}
		if ( $hits >= 2 ) {
			return __( 'The title contains multiple promotional phrases.', 'feedguard-woo' );
		}
		return '';
	}

	/**
	 * Run all data checks for one product.
	 *
	 * @param WC_Product $product        Product.
	 * @param string[]   $duplicate_skus SKUs (lowercased) used by more than one product.
	 * @return array[] Issues.
	 */
	public static function run_product_checks( $product, $duplicate_skus = array() ) {
		$issues = array();

		// --- Publication / visibility -------------------------------------
		if ( 'publish' !== $product->get_status() ) {
			$issues[] = self::issue(
				$product,
				'not_published',
				self::SEVERITY_ERROR,
				sprintf(
					/* translators: %s: product status */
					__( 'This product\'s status is "%s", not "publish".', 'feedguard-woo' ),
					$product->get_status()
				),
				__( 'Publish the product, or make sure your feed plugin excludes non-published products.', 'feedguard-woo' )
			);
		}
		$visibility = $product->get_catalog_visibility();
		if ( 'hidden' === $visibility || 'search' === $visibility ) {
			$issues[] = self::issue(
				$product,
				'not_visible',
				self::SEVERITY_WARNING,
				sprintf(
					/* translators: %s: catalogue visibility */
					__( 'Catalogue visibility is "%s", so shoppers cannot browse to this product.', 'feedguard-woo' ),
					$visibility
				),
				__( 'Set catalogue visibility to "Shop and search results" in the product\'s Publish panel, or exclude this product from your feed.', 'feedguard-woo' )
			);
		}

		// --- Identifiers ----------------------------------------------------
		$gtin  = self::get_gtin( $product );
		$brand = self::get_brand( $product );
		$mpn   = self::get_mpn( $product );
		if ( '' === $gtin && '' === $brand && '' === $mpn ) {
			$issues[] = self::issue(
				$product,
				'missing_identifiers',
				self::SEVERITY_ERROR,
				__( 'No GTIN, brand or MPN was found on this product.', 'feedguard-woo' ),
				__( 'Add the GTIN (barcode) in the product\'s Inventory tab. If the product has no GTIN, add its brand and MPN instead — or, if it truly has no identifiers, set identifier_exists to "No" for it in your feed plugin.', 'feedguard-woo' )
			);
		} else {
			if ( '' === $gtin ) {
				$issues[] = self::issue(
					$product,
					'missing_gtin',
					self::SEVERITY_WARNING,
					__( 'This product has a brand/MPN but no GTIN.', 'feedguard-woo' ),
					__( 'If the product has a barcode, add it as the GTIN in the Inventory tab — products with a GTIN match Google\'s catalogue far better.', 'feedguard-woo' )
				);
			}
			if ( '' === $brand ) {
				$issues[] = self::issue(
					$product,
					'missing_brand',
					self::SEVERITY_WARNING,
					__( 'No brand was found (brands taxonomy, "brand" attribute and brand fields are all empty).', 'feedguard-woo' ),
					__( 'Assign the product\'s brand (Products → Brands, or a "brand" attribute). Google expects a brand next to every GTIN/MPN.', 'feedguard-woo' )
				);
			}
		}

		// --- Image -----------------------------------------------------------
		$image_id = $product->get_image_id();
		if ( ! $image_id ) {
			$issues[] = self::issue(
				$product,
				'missing_image',
				self::SEVERITY_ERROR,
				__( 'This product has no main image.', 'feedguard-woo' ),
				__( 'Set a product image. Google requires image_link for every item.', 'feedguard-woo' )
			);
		} else {
			$image_url = (string) wp_get_attachment_image_url( $image_id, 'full' );
			if ( '' !== $image_url && 0 !== strpos( $image_url, 'https://' ) ) {
				$issues[] = self::issue(
					$product,
					'image_not_https',
					self::SEVERITY_WARNING,
					sprintf(
						/* translators: %s: image URL */
						__( 'The main image URL is not HTTPS: %s', 'feedguard-woo' ),
						$image_url
					),
					__( 'Serve your site and media over HTTPS (Settings → General should use https:// URLs, with a valid SSL certificate).', 'feedguard-woo' )
				);
			}
		}

		// --- Title ------------------------------------------------------------
		$title  = $product->get_name();
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
		if ( $length > 150 ) {
			$issues[] = self::issue(
				$product,
				'title_too_long',
				self::SEVERITY_WARNING,
				sprintf(
					/* translators: %d: title length */
					__( 'The title is %d characters long (Google\'s limit is 150).', 'feedguard-woo' ),
					$length
				),
				__( 'Shorten the title: brand + product + the one or two attributes shoppers search for. Move the rest to the description.', 'feedguard-woo' )
			);
		}
		$stuffing = self::title_stuffing_reason( $title );
		if ( '' !== $stuffing ) {
			$issues[] = self::issue(
				$product,
				'title_stuffing',
				self::SEVERITY_WARNING,
				$stuffing,
				__( 'Rewrite the title in normal sentence case without repeated keywords or promotional phrases.', 'feedguard-woo' )
			);
		}

		// --- Description -------------------------------------------------------
		if ( '' === trim( wp_strip_all_tags( (string) $product->get_description() ) )
			&& '' === trim( wp_strip_all_tags( (string) $product->get_short_description() ) ) ) {
			$issues[] = self::issue(
				$product,
				'missing_description',
				self::SEVERITY_WARNING,
				__( 'Both the description and the short description are empty.', 'feedguard-woo' ),
				__( 'Write a real description (a few sentences about what it is, who it is for, and key specs). Google uses it for matching.', 'feedguard-woo' )
			);
		}

		// --- SKU ----------------------------------------------------------------
		$sku = (string) $product->get_sku();
		if ( '' === trim( $sku ) ) {
			$issues[] = self::issue(
				$product,
				'missing_sku',
				self::SEVERITY_WARNING,
				__( 'This product has no SKU.', 'feedguard-woo' ),
				__( 'Give every product a unique SKU. Most feed plugins use the SKU as the stable item ID in Merchant Center.', 'feedguard-woo' )
			);
		} elseif ( in_array( strtolower( $sku ), $duplicate_skus, true ) ) {
			$issues[] = self::issue(
				$product,
				'duplicate_sku',
				self::SEVERITY_ERROR,
				sprintf(
					/* translators: %s: SKU */
					__( 'The SKU "%s" is also used by another product.', 'feedguard-woo' ),
					$sku
				),
				__( 'Make the SKU unique. Duplicate item IDs overwrite each other in Merchant Center, so one of these products disappears.', 'feedguard-woo' )
			);
		}

		// --- Price ----------------------------------------------------------------
		// Grouped products take their price from their children; skip price checks for them.
		if ( ! $product->is_type( 'grouped' ) ) {
			$price = $product->get_price();
			if ( '' === (string) $price ) {
				$issues[] = self::issue(
					$product,
					'missing_price',
					self::SEVERITY_ERROR,
					__( 'This product has no price.', 'feedguard-woo' ),
					__( 'Set a regular price. Google cannot approve an item without a price.', 'feedguard-woo' )
				);
			} elseif ( 0.0 === (float) $price ) {
				$issues[] = self::issue(
					$product,
					'zero_price',
					self::SEVERITY_WARNING,
					__( 'The price is 0.', 'feedguard-woo' ),
					__( 'Set the real selling price, or exclude genuinely free items from your feed.', 'feedguard-woo' )
				);
			}
			$regular = (float) $product->get_regular_price();
			$sale    = $product->get_sale_price();
			if ( '' !== (string) $sale && $regular > 0 && (float) $sale >= $regular ) {
				$issues[] = self::issue(
					$product,
					'sale_price_invalid',
					self::SEVERITY_ERROR,
					sprintf(
						/* translators: 1: sale price, 2: regular price */
						__( 'Sale price (%1$s) is not lower than the regular price (%2$s).', 'feedguard-woo' ),
						wc_format_decimal( (float) $sale ),
						wc_format_decimal( $regular )
					),
					__( 'Lower the sale price below the regular price, or clear the sale price field.', 'feedguard-woo' )
				);
			}
		}

		return $issues;
	}

	/**
	 * Store-level checks: currency, base country, shipping, tax.
	 *
	 * @return array[] Issues (product = null rows).
	 */
	public static function run_store_checks() {
		$issues = array();

		$currency = (string) get_woocommerce_currency();
		if ( '' === $currency ) {
			$issues[] = self::issue(
				null,
				'store_no_currency',
				self::SEVERITY_ERROR,
				__( 'WooCommerce has no currency configured.', 'feedguard-woo' ),
				__( 'Set the store currency in WooCommerce → Settings → General. Every feed price needs a currency.', 'feedguard-woo' )
			);
		}

		$country = (string) get_option( 'woocommerce_default_country' );
		if ( '' === $country ) {
			$issues[] = self::issue(
				null,
				'store_no_country',
				self::SEVERITY_WARNING,
				__( 'The store\'s base country/region is not set.', 'feedguard-woo' ),
				__( 'Set "Base location" in WooCommerce → Settings → General.', 'feedguard-woo' )
			);
		}

		$has_shipping = false;
		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			$zones = WC_Shipping_Zones::get_zones();
			foreach ( (array) $zones as $zone_data ) {
				if ( ! empty( $zone_data['shipping_methods'] ) ) {
					$has_shipping = true;
					break;
				}
			}
			if ( ! $has_shipping ) {
				$rest_zone = WC_Shipping_Zones::get_zone( 0 );
				if ( $rest_zone && $rest_zone->get_shipping_methods() ) {
					$has_shipping = true;
				}
			}
		}
		if ( ! $has_shipping ) {
			$issues[] = self::issue(
				null,
				'store_no_shipping',
				self::SEVERITY_ERROR,
				__( 'No shipping zone has any shipping method enabled.', 'feedguard-woo' ),
				__( 'Add at least one shipping method in WooCommerce → Settings → Shipping — or define shipping in Merchant Center instead (Settings → Shipping and returns). Google needs it in one of the two places.', 'feedguard-woo' )
			);
		}

		if ( function_exists( 'wc_tax_enabled' ) && ! wc_tax_enabled() ) {
			$issues[] = self::issue(
				null,
				'store_tax_disabled',
				self::SEVERITY_WARNING,
				__( '"Enable taxes" is off in WooCommerce.', 'feedguard-woo' ),
				__( 'This is fine if tax is configured in Merchant Center (Settings → Tax). If it is set in neither place, expect price mismatches and disapprovals.', 'feedguard-woo' )
			);
		}

		return $issues;
	}

	/**
	 * Extract the Product structured data (JSON-LD) from a page's HTML.
	 *
	 * @param string $html Page HTML.
	 * @return array|null Product node (with 'offers' normalised to an array), or null.
	 */
	public static function extract_jsonld_product( $html ) {
		if ( ! preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches ) ) {
			return null;
		}
		foreach ( $matches[1] as $block ) {
			$data = json_decode( trim( $block ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$found = self::find_product_node( $data );
			if ( null !== $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * Recursively find a schema.org Product node in decoded JSON-LD.
	 *
	 * @param mixed $node Decoded JSON value.
	 * @return array|null
	 */
	private static function find_product_node( $node ) {
		if ( ! is_array( $node ) ) {
			return null;
		}
		if ( isset( $node['@type'] ) ) {
			$types = is_array( $node['@type'] ) ? $node['@type'] : array( $node['@type'] );
			foreach ( $types as $type ) {
				if ( 0 === strcasecmp( (string) $type, 'Product' ) ) {
					return $node;
				}
			}
		}
		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				$found = self::find_product_node( $value );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Price + availability from a JSON-LD Product node.
	 *
	 * @param array $product_node Product node from extract_jsonld_product().
	 * @return array{price:?float,availability:?string}
	 */
	public static function jsonld_offer_values( $product_node ) {
		$result = array(
			'price'        => null,
			'availability' => null,
		);
		if ( empty( $product_node['offers'] ) || ! is_array( $product_node['offers'] ) ) {
			return $result;
		}
		$offers = $product_node['offers'];
		// Offers is sometimes a list of offer objects; use the first.
		if ( isset( $offers[0] ) && is_array( $offers[0] ) ) {
			$offers = $offers[0];
		}
		if ( isset( $offers['price'] ) && is_numeric( $offers['price'] ) ) {
			$result['price'] = (float) $offers['price'];
		} elseif ( isset( $offers['lowPrice'] ) && is_numeric( $offers['lowPrice'] ) ) {
			// AggregateOffer: the feed sends the lowest variation price too.
			$result['price'] = (float) $offers['lowPrice'];
		}
		if ( ! empty( $offers['availability'] ) && is_string( $offers['availability'] ) ) {
			$result['availability'] = $offers['availability'];
		}
		return $result;
	}

	/**
	 * Fallback price extraction from WooCommerce's price meta tag.
	 *
	 * @param string $html Page HTML.
	 * @return float|null
	 */
	public static function extract_meta_price( $html ) {
		if ( preg_match( '#property=["\']product:price:amount["\'][^>]*content=["\']([0-9]+(?:[.,][0-9]+)?)["\']#i', $html, $m ) ) {
			return (float) str_replace( ',', '.', $m[1] );
		}
		if ( preg_match( '#content=["\']([0-9]+(?:[.,][0-9]+)?)["\'][^>]*property=["\']product:price:amount["\']#i', $html, $m ) ) {
			return (float) str_replace( ',', '.', $m[1] );
		}
		return null;
	}

	/**
	 * Compare one product's live page against its data.
	 *
	 * Never returns an issue for a page that could not be fetched or parsed —
	 * those are reported through the 'status' so the report can show them as
	 * "not checked" instead of pretending they failed.
	 *
	 * @param WC_Product $product Product.
	 * @return array{status:string,reason:string,issues:array[]}
	 *   status: 'checked' | 'unchecked'
	 */
	public static function run_page_check( $product ) {
		$out = array(
			'status' => 'unchecked',
			'reason' => '',
			'issues' => array(),
		);
		$url = (string) $product->get_permalink();
		if ( '' === $url ) {
			$out['reason'] = __( 'Product has no permalink.', 'feedguard-woo' );
			return $out;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'user-agent'  => 'FeedGuard-Woo/' . FEEDGUARD_WOO_VERSION . ' (pre-flight check; ' . home_url( '/' ) . ')',
			)
		);
		if ( is_wp_error( $response ) ) {
			$out['reason'] = $response->get_error_message();
			return $out;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$out['reason'] = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Page returned HTTP %d.', 'feedguard-woo' ),
				$code
			);
			return $out;
		}
		$html = (string) wp_remote_retrieve_body( $response );
		if ( '' === $html ) {
			$out['reason'] = __( 'Page returned an empty body.', 'feedguard-woo' );
			return $out;
		}

		$out['status'] = 'checked';
		$node          = self::extract_jsonld_product( $html );
		$page_price    = null;
		$page_avail    = null;
		if ( null !== $node ) {
			$values     = self::jsonld_offer_values( $node );
			$page_price = $values['price'];
			$page_avail = $values['availability'];
		}
		if ( null === $page_price ) {
			$page_price = self::extract_meta_price( $html );
		}

		// --- Price comparison -------------------------------------------------
		// The feed price may be the raw price or the tax-display price,
		// depending on store settings; accept a match against either.
		if ( null !== $page_price && ! $product->is_type( 'grouped' ) ) {
			$candidates = array_unique(
				array_filter(
					array(
						(float) $product->get_price(),
						(float) wc_get_price_to_display( $product ),
					),
					static function ( $v ) {
						return $v > 0;
					}
				)
			);
			$matches = false;
			foreach ( $candidates as $candidate ) {
				if ( abs( $candidate - $page_price ) < 0.01 ) {
					$matches = true;
					break;
				}
			}
			if ( ! $matches && ! empty( $candidates ) ) {
				$currency = get_woocommerce_currency();
				$out['issues'][] = self::issue(
					$product,
					'page_price_mismatch',
					self::SEVERITY_ERROR,
					sprintf(
						/* translators: 1: page price, 2: data price, 3: currency code */
						__( 'The product page shows %1$s but the product data price is %2$s (%3$s). Google compares the feed with the page and disapproves mismatches.', 'feedguard-woo' ),
						wc_format_decimal( $page_price ),
						wc_format_decimal( (float) $product->get_price() ),
						$currency
					),
					__( 'Fix the price at its source: check the regular/sale price fields, the "display prices with/without tax" setting, and any plugin that changes front-end prices (roles, B2B, currency switchers). Do not edit the feed to hide this.', 'feedguard-woo' )
				);
			}
		}

		// --- Availability comparison -------------------------------------------
		if ( null !== $page_avail ) {
			$page_in_stock = false === stripos( $page_avail, 'OutOfStock' ) && false === stripos( $page_avail, 'SoldOut' );
			if ( $page_in_stock !== $product->is_in_stock() ) {
				$out['issues'][] = self::issue(
					$product,
					'page_availability_mismatch',
					self::SEVERITY_WARNING,
					$page_in_stock
						? __( 'The page structured data says "in stock" but WooCommerce marks this product out of stock.', 'feedguard-woo' )
						: __( 'The page structured data says "out of stock" but WooCommerce marks this product in stock.', 'feedguard-woo' ),
					__( 'Check the stock status and any caching/structured-data plugin: the page, the feed and WooCommerce must all agree.', 'feedguard-woo' )
				);
			}
		}

		return $out;
	}

	/**
	 * Run a full check across the store.
	 *
	 * @param array $args {
	 *     @type bool $page_checks      Whether to fetch product pages. Default true.
	 *     @type int  $page_check_limit Max products to page-check (0 = all). Default 25.
	 *     @type bool $include_unpublished Also check non-published products. Default false.
	 * }
	 * @return array {
	 *     @type string   $run_at           Localised date/time of the run.
	 *     @type int      $products_checked Number of products data-checked.
	 *     @type int      $page_checked     Number of products whose page was fetched OK.
	 *     @type int      $page_unchecked   Number of products whose page could not be checked.
	 *     @type array[]  $store_issues     Store-level issues.
	 *     @type array[]  $issues           All product-level issues (including page issues).
	 *     @type int      $errors           Error-severity issue count (store + product).
	 *     @type int      $warnings         Warning-severity issue count (store + product).
	 * }
	 */
	public static function run_all( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'page_checks'         => true,
				'page_check_limit'    => 25,
				'include_unpublished' => false,
			)
		);

		$statuses = $args['include_unpublished']
			? array( 'publish', 'draft', 'pending', 'private' )
			: array( 'publish' );

		// First pass: collect products page by page, building the SKU map so
		// duplicates can be detected without loading the whole catalogue twice.
		$products       = array();
		$sku_counts     = array();
		$page_number    = 1;
		do {
			$batch = wc_get_products(
				array(
					'status' => $statuses,
					'limit'  => 100,
					'page'   => $page_number,
					'return' => 'objects',
					'orderby' => 'ID',
					'order'  => 'ASC',
				)
			);
			foreach ( (array) $batch as $product ) {
				$products[] = $product;
				$sku        = strtolower( trim( (string) $product->get_sku() ) );
				if ( '' !== $sku ) {
					$sku_counts[ $sku ] = isset( $sku_counts[ $sku ] ) ? $sku_counts[ $sku ] + 1 : 1;
				}
			}
			$page_number++;
		} while ( is_array( $batch ) && 100 === count( $batch ) );

		$duplicate_skus = array_keys(
			array_filter(
				$sku_counts,
				static function ( $count ) {
					return $count > 1;
				}
			)
		);

		$issues         = array();
		$page_checked   = 0;
		$page_unchecked = 0;
		$page_budget    = $args['page_checks'] ? (int) $args['page_check_limit'] : 0;

		foreach ( $products as $product ) {
			$issues = array_merge( $issues, self::run_product_checks( $product, $duplicate_skus ) );

			// Page checks run on published, visible products only, newest budget first come.
			if ( $args['page_checks']
				&& 'publish' === $product->get_status()
				&& 'hidden' !== $product->get_catalog_visibility()
				&& ( 0 === $page_budget || $page_checked + $page_unchecked < $page_budget ) ) {
				$result = self::run_page_check( $product );
				if ( 'checked' === $result['status'] ) {
					$page_checked++;
					$issues = array_merge( $issues, $result['issues'] );
				} else {
					$page_unchecked++;
				}
			}
		}

		$store_issues = self::run_store_checks();

		$errors = 0;
		$warnings = 0;
		foreach ( array_merge( $store_issues, $issues ) as $row ) {
			if ( self::SEVERITY_ERROR === $row['severity'] ) {
				$errors++;
			} else {
				$warnings++;
			}
		}

		return array(
			'run_at'           => current_time( 'mysql' ),
			'products_checked' => count( $products ),
			'page_checked'     => $page_checked,
			'page_unchecked'   => $page_unchecked,
			'store_issues'     => $store_issues,
			'issues'           => $issues,
			'errors'           => $errors,
			'warnings'         => $warnings,
		);
	}

	/**
	 * Group a flat issue list by check slug, worst severity first.
	 *
	 * @param array[] $issues Issues.
	 * @return array<string,array[]> check slug => issues.
	 */
	public static function group_issues( $issues ) {
		$groups = array();
		foreach ( $issues as $row ) {
			$groups[ $row['check'] ][] = $row;
		}
		uasort(
			$groups,
			static function ( $a, $b ) {
				$rank = static function ( $rows ) {
					foreach ( $rows as $row ) {
						if ( self::SEVERITY_ERROR === $row['severity'] ) {
							return 0;
						}
					}
					return 1;
				};
				$ra = $rank( $a );
				$rb = $rank( $b );
				if ( $ra === $rb ) {
					return count( $b ) <=> count( $a );
				}
				return $ra <=> $rb;
			}
		);
		return $groups;
	}
}
