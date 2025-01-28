<?php
/**
 * Plugin Name: TLS Compatibility Checker
 * Description: A scanner for outgoing HTTP requests in WordPress code to check TLS 1.2/1.3 compatibility.
 * Version: 1.3
 * Author: Chris Reynolds
 */

namespace Pantheon\TLSChecker;

function bootstrap() {
	define( 'TLS_CHECKER_INC', plugin_dir_path( __FILE__ ) . '/inc/' );
	require_once TLS_CHECKER_INC . 'admin.php';
	require_once TLS_CHECKER_INC . 'core.php';
	require_once TLS_CHECKER_INC . 'site-health.php';

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once TLS_CHECKER_INC . 'cli.php';
	}

	register_activation_hook( __FILE__, __NAMESPACE__ . '\\tls_checker_activate' );
	register_activation_hook( __FILE__, __NAMESPACE__ . '\\tls_checker_deactivate' );
}

function tls_checker_activate() {
	// Initialize database.
	if ( ! get_option( 'tls_checker_passing_urls' ) && ! get_option( 'tls_checker_failing_urls' ) ) {
		pantheon_tls_checker_reset_urls();
	}
}

function tls_checker_deactivate() {
	pantheon_tls_checker_delete_options();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
