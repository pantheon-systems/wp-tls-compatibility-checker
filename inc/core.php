<?php
/**
 * TLS Compatibility Checker Public Functions
 * 
 * @package Pantheon_TLS_Compatibility_Checker
 */

function pantheon_tls_checker_delete_options() {
	delete_option( 'tls_checker_passing_urls' );
	delete_option( 'tls_checker_failing_urls' );
}

function pantheon_tls_checker_get_passing_urls() {
	return get_option( 'tls_checker_passing_urls', [] );
}

function pantheon_tls_checker_get_failing_urls() {
	return get_option( 'tls_checker_failing_urls', [] );
}

function pantheon_tls_checker_add_passing_url( $url ) {
	$passing_urls = pantheon_tls_checker_get_passing_urls();
	if ( ! in_array( $url, $passing_urls ) ) {
		$passing_urls[] = $url;
		update_option( 'tls_checker_passing_urls', $passing_urls );
	}
}

function pantheon_tls_checker_add_failing_url( $url ) {
	$failing_urls = pantheon_tls_checker_get_failing_urls();
	if ( ! in_array( $url, $failing_urls ) ) {
		$failing_urls[] = $url;
		update_option( 'tls_checker_failing_urls', $failing_urls );
	}
}

function pantheon_tls_checker_remove_passing_url( $url ) {
	$passing_urls = pantheon_tls_checker_get_passing_urls();
	$index = array_search( $url, $passing_urls );
	if ( $index !== false ) {
		unset( $passing_urls[ $index ] );
		update_option( 'tls_checker_passing_urls', $passing_urls );
	}
}

function pantheon_tls_checker_remove_failing_url( $url ) {
	$failing_urls = pantheon_tls_checker_get_failing_urls();
	$index = array_search( $url, $failing_urls );
	if ( $index !== false ) {
		unset( $failing_urls[ $index ] );
		update_option( 'tls_checker_failing_urls', $failing_urls );
	}
}

function pantheon_tls_checker_reset_urls() {
	update_option( 'tls_checker_passing_urls', [] );
	update_option( 'tls_checker_failing_urls', [] );
}

function pantheon_tls_checker_get_skip_urls() {
    $skip_urls = apply_filters('pantheon.tls_checker.skip_urls', []);
    return array_unique($skip_urls);
}

function pantheon_tls_checker_get_additional_urls() {
	return apply_filters( 'pantheon.tls_checker.additional_urls', [] );
}

function pantheon_tls_checker_scan($urls) {
    $results = [
        'passing' => [],
        'failing' => [],
    ];

	$passing_urls = pantheon_tls_checker_get_passing_urls();

	if ( ! empty( $passing_urls ) ) {
		// Merge array of passing URLs with passed URLs and filter for unique urls.
		apply_filters( 'pantheon.tls_checker.skip_urls', $passing_urls );
	}

    foreach ($urls as $url) {
        // Skip URLs that match the skip list
        if (in_array($url, pantheon_tls_checker_get_skip_urls())) {
            continue;
        }

        // Skip non-HTTP/HTTPS URLs (e.g., gid://, php://)
        $parsed_url = parse_url($url);
        if (empty($parsed_url['scheme']) || !in_array($parsed_url['scheme'], ['http', 'https'])) {
            continue;
        }

        // Check TLS compatibility
        $hostname = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? 443;

        if (pantheon_tls_checker_check_tls_support($hostname, $port)) {
            $results['passing'][] = $url;
            pantheon_tls_checker_add_passing_url($url);
            pantheon_tls_checker_remove_failing_url($url);
        } else {
            $results['failing'][] = $url;
            pantheon_tls_checker_add_failing_url($url);
            pantheon_tls_checker_remove_passing_url($url);
        }
    }

    return $results;
}

function pantheon_tls_checker_check_tls_support( $hostname, $port = 443 ) {
	$crypto_methods = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

	// Add TLS 1.3 if available
	if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT' ) ) {
		$crypto_methods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
	}

	$context = stream_context_create( [
		'ssl' => [
			'crypto_method' => $crypto_methods,
		],
	] );

	$fp = @stream_socket_client( "ssl://$hostname:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context );
	if ( $fp )  {
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
function pantheon_tls_checker_extract_urls($dir)
{
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
	$urls = [];

	foreach ($files as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$code = file_get_contents($file->getRealPath());
		$tokens = token_get_all($code);

		foreach ($tokens as $token) {
			if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
				$string = trim($token[1], "'\"");
				if (filter_var($string, FILTER_VALIDATE_URL)) {
					$urls[] = $string;
				}
			}
		}
	}

	return array_unique($urls);
}

/**
 * Check if a URL is reachable and follow redirects if necessary.
 *
 * @param string $url URL to check.
 * @return bool True if reachable, false otherwise.
 */
function pantheon_tls_checker_is_url_reachable($url)
{
	$headers = @get_headers($url, 1);
	if ($headers) {
		// Handle redirects (302, 301)
		if (isset($headers['Location'])) {
			$redirect_url = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
			return pantheon_tls_checker_is_url_reachable($redirect_url);
		}

		$http_status = substr($headers[0], 9, 3);
		// Treat any status below 500 as reachable (e.g., 200, 301, 403)
		return (int)$http_status < 500;
	}

	return false;
}