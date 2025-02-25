<?php
/**
 * TLS Compatibility Checker Public Functions
 * 
 * @package Pantheon_TLS_Compatibility_Checker
 */

/**
 * Delete stored TLS scan data.
 */
function pantheon_tls_checker_delete_options() {
	delete_option( 'tls_checker_passing_urls' );
	delete_option( 'tls_checker_failing_urls' );
}

/**
 * Get a list of URLs that pass the TLS check.
 * 
 * @return array The array of passing URLs.
 */
function pantheon_tls_checker_get_passing_urls() {
	return get_option( 'tls_checker_passing_urls', [] );
}

/**
 * Get a list of URLs that fail the TLS check.
 * 
 * @return array The array of failing URLs.
 */
function pantheon_tls_checker_get_failing_urls() {
	return get_option( 'tls_checker_failing_urls', [] );
}

/**
 * Add a passing URL to the database.
 *
 * @param string $url The URL to add to the list of passing URLs.
 */
function pantheon_tls_checker_add_passing_url( $url ) {
	$passing_urls = pantheon_tls_checker_get_passing_urls();
	if ( ! in_array( $url, $passing_urls, true ) ) {
		$passing_urls[] = $url;
		update_option( 'tls_checker_passing_urls', $passing_urls );
	}
}

/**
 * Add a failing URL to the database.
 *
 * @param string $url The URL to add to the list of failing URLs.
 */
function pantheon_tls_checker_add_failing_url( $url ) {
	$failing_urls = pantheon_tls_checker_get_failing_urls();
	if ( ! in_array( $url, $failing_urls, true ) ) {
		$failing_urls[] = $url;
		update_option( 'tls_checker_failing_urls', $failing_urls );
	}
}

/**
 * Remove a passing URL from the database.
 *
 * @param string $url The URL to remove.
 */
function pantheon_tls_checker_remove_passing_url( $url ) {
	$passing_urls = pantheon_tls_checker_get_passing_urls();
	$index = array_search( $url, $passing_urls, true );
	if ( $index !== false ) {
		unset( $passing_urls[ $index ] );
		update_option( 'tls_checker_passing_urls', $passing_urls );
	}
}

/**
 * Remove a failing URL from the database.
 *
 * @param string $url The URL to remove.
 */
function pantheon_tls_checker_remove_failing_url( $url ) {
	$failing_urls = pantheon_tls_checker_get_failing_urls();
	$index = array_search( $url, $failing_urls, true );
	if ( $index !== false ) {
		unset( $failing_urls[ $index ] );
		update_option( 'tls_checker_failing_urls', $failing_urls );
	}
}

/**
 * Reset stored options to empty arrays.
 * 
 * This is different than pantheon_tls_checker_delete_options which actually removes the options from the database entirely.
 */
function pantheon_tls_checker_reset_urls() {
	update_option( 'tls_checker_passing_urls', [] );
	update_option( 'tls_checker_failing_urls', [] );
}

/**
 * Get a list of URLs to skip.
 * 
 * @uses pantheon.tls_checker.skip_urls
 * @return array An array of (unique) URLs to skip when running the scan.
 */
function pantheon_tls_checker_get_skip_urls() {
	/**
	 * Allow specific URLs to be skipped when running the TLS scan.
	 * 
	 * @param array $urls_to_skip
	 * 
	 * Usage:
	 * add_filter( 'pantheon.tls_checker.skip_urls', function( $urls_to_skip ) {
	 *     $urls_to_skip[] = 'https://pantheon.io';
	 *     return $urls_to_skip;
	 * } );
	 */
	$skip_urls = apply_filters( 'pantheon.tls_checker.skip_urls', [] );
	return array_unique( $skip_urls );
}

/**
 * Get a list of URLs to add to the TLS scan.
 * 
 * @uses pantheon.tls_checker.additional_urls
 * @return array An array of URLs to add to a TLS scan.
 */
function pantheon_tls_checker_get_additional_urls() {
	/**
	 * Allow URLs to be added to a TLS scan explicitly.
	 * 
	 * @param array $urls_to_add
	 * 
	 * Usage:
	 * add_filter( 'pantheon.tls_checker.additional_urls', function( $urls_to_add ) {
	 *     $urls_to_add[] = 'pantheon.io';
	 *     return $urls_to_add;
	 * } );
	 */
	return apply_filters( 'pantheon.tls_checker.additional_urls', [] );
}

/**
 * Run the TLS 1.2/1.3 scan.
 *
 * @param array $urls An array of URLs to scan for TLS compatibility.
 */
function pantheon_tls_checker_scan( $urls ) {
	$results = [
		'passing' => [],
		'failing' => [],
	];

	$skip_urls = pantheon_tls_checker_get_skip_urls();
	$passing_urls = pantheon_tls_checker_get_passing_urls();

	if ( ! empty( $passing_urls ) ) {
		// Merge array of passing URLs with passed URLs and urls to skip for unique urls.
		$skip_urls = array_unique( array_merge( $skip_urls, $passing_urls ) );
	}

	foreach ( $urls as $url ) {
		// Skip URLs that match the skip list.
		if ( in_array( $url, $skip_urls, true ) ) {
			continue;
		}

		// Skip non-HTTP/HTTPS URLs (e.g., gid://, php://).
		$parsed_url = parse_url( $url );
		if ( empty( $parsed_url['scheme'] ) || ! in_array( $parsed_url['scheme'], [ 'http', 'https' ], true ) ) {
			continue;
		}

		// Skip URLs that are not reachable.
		if ( ! pantheon_tls_checker_is_url_reachable( $url ) ) {
			continue;
		}

		// Check TLS compatibility.
		$hostname = parse_url( $url, PHP_URL_HOST );
		$port = parse_url( $url, PHP_URL_PORT ) ?? 443;

		if ( pantheon_tls_checker_check_tls_support( $hostname, $port ) ) {
			$results['passing'][] = $url;
			pantheon_tls_checker_add_passing_url( $url );
			pantheon_tls_checker_remove_failing_url( $url );
		} else {
			$results['failing'][] = $url;
			pantheon_tls_checker_add_failing_url( $url );
			pantheon_tls_checker_remove_passing_url( $url );
		}
	}

	return $results;
}

/**
 * Check TLS 1.2/1.3 compatibility for a given host.
 *
 * @param string $hostname The hostname to scan for TLS 1.2/1.3 compatibility.
 * @param int $port (Optional) The port for the host to scan (defaults to 443).
 */
function pantheon_tls_checker_check_tls_support( $hostname, $port = 443 ) {
	$crypto_methods = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

	// Add TLS 1.3 if available.
	if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT' ) ) {
		$crypto_methods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
	}

	$context = stream_context_create( [
		'ssl' => [
			'crypto_method' => $crypto_methods,
		],
	] );

	$fp = @stream_socket_client( "ssl://$hostname:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context );
	if ( $fp ) {
		fclose( $fp );
		return true;
	}

	return false;
}

/**
 * Extract URLs from PHP files in the given directory.
 *
 * @param string $dir Directory to scan.
 * @return array Unique URLs found in the code.
 */
function pantheon_tls_checker_extract_urls( $dir ) {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) );
	$urls = [];

	foreach ( $files as $file ) {
		if ( $file->getExtension() !== 'php' ) {
			continue;
		}

		$code = file_get_contents( $file->getRealPath() );
		$tokens = token_get_all( $code );

		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && $token[0] === T_CONSTANT_ENCAPSED_STRING ) {
				$string = trim( $token[1], "'\"" );
				if ( filter_var( $string, FILTER_VALIDATE_URL ) ) {
					$urls[] = $string;
				}
			}
		}
	}

	return array_unique( $urls );
}

/**
 * Check if a URL is reachable and follow redirects if necessary.
 *
 * @param string $url URL to check.
 * @return bool True if reachable, false otherwise.
 */
function pantheon_tls_checker_is_url_reachable( $url ) {
	$headers = @get_headers( $url, 1 );
	if ( $headers ) {
		// Handle redirects (302, 301).
		if ( isset( $headers['Location'] ) ) {
			$redirect_url = is_array( $headers['Location'] ) ? end( $headers['Location'] ) : $headers['Location'];
			return pantheon_tls_checker_is_url_reachable( $redirect_url );
		}

		$http_status = substr( $headers[0], 9, 3 );
		// Treat any status below 400 as reachable (e.g., 200, 301, not 500 errors or 404s).
		return (int) $http_status < 400;
	}

	return false;
}
