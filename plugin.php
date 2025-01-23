<?php
/**
 * Plugin Name: TLS Compatibility Checker
 * Description: A scanner for outgoing HTTP requests in WordPress code to check TLS 1.2/1.3 compatibility.
 * Version: 1.3
 * Author: Chris Reynolds
 */

namespace Pantheon\TLSChecker;

function bootstrap() {
	require_once plugin_dir_path( __FILE__ ) . '/cli.php';
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );