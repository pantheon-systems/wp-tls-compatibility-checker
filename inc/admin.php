<?php
/**
 * TLS Compatibility Checker Admin Page
 * 
 * @package Pantheon_TLS_Compatibility_Checker
 */

namespace Pantheon\TLSChecker\Admin;

function bootstrap() {
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_menu_page' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts' );
	add_action( 'wp_ajax_pantheon_tls_checker_scan', __NAMESPACE__ . '\\handle_ajax_scan' );
	add_action( 'wp_ajax_nopriv_pantheon_tls_checker_scan', __NAMESPACE__ . '\\handle_ajax_scan' ); // Allow for non-logged in users if needed
	
}

function enqueue_scripts() {
	$screen = get_current_screen();

	// Only load the css on our admin page.
	if ( $screen && $screen->base === 'tools_page_tls-compatibility-checker' ) {
		wp_enqueue_style( 'tls-compatibility-admin', TLS_CHECKER_ASSETS . 'admin.css', [], TLS_CHECKER_VERSION, 'screen' );
		wp_enqueue_script( 'tls-compatibility-scan', TLS_CHECKER_ASSETS . 'scan.js', [], TLS_CHECKER_VERSION );
		wp_localize_script( 'tls-compatibility-scan', 'tlsCheckerAjax', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'pantheon_tls_checker_scan_action' ),
		] );
	}
}

function add_menu_page() {
	add_submenu_page(
		'tools.php',
		__( 'TLS Compatibility Checker', 'pantheon-tls-compatibility-checker' ),
		__( 'TLS Compatibility', 'pantheon-tls-compatibility-checker' ),
		'manage_options',
		'tls-compatibility-checker',
		__NAMESPACE__ . '\\render_page'
	);
}

function render_page() {
	if ( isset( $_POST['pantheon_tls_checker_reset'] ) ) {
		check_admin_referer( 'pantheon_tls_checker_reset_action' );
		pantheon_tls_checker_reset_urls();
		add_action( 'admin_notices', __NAMESPACE__ . '\\reset_successful_notice' );
	}

	$failing_urls = pantheon_tls_checker_get_failing_urls();
	$passing_urls = pantheon_tls_checker_get_passing_urls();
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<div id="pantheon-tls-alert-container"></div>
		<p><?php esc_html_e( 'Check your codebase for outgoing connections to servers that support TLS 1.2/1.3', 'pantheon-tls-compatibility-checker' ); ?></p>
		<div class="failing-urls">
			<p>
				<?php echo wp_kses_post( 'The following URLs were found in your codebase that do <em>not</em> support TLS connections of 1.2 or higher.', 'pantheon-tls-compatibility-checker' ); ?>
			</p>
			<pre class="card">
<?php 
			if ( empty( $failing_urls ) && empty( $passing_urls ) ) {
				esc_html_e( 'No URLs failing TLS 1.2/1.3 connections found. Try running a scan.', 'pantheon-tls-compatibility-checker' ); 
			} elseif ( empty( $failing_urls ) && ! empty( $passing_urls ) ) {
				esc_html_e( 'All outgoing HTTP/HTTPS connections found are compatible with TLS 1.2/1.3.', 'pantheon-tls-compatibility-checker' );
			} else {
				foreach ( $failing_urls as $url ) {
					echo esc_url( $url ) . "\n";
				} 
			}
?>
			</pre>
			<p class="description">
				<?php esc_html_e( 'Use the "Reset TLS Compatibility Data" button below to remove stored data from previous scans. This is not required and should only be done if you wish to re-run a scan from scratch. Subsequent scans will automatically skip checking any URLs that have already been tested and passed.', 'pantheon-tls-compatibility-checker' ); ?>
			</p>
		</div>
		<div class="tls-scan">
			<h2><?php esc_html_e( 'Scan your site for outgoing TLS connections', 'pantheon-tls-compatibility-checker' ); ?></h2>

			<p>
				<?php esc_html_e( 'You can check your site for HTTP/HTTPS connections by using WP-CLI (see details below) or in the dashboard with the "Scan site for TLS 1.2/1.3 compatibility" button.', 'pantheon-tls-compatibility-checker' ); ?>
				<br />
				<?php echo wp_kses_post( sprintf( 
					'<a href="%1$s">%2$s</a>',
					'https://www.cloudflare.com/learning/ssl/transport-layer-security-tls/',
					__( 'Learn more about TLS.', 'pantheon-tls-compatibility-checker' ) 
				) ); ?>
			</p>
			<div class="tls-compatibility-actions">
				<form method="post">
					<?php wp_nonce_field( 'pantheon_tls_checker_reset_action' ); ?>
					<button type="submit" name="pantheon_tls_checker_reset" class="button button-secondary">
						<?php esc_html_e( 'Reset TLS Compatibility Data', 'pantheon-tls-compatibility-checker' ); ?>
					</button>
				</form>
				<form method="post">
					<?php wp_nonce_field( 'pantheon_tls_checker_scan_action' ); ?>
					<button type="submit" name="pantheon_tls_checker_scan" id="pantheon-tls-scan" class="button button-primary">
						<?php esc_html_e( 'Scan site for TLS 1.2/1.3 compatibility', 'pantheon-tls-compatibility-checker' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>
	<?php
}

bootstrap();
