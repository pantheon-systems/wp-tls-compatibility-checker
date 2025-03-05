#!/usr/bin/env bats

pr_num="${PR_NUMBER:-""}"
terminus_token="${TERMINUS_TOKEN}"
php_version=${PHP_VERSION//./}

get_site_id() {
	if [[ $php_version == '83' ]]; then
		echo "test-drupal-cms-tls-checker-83"
	else
		echo "test-drupal-tls-checker-${php_version}"
	fi
}

# shellcheck disable=SC2155
site_id=$(get_site_id)