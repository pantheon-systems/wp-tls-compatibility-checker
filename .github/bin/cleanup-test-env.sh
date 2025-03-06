#!/bin/bash
set -euo pipefail

# Set up the environment to test against.
readonly terminus_token=${TERMINUS_TOKEN:-""}
# shellcheck disable=SC2153
readonly php_version=${PHP_VERSION//./} 
readonly pr_num=${PR_NUMBER:-""}

function get_site_id() {
	echo "test-wp-tls-checker-${php_version}"
}

# shellcheck disable=SC2155
readonly site_id=$(get_site_id)

function log_into_terminus() {
	if ! terminus whoami; then
		echo -e "${YELLOW}Log into Terminus${RESET}"
		terminus auth:login --machine-token="${terminus_token}" -q
	fi
}

function delete_multidev() {
	terminus multidev:delete "${site_id}.pr-${pr_num}" -y

	# Use terminus multidev:list ${site_id} --fields=id,created --format=js to identify multidevs older than 30 days and delete them.
	current_date=$(date +%s)
	threshold_date=$((current_date - 30 * 24 * 60 * 60))

	multidevs=$(terminus multidev:list "${site_id}" --fields=id,created --format=json | jq -c '.[]')

	for multidev in $multidevs; do
		id=$(echo "$multidev" | jq -r '.id')
		created=$(echo "$multidev" | jq -r '.created')

		if (( created < threshold_date )); then
			echo "Deleting multidev ${id} created on $(date -d @"$created")"
			terminus multidev:delete "${site_id}.${id}" -y
		fi
	done
}

log_into_terminus
delete_multidev

echo "Test environment cleaned up. 🧹"