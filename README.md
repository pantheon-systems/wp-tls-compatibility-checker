# WP TLS Compatibility Checker

[![Unofficial Support](https://img.shields.io/badge/Pantheon-Unofficial_Support-yellow?logo=pantheon&color=FFDC28)](https://docs.pantheon.io/oss-support-levels#unofficial-support)
[![Lint](https://github.com/jazzsequence/wp-tls-compatibility-checker/actions/workflows/lint.yml/badge.svg)](https://github.com/jazzsequence/wp-tls-compatibility-checker/actions/workflows/lint.yml)
![GitHub Release](https://img.shields.io/github/v/release/jazzsequence/wp-tls-compatibility-checker)
![GitHub License](https://img.shields.io/github/license/jazzsequence/wp-tls-compatibility-checker)


A scanner for outgoing HTTP requests in WordPress code to check TLS 1.2/1.3 compatibility. The scanner stores results in the database so they can be fetched via CLI or other commands.

## WP-CLI Commands

The TLS Checker can be run from the command line with WP-CLI.

### `run`

Runs the TLS checker scan across all PHP files in the given directories (defaults to `/mu-plugins`, `/themes` and `/plugins`). You can specify a directory by passing a `--directory` flag, e.g.:

```bash
wp tls-checker run --directory=/path/to/my/directory
```

#### Examples

```bash
wp tls-checker scan
```

```bash
wp tls-checker scan --directory=/private/scripts/quicksilver
```

Or, in a Pantheon environment using Terminus:

```bash
terminus wp -- <site>.<env> tls-checker scan
```

### `report`

Returns a full report of checked URLs and whether they passed or failed the TLS check. Supports multiple formats (table, JSON, CSV, YAML).

#### Examples

```bash
wp tls-checker report
```

```bash
wp tls-checker report --format=json | jq
```

```bash
wp tls-checker report --format=csv
```

Or, in a Pantheon environment using Terminus:

```bash
terminus wp -- <site>.<env> tls-checker report
```

### `reset`

Resets the stored passing and failing URLs so the next scan will re-check all discovered URLs.

#### Examples
```bash
wp tls-checker reset
```

```bash
terminus wp -- <site>.<env> tls-checker reset
```

## How do I know it worked?
If the scan doesn't find anything bad, you should be good to go. If it does, it will list the URLs that it found that weren't compatible. However, if you want to validate that it's working, you can create a new plugin with the following code:

```php
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
```

When you run the command, the URL above should be returned as a host that does NOT support TLS 1.2 or higher.

## TODO
- [ ] Add tests