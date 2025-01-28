<?php
/**
 * TLS Compatibility Checker Site Health
 * 
 * @package Pantheon_TLS_Compatibility_Checker
 */

namespace Pantheon\TLSChecker\SiteHealth;

function bootstrap() {
	add_filter( 'site_status_tests', __NAMESPACE__ . '\\site_health_check', 10 );
}

function site_health_check( $tests ) {
	$tests['direct']['tls_check_failing_urls'] = [
		'label' => __( 'TLS Failing URLs', 'pantheon-tls-compatibility-checker' ),
		'test' => __NAMESPACE__ . '\\site_health_test',
	];

	return $tests;
}

function site_health_test() {
	$failing_urls = pantheon_tls_checker_get_failing_urls();

	if ( ! empty( $failing_urls ) ) {
		return [
			'status' => 'critical',
			'label' => __( 'TLS Failing URLs Detected', 'pantheon-tls-compatibility-checker' ),
			'message' => sprintf(
				__( 'The following URLs do not support TLS 1.2 or higher: %s', 'pantheon-tls-compatibility-checker' ),
				'<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $failing_urls ) ) . '</li></ul>'
			),
		];
	}

	return [
		'status' => 'good',
		'label' => __( 'All scanned URLs pass TLS check', 'pantheon-tls-compatibility-checker' ),
		'message' => __( 'All scanned URLs support TLS 1.2 or higher.', 'pantheon-tls-compatibility-checker' ),
	];
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );