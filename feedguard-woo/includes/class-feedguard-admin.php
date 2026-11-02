<?php
/**
 * FeedGuard admin: the report page under WooCommerce → FeedGuard and the
 * CSV export handler.
 *
 * Everything here requires the `manage_woocommerce` capability and is
 * protected by nonces. Results of the last run are kept in a per-user
 * transient (1 hour) so the CSV export downloads exactly what the report
 * showed.
 *
 * @package FeedGuard_Woo
 */

defined( 'ABSPATH' ) || exit;

class FeedGuard_Woo_Admin {

	const MENU_SLUG = 'feedguard-woo';
	const NONCE_RUN = 'feedguard_woo_run';
	const NONCE_EXPORT = 'feedguard_woo_export';

	/**
	 * Register hooks. Called once from the main plugin file.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_feedguard_woo_export_csv', array( __CLASS__, 'handle_csv_export' ) );
	}

	/**
	 * Transient key holding the last results for the current user.
	 *
	 * @return string
	 */
	private static function results_key() {
		return 'feedguard_woo_results_' . get_current_user_id();
	}

	/**
	 * Add the report page under the WooCommerce menu.
	 */
	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'FeedGuard — Merchant Center pre-flight checks', 'feedguard-woo' ),
			__( 'FeedGuard', 'feedguard-woo' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Load the small admin stylesheet on our page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'feedguard-woo-admin',
			FEEDGUARD_WOO_URL . 'assets/admin.css',
			array(),
			FEEDGUARD_WOO_VERSION
		);
	}

	/**
	 * Render the report page, running a check first when the form posted.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to run FeedGuard checks.', 'feedguard-woo' ) );
		}

		$results = get_transient( self::results_key() );
		if ( ! is_array( $results ) ) {
			$results = null;
		}

		// Handle the "Run checks" form submission.
		if ( isset( $_POST['feedguard_woo_run'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- verified immediately below.
			check_admin_referer( self::NONCE_RUN );
			$page_checks = isset( $_POST['feedguard_woo_page_checks'] ); // phpcs:ignore WordPress.Security.NonceVerification -- verified above.
			$limit       = isset( $_POST['feedguard_woo_page_limit'] ) ? absint( $_POST['feedguard_woo_page_limit'] ) : 25; // phpcs:ignore WordPress.Security.NonceVerification -- verified above.
			$results     = FeedGuard_Woo_Checks::run_all(
				array(
					'page_checks'      => $page_checks,
					'page_check_limit' => $limit,
				)
			);
			set_transient( self::results_key(), $results, HOUR_IN_SECONDS );
		}

		$run_page_checks = true; // Page checks are on by default; they are the highest-value check.
		?>
		<div class="wrap feedguard-woo">
			<h1><?php esc_html_e( 'FeedGuard — Merchant Center pre-flight checks', 'feedguard-woo' ); ?></h1>

			<div class="notice notice-info inline feedguard-woo-disclaimer">
				<p>
					<?php esc_html_e( 'FeedGuard is an early-warning tool. It checks your product data against the problems that most often cause Google Merchant Center disapprovals — but Merchant Center\'s own diagnostics are the only authoritative verdict. A clean FeedGuard report does not guarantee approval, and FeedGuard never promises it.', 'feedguard-woo' ); ?>
				</p>
			</div>

			<form method="post" class="feedguard-woo-run-form">
				<?php wp_nonce_field( self::NONCE_RUN ); ?>
				<input type="hidden" name="feedguard_woo_run" value="1" />
				<p>
					<label>
						<input type="checkbox" name="feedguard_woo_page_checks" value="1" <?php checked( $run_page_checks ); ?> />
						<?php esc_html_e( 'Also fetch each product page and compare its price/availability with the product data (slower, recommended)', 'feedguard-woo' ); ?>
					</label>
				</p>
				<p>
					<label for="feedguard_woo_page_limit"><?php esc_html_e( 'Maximum products to page-check (0 = all):', 'feedguard-woo' ); ?></label>
					<input type="number" id="feedguard_woo_page_limit" name="feedguard_woo_page_limit" value="25" min="0" max="1000" class="small-text" />
				</p>
				<?php submit_button( __( 'Run checks', 'feedguard-woo' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( null === $results ) : ?>
				<p><?php esc_html_e( 'No check has been run yet in this session. Run one above — a typical store takes a few seconds without page checks.', 'feedguard-woo' ); ?></p>
			<?php else : ?>
				<?php self::render_results( $results ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a results set: summary, store issues, grouped product issues, export form.
	 *
	 * @param array $results Results from FeedGuard_Woo_Checks::run_all().
	 */
	private static function render_results( $results ) {
		$definitions = FeedGuard_Woo_Checks::get_check_definitions();
		?>
		<hr />
		<h2>
			<?php
			printf(
				/* translators: %s: date/time of the run */
				esc_html__( 'Results — run at %s', 'feedguard-woo' ),
				esc_html( $results['run_at'] )
			);
			?>
		</h2>
		<ul class="feedguard-woo-summary">
			<li><?php printf( esc_html__( 'Products checked: %d', 'feedguard-woo' ), (int) $results['products_checked'] ); ?></li>
			<li class="feedguard-woo-error-text"><?php printf( esc_html__( 'Errors: %d', 'feedguard-woo' ), (int) $results['errors'] ); ?></li>
			<li class="feedguard-woo-warning-text"><?php printf( esc_html__( 'Warnings: %d', 'feedguard-woo' ), (int) $results['warnings'] ); ?></li>
			<li><?php printf( esc_html__( 'Pages compared: %d', 'feedguard-woo' ), (int) $results['page_checked'] ); ?></li>
			<?php if ( (int) $results['page_unchecked'] > 0 ) : ?>
				<li><?php printf( esc_html__( 'Pages that could not be checked (network/HTTP problems — not counted as failures): %d', 'feedguard-woo' ), (int) $results['page_unchecked'] ); ?></li>
			<?php endif; ?>
		</ul>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_EXPORT ); ?>
			<input type="hidden" name="action" value="feedguard_woo_export_csv" />
			<?php submit_button( __( 'Download CSV report', 'feedguard-woo' ), 'secondary', 'submit', false ); ?>
		</form>

		<?php if ( ! empty( $results['store_issues'] ) ) : ?>
			<h3><?php esc_html_e( 'Store settings', 'feedguard-woo' ); ?></h3>
			<?php self::render_issue_table( $results['store_issues'], $definitions ); ?>
		<?php endif; ?>

		<?php
		if ( empty( $results['issues'] ) ) {
			echo '<p class="feedguard-woo-clean">' . esc_html__( 'No product issues found. Nice — but remember, Merchant Center diagnostics remain the final word.', 'feedguard-woo' ) . '</p>';
		}
		foreach ( FeedGuard_Woo_Checks::group_issues( $results['issues'] ) as $check => $rows ) {
			$label = isset( $definitions[ $check ] ) ? $definitions[ $check ]['label'] : $check;
			$why   = isset( $definitions[ $check ] ) ? $definitions[ $check ]['why'] : '';
			?>
			<h3>
				<?php echo esc_html( $label ); ?>
				<span class="feedguard-woo-count">
					<?php
					printf(
						/* translators: %d: number of affected products */
						esc_html( _n( '(%d product)', '(%d products)', count( $rows ), 'feedguard-woo' ) ),
						count( $rows )
					);
					?>
				</span>
			</h3>
			<?php if ( '' !== $why ) : ?>
				<p class="feedguard-woo-why"><?php echo esc_html( $why ); ?></p>
			<?php endif; ?>
			<?php self::render_issue_table( $rows, $definitions ); ?>
			<?php
		}
	}

	/**
	 * Render one group of issues as a table.
	 *
	 * @param array[] $rows        Issue rows.
	 * @param array   $definitions Check definitions (unused beyond typing clarity).
	 */
	private static function render_issue_table( $rows, $definitions ) {
		?>
		<table class="widefat striped feedguard-woo-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'feedguard-woo' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'feedguard-woo' ); ?></th>
					<th><?php esc_html_e( 'Severity', 'feedguard-woo' ); ?></th>
					<th><?php esc_html_e( 'What was found', 'feedguard-woo' ); ?></th>
					<th><?php esc_html_e( 'How to fix it', 'feedguard-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td>
							<?php if ( (int) $row['product_id'] > 0 && '' !== $row['edit_url'] ) : ?>
								<a href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php echo esc_html( $row['product_name'] ); ?></a>
								<span class="feedguard-woo-id">#<?php echo (int) $row['product_id']; ?></span>
							<?php else : ?>
								<?php echo esc_html( $row['product_name'] ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( '' !== $row['sku'] ? $row['sku'] : '—' ); ?></td>
						<td>
							<span class="feedguard-woo-badge feedguard-woo-badge-<?php echo esc_attr( $row['severity'] ); ?>">
								<?php echo esc_html( $row['severity'] ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $row['detail'] ); ?></td>
						<td><?php echo esc_html( $row['fix'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * CSV export handler (admin-post.php?action=feedguard_woo_export_csv).
	 * Exports the last run's results; if there is no recent run, performs a
	 * data-only run (no page fetches) so the button always works.
	 */
	public static function handle_csv_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export FeedGuard reports.', 'feedguard-woo' ) );
		}
		check_admin_referer( self::NONCE_EXPORT );

		$results = get_transient( self::results_key() );
		if ( ! is_array( $results ) ) {
			$results = FeedGuard_Woo_Checks::run_all( array( 'page_checks' => false ) );
			set_transient( self::results_key(), $results, HOUR_IN_SECONDS );
		}

		$filename = 'feedguard-report-' . gmdate( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a download, no file involved.
		if ( false === $out ) {
			wp_die( esc_html__( 'Could not generate the CSV export.', 'feedguard-woo' ) );
		}
		fputcsv( $out, array( 'product_id', 'sku', 'product_name', 'check', 'severity', 'detail', 'fix', 'edit_url' ) );
		foreach ( array_merge( (array) $results['store_issues'], (array) $results['issues'] ) as $row ) {
			fputcsv(
				$out,
				array(
					$row['product_id'],
					$row['sku'],
					$row['product_name'],
					$row['check'],
					$row['severity'],
					$row['detail'],
					$row['fix'],
					$row['edit_url'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
