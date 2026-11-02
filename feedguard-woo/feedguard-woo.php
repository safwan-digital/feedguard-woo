<?php
/**
 * Plugin Name:       FeedGuard for WooCommerce
 * Plugin URI:        https://github.com/safwan-digital/feedguard-woo
 * Description:       Pre-flight checks for Google Merchant Center, inside WooCommerce. Finds missing GTINs, bad prices, duplicate SKUs and page-vs-data mismatches before Google disapproves your products. No external calls, no telemetry.
 * Version:           0.1.0
 * Author:            Safwan Siddi
 * Author URI:        https://github.com/safwan-digital
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       feedguard-woo
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 *
 * FeedGuard for WooCommerce is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * @package FeedGuard_Woo
 */

defined( 'ABSPATH' ) || exit;

define( 'FEEDGUARD_WOO_VERSION', '0.1.0' );
define( 'FEEDGUARD_WOO_FILE', __FILE__ );
define( 'FEEDGUARD_WOO_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEEDGUARD_WOO_URL', plugin_dir_url( __FILE__ ) );

/*
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 * FeedGuard never reads or writes orders, but the declaration keeps the
 * plugin warning-free on HPOS-enabled stores.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FEEDGUARD_WOO_FILE, true );
		}
	}
);

/**
 * Whether WooCommerce is active on this site (multisite-aware).
 *
 * @return bool
 */
function feedguard_woo_woocommerce_active() {
	if ( class_exists( 'WooCommerce' ) ) {
		return true;
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active_for_network( 'woocommerce/woocommerce.php' )
		|| is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Activation: hard-check WooCommerce and remember the version.
 * The plugin stays loadable without WooCommerce so the admin notice can
 * explain what is missing instead of fataling.
 */
function feedguard_woo_activate() {
	update_option( 'feedguard_woo_version', FEEDGUARD_WOO_VERSION );
	if ( ! feedguard_woo_woocommerce_active() ) {
		set_transient( 'feedguard_woo_missing_wc', 1, MINUTE_IN_SECONDS * 5 );
	}
}
register_activation_hook( __FILE__, 'feedguard_woo_activate' );

/**
 * Admin notice shown when WooCommerce is missing.
 */
function feedguard_woo_missing_wc_notice() {
	if ( feedguard_woo_woocommerce_active() ) {
		return;
	}
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'FeedGuard for WooCommerce needs WooCommerce to be installed and active. The checks will not run until WooCommerce is active.', 'feedguard-woo' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'feedguard_woo_missing_wc_notice' );

/**
 * Boot the plugin once all plugins are loaded.
 */
function feedguard_woo_init() {
	if ( ! feedguard_woo_woocommerce_active() ) {
		return;
	}
	require_once FEEDGUARD_WOO_DIR . 'includes/class-feedguard-checks.php';
	if ( is_admin() ) {
		require_once FEEDGUARD_WOO_DIR . 'includes/class-feedguard-admin.php';
		FeedGuard_Woo_Admin::init();
	}
}
add_action( 'plugins_loaded', 'feedguard_woo_init' );
