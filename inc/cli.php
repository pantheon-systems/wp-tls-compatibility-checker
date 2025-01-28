<?php
/**
 * TLS Compatibility Checker WP-CLI Command
 * 
 * @package Pantheon_TLS_Compatibility_Checker
 */

namespace Pantheon\TLSChecker;

use \WP_CLI;

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class TLS_Checker_Command {

    /**
     * Scan WordPress code for outgoing HTTP requests and check TLS compatibility.
     *
     * ## OPTIONS
     *
     * [--directory=<directory>]
     * : Directory to scan. Defaults to wp-content/plugins, wp-content/themes, and wp-content/mu-plugins.
     *
     * ## EXAMPLES
     *
     *     wp tls-checker scan
     *     wp tls-checker scan --directory=wp-content/themes
     *
     * @when after_wp_load
     */
    public function scan($args, $assoc_args)
    {
        // Default directories to scan
        $default_dirs = [
            WP_CONTENT_DIR . '/plugins',
            WP_CONTENT_DIR . '/themes',
            WP_CONTENT_DIR . '/mu-plugins',
        ];
    
        // Use a specified directory or default to plugins, themes, and mu-plugins
        $directories = isset($assoc_args['directory']) ? [$assoc_args['directory']] : $default_dirs;
    
        $urls = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                WP_CLI::warning("The directory '$directory' does not exist. Skipping...");
                continue;
            }
    
            WP_CLI::log("Scanning directory: $directory");
            $urls = array_merge($urls, pantheon_tls_checker_extract_urls($directory));
        }
    
        $urls = array_unique(array_merge($urls, pantheon_tls_checker_get_additional_urls()));
    
        if (empty($urls)) {
            WP_CLI::success("No URLs found in the specified directories.");
            return;
        }
    
        WP_CLI::log(count($urls) . " unique URLs found. Checking TLS compatibility...");
    
        // Apply skip URLs filter
        $skip_urls = pantheon_tls_checker_get_skip_urls();
        $urls = array_diff($urls, $skip_urls);
    
        // Progress bar
        $progress = \WP_CLI\Utils\make_progress_bar('Checking TLS', count($urls));
    
        $checked = [];
        $failed = [];
    
        foreach ($urls as $url) {
            // Ignore unsupported schemes
            $parsed_url = parse_url($url);
            if (empty($parsed_url['scheme']) || !in_array($parsed_url['scheme'], ['http', 'https'])) {
                $progress->tick();
                continue;
            }
    
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $progress->tick();
                continue;
            }
    
            $hostname = $parsed_url['host'] ?? null;
            $port = $parsed_url['port'] ?? 443;
    
            if (!$hostname || isset($checked["$hostname:$port"])) {
                $progress->tick();
                continue;
            }
    
            // Check if URL is reachable
            $reachable = pantheon_tls_checker_is_url_reachable($url);
            if (!$reachable) {
                $progress->tick();
                continue; // Silently skip unreachable URLs
            }
    
            // Check TLS support
            $is_tls_supported = pantheon_tls_checker_check_tls_support($hostname, $port);
            $checked["$hostname:$port"] = $is_tls_supported;
    
            if (!$is_tls_supported) {
                $failed[] = $url;
                pantheon_tls_checker_add_failing_url($url);
                pantheon_tls_checker_remove_passing_url($url);
            } else {
                pantheon_tls_checker_add_passing_url($url);
                pantheon_tls_checker_remove_failing_url($url);
            }
    
            $progress->tick();
        }
    
        $progress->finish();
    
        WP_CLI::success("TLS compatibility check completed.");
        WP_CLI::log("Checked URLs: " . count($checked));
    
        if (!empty($failed)) {
            WP_CLI::log("The following hosts do NOT support TLS 1.2 or higher:");
            foreach ($failed as $hostname) {
                WP_CLI::log("- $hostname");
            }
        } else {
            WP_CLI::success("All hosts support TLS 1.2 or higher.");
        }
    }    

    /**
     * Reset the stored TLS check data.
     * 
     * ## EXAMPLES
     * 
     *     wp tls-checker reset
     * 
     * @when after_wp_load
     */
    public function reset( $args, $assoc_args ) {
        pantheon_tls_checker_reset_urls();
        WP_CLI::success( 'All stored TLS check data has been reset.' );
    }

    /**
     * Display a report of all scanned URLs.
     * 
     * ## EXAMPLES
     * 
     *     wp tls-checker report
     * 
     * @when after_wp_load
     */
    public function report( $args, $assoc_args ) {
        // Extract the format option, defaulting to 'table'
        $format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
    
        // Retrieve URL data
        $passing_urls = pantheon_tls_checker_get_passing_urls();
        $failing_urls = pantheon_tls_checker_get_failing_urls();
        $additional_urls = pantheon_tls_checker_get_additional_urls();
        $skipped_urls = pantheon_tls_checker_get_skip_urls();
    
        // Prepare data for display
        $data = [];
        foreach ( $passing_urls as $url ) {
            $data[] = [ 'status' => 'Passing', 'url' => $url ];
        }
        foreach ( $failing_urls as $url ) {
            $data[] = [ 'status' => 'Failing', 'url' => $url ];
        }
        foreach ( $additional_urls as $url ) {
            $data[] = [ 'status' => 'Additional', 'url' => $url ];
        }
        foreach ( $skipped_urls as $url ) {
            $data[] = [ 'status' => 'Skipped', 'url' => $url ];
        }
    
        // Validate fields and ensure correct format
        $formatter_options = [
            'format' => $format, // Explicitly set the format
            'fields' => [ 'status', 'url' ], // Explicitly define the fields
        ];
    
        // Create the formatter and display the data
        $formatter = new WP_CLI\Formatter( $formatter_options );
        $formatter->display_items( $data );
    }    
}

// Register the WP-CLI commands.
WP_CLI::add_command('tls-checker', __NAMESPACE__ . '\\TLS_Checker_Command');
