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

## TODO
- [ ] Add an admin page in Tools explaining the scanner
- [ ] Output the data to json (or the database) for both passed and failed urls
- [ ] Add a Site Health component to show an alert if there are any failing outbound requests
- [ ] Add tests, linting, etc.