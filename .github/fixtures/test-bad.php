<?php
/**
 * Plugin Name: TLS checker bad plugin
 * Description: Makes a request against a known bad (non-TLS 1.2+) URL
 * Version: 1.3
 * Author: Pantheon Systems
 */
add_action( 'admin_init', function() {
	print_r( wp_remote_get( 'https://tls-v1-1.badssl.com:1011/' ) );
});