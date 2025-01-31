<?php
/**
 * TLS Compatibility Checker Site Health
 * 
 * @package Pantheon_TLS_Compatibility_Checker
 */

namespace Pantheon\TLSChecker\SiteHealth;

/**
 * Add a site health check for TLS compatibility.
 *
 * @param array $tests The existing site health tests.
 * @return array The modified site health tests.
 */
function site_health_check( $tests ) {
	$tests['direct']['pantheon_tls_check_failing_urls'] = [
		'label' => __( 'TLS Failing URLs', 'pantheon-tls-compatibility-checker' ),
		'test' => __NAMESPACE__ . '\\site_health_test',
	];

	return $tests;
}

/**
 * Test the site's TLS compatibility.
 *
 * @return array The test result.
 */
function site_health_test() {
	$failing_urls = pantheon_tls_checker_get_failing_urls();
	$result = [
		'status' => 'good',
		'label' => __( 'All scanned URLs pass TLS check', 'pantheon-tls-compatibility-checker' ),
		'description' => __( 'All scanned URLs support TLS 1.2 or higher.', 'pantheon-tls-compatibility-checker' ),
		'badge' => [
			'label' => __( 'TLS Compatibility', 'pantheon-tls-compatibility-checker' ),
			'color' => 'green',
		],
		'test' => 'pantheon_tls_check_failing_urls',
	];

	if ( ! empty( $failing_urls ) ) {
		$result['status'] = 'critical';
		$result['label'] = __( 'TLS Failing URLs Detected', 'pantheon-tls-compatibility-checker' );
		$result['badge']['color'] = 'red';
		$result['description'] = sprintf(
			// Translators: %s is a list of URLs.
			__( 'The following URLs do not support TLS 1.2 or higher: %s', 'pantheon-tls-compatibility-checker' ),
			'<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $failing_urls ) ) . '</li></ul>'
		);
	}

	return $result;
}

add_filter( 'site_status_tests', __NAMESPACE__ . '\\site_health_check', 10 );
