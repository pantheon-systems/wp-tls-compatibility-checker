# WP TLS Compatibility Checker
A scanner for outgoing HTTP requests in WordPress code to check TLS 1.2/1.3 compatibility.

## Usage

From a command line, use WP-CLI to run the scanner.

```bash
wp tls-checker run
```

Or, in a Pantheon environment using Terminus:

```bash
terminus wp -- <site>.<env> tls-checker run
```

The scanner will automatically scan the `/mu-plugins`, `/themes` and `/plugins` directories for any outbound HTTP requests. You can specify a directory by passing a `--directory` flag, e.g.:

```bash
wp tls-checker run --directory=/path/to/my/directory
```

The scanner will only check PHP files.

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
- [ ] Add an admin page in Tools explaining the scanner
- [ ] Allow the scan to be run in the admin via admin-ajax
- [ ] Output the data to json (or the database) for both passed and failed urls
- [ ] Add a Site Health component to show an alert if there are any failing outbound requests
- [ ] Add tests, linting, etc.