<?php
/**
 * TLS Compatibility Checker Admin Page
 * 
 * @package Pantheon_TLS_Compatibility_Checker
 */

namespace Pantheon\TLSChecker\Admin;

function bootstrap() {
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_menu_page' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_css' );
}

function enqueue_css() {
	wp_enqueue_style( 'tls-compatibility-admin', TLS_CHECKER_ASSETS . 'admin.css', [], TLS_CHECKER_VERSION, 'screen' );
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
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form method="post">
			<?php wp_nonce_field( 'pantheon_tls_checker_reset_action' ); ?>
			<button type="submit" name="pantheon_tls_checker_reset" class="button button-secondary">
				<?php esc_html_e( 'Reset TLS Checker Data', 'pantheon-tls-compatibility-checker' ); ?>
			</button>
		</form>
	</div>
	<?php
}

add_action( 'admin_menu', __NAMESPACE__ . '\\add_menu_page' );