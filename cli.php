<?php
/**
 * TLS Compatibility Checker WP-CLI Command
 * 
 * @package Pantheon_TLS_Compatibility_Checker
 */

namespace Pantheon\TLSChecker;

use \WP_CLI;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;

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
     *     wp tls-checker run
     *     wp tls-checker run --directory=wp-content/themes
     *
     * @when after_wp_load
     */
    public function run($args, $assoc_args)
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
            $urls = array_merge($urls, $this->extract_urls($directory));
        }

        $urls = array_unique($urls);

        if (empty($urls)) {
            WP_CLI::success("No URLs found in the specified directories.");
            return;
        }

        WP_CLI::log(count($urls) . " unique URLs found. Checking TLS compatibility...");

        // Progress bar
        $progress = \WP_CLI\Utils\make_progress_bar('Checking TLS', count($urls));
        $checked = [];
        $failed = [];

        foreach ($urls as $url) {
            // Ignore gid:// URLs
            if (strpos($url, 'gid://') === 0) {
                $progress->tick();
                continue;
            }
        
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $progress->tick();
                continue;
            }
        
            $parsed_url = parse_url($url);
            $hostname = $parsed_url['host'] ?? null;
            $port = $parsed_url['port'] ?? 443; // Default to port 443
        
            if (!$hostname || isset($checked["$hostname:$port"])) {
                $progress->tick();
                continue;
            }
        
            // Check if URL is reachable
            $reachable = $this->is_url_reachable($url);
            if (!$reachable) {
                $progress->tick();
                continue; // Silently skip unreachable URLs
            }
        
            // Check TLS
            $is_tls_supported = $this->check_tls_support($hostname, $port);
            $checked["$hostname:$port"] = $is_tls_supported;
        
            if (!$is_tls_supported) {
                $failed[] = "$hostname:$port";
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
     * Check if a URL is reachable and follow redirects if necessary.
     *
     * @param string $url URL to check.
     * @return bool True if reachable, false otherwise.
     */
    private function is_url_reachable($url)
    {
        $headers = @get_headers($url, 1);
        if ($headers) {
            // Handle redirects (302, 301)
            if (isset($headers['Location'])) {
                $redirect_url = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
                return $this->is_url_reachable($redirect_url);
            }

            $http_status = substr($headers[0], 9, 3);
            // Treat any status below 500 as reachable (e.g., 200, 301, 403)
            return (int)$http_status < 500;
        }

        return false;
    }

    /**
     * Extract URLs from PHP files in the given directory.
     *
     * @param string $dir Directory to scan.
     * @return array Unique URLs found in the code.
     */
    private function extract_urls($dir)
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
     * Check if a hostname supports TLS 1.2 or higher (up to TLS 1.3).
     *
     * @param string $hostname Hostname to check.
     * @param int $port Optional port to check (default: 443).
     * @return bool True if TLS 1.2 or 1.3 is supported, false otherwise.
     */
    private function check_tls_support($hostname, $port = 443)
    {
        $crypto_methods = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

        // Add TLS 1.3 if available
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $crypto_methods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        // Use a stream context for the TLS handshake
        $stream = stream_context_create([
            'ssl' => ['crypto_method' => $crypto_methods],
        ]);

        // Attempt to open a connection with the specified port
        $fp = @stream_socket_client("ssl://$hostname:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $stream);

        if ($fp) {
            fclose($fp);
            return true; // TLS 1.2 or 1.3 is supported
        }

        return false; // TLS handshake failed
    }
}

// Register the WP-CLI command
WP_CLI::add_command('tls-checker', __NAMESPACE__ . '\\TLS_Checker_Command');
