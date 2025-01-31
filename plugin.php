<?php
/**
 * Plugin Name: TLS Compatibility Checker
 * Description: A scanner for outgoing HTTP requests in WordPress code to check TLS 1.2/1.3 compatibility.
 * Version: 1.0.0
 * Author: Pantheon Systems
 * Author URI: https://pantheon.io
 * License: MIT
 * GitHub Plugin URI: jazzsequence/pantheon-tls-compatibility-checker
 * Primary Branch: main
 */

namespace Pantheon\TLSChecker;

/**
 * Bootstrap the plugin.
 */
function bootstrap() {
	define( 'TLS_CHECKER_INC', plugin_dir_path( __FILE__ ) . '/inc/' );
	define( 'TLS_CHECKER_ASSETS', plugin_dir_url( __FILE__ ) . '/assets/' );
	define( 'TLS_CHECKER_VERSION', '1.0.0' );
	require_once TLS_CHECKER_INC . 'core.php';
	require_once TLS_CHECKER_INC . 'admin.php';
	require_once TLS_CHECKER_INC . 'site-health.php';

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once TLS_CHECKER_INC . 'cli.php';
	}

	register_activation_hook( __FILE__, __NAMESPACE__ . '\\tls_checker_activate' );
	register_activation_hook( __FILE__, __NAMESPACE__ . '\\tls_checker_deactivate' );
}

/**
 * Plugin activation hooks.
 */
function tls_checker_activate() {
	// Initialize database.
	if ( ! get_option( 'tls_checker_passing_urls' ) && ! get_option( 'tls_checker_failing_urls' ) ) {
		pantheon_tls_checker_reset_urls();
	}
}

/**
 * Plugin deactivation hooks.
 */
function tls_checker_deactivate() {
	pantheon_tls_checker_delete_options();
}

// Kick it off.
bootstrap();
