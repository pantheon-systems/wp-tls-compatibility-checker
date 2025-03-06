#!/bin/bash
set -euo pipefail

# Set up the environment to test against.
readonly terminus_token=${TERMINUS_TOKEN:-""}
readonly commit_msg=${COMMIT_MSG:-""}
readonly upstream_name=${UPSTREAM_NAME:-"wordpress-composer-managed"}
readonly workspace=${WORKSPACE:-""}
readonly site_name=${SITE_NAME:-"WordPress TLS Checker Test Site"}
readonly pr_num=${PR_NUMBER:-""}
readonly target_env=${TARGET_ENV:-""}
readonly site_id=${SITE_ID:-""}
# shellcheck disable=SC2153
readonly php_version=${PHP_VERSION//./} 

# Set some colors.
RED="\033[1;31m"
GREEN="\033[1;32m"
YELLOW="\033[1;33m"
RESET="\033[0m"

function log_into_terminus() {
	if ! terminus whoami; then
		echo -e "${YELLOW}Log into Terminus${RESET}"
		terminus auth:login --machine-token="${terminus_token}" -q
	fi
	terminus art wordpress
}

function create_site() {
	echo ""
	echo -e "${YELLOW}Create ${site_id} if it does not exist.${RESET}"
	if terminus site:info "${site_id}"; then
		echo "Test site already exists, skipping site creation."
	else
		terminus site:create "${site_id}" "${site_name}" "${upstream_name}" --org=5ae1fa30-8cc4-4894-8ca9-d50628dcba17
	fi

	if terminus plan:info "${site_id}" | grep -q "Sandbox"; then
		echo "Site is on a sandbox plan, setting to performance_small."
		terminus plan:set "${site_id}" "plan-performance_small-contract-annual-1"
	fi
}

function clone_site() {
	echo ""
	echo -e "${YELLOW}Clone the site locally${RESET}"
	echo "Setting up git config..."
	git config --global user.email "cms-platform+tls-checker-test@pantheon.io"
	git config --global user.name "Pantheon Test Bot"
	terminus local:clone "${site_id}"
	composer install
	terminus connection:set "${site_id}.dev" git -y
}

function set_multidev() {
	echo ""
	echo -e "${YELLOW}Set the multidev to test on based on the PR number passed in from CI."

	# Check if multidev exists, create if it does not.
	local multidevs
	multidevs="$(terminus multidev:list "${site_id}" --fields=id --format=list)"
	if echo "${multidevs}" | grep -q "pr-${pr_num}"; then
		echo "Multidev environment for PR ${pr_num} already exists."
	else
		echo "Creating multidev environment for PR ${pr_num}."
		terminus multidev:create "${site_id}".dev "pr-${pr_num}"
	fi

	cd ~/pantheon-local-copies/"${site_id}"
	git fetch --all
	if git show-ref --verify --quiet refs/remotes/origin/pr-"${pr_num}"; then
		echo "Branch pr-${pr_num} exists."
		git checkout -B "pr-${pr_num}" --track origin/"pr-${pr_num}"
	else
		echo -e "${RED}Branch pr-${pr_num} could not be found.${RESET}"
		return 1
	fi

	# Setup the WordPress site.
	terminus wp "${site_id}.pr-${pr_num}" -- core install --url="${target_env}-${site_id}.pantheonsite.io" --title="${site_name}" --admin_user=pantheon --admin_email="pantheon-robot@getpantheon.com" --skip-email
}

function update_pantheon_php_version() {
	local yml_file="$HOME/pantheon-local-copies/${site_id}/pantheon.yml"
	local php_version_with_dot="${PHP_VERSION}"  # Ensure version has the period

	# If pantheon.yml doesn't exist, create it with api_version: 1
	if [[ ! -f "$yml_file" ]]; then
		echo -e "${YELLOW}pantheon.yml does not exist. Creating it.${RESET}"
		echo -e "api_version: 1\nphp_version: ${php_version_with_dot}" > "$yml_file"
		return 0
	fi

	# Check if a php_version line exists
	if grep -q "^php_version:" "$yml_file"; then
		echo -e "php_version found in pantheon.yml."
	else
		echo -e "${YELLOW}Adding php_version to pantheon.yml.${RESET}"
		echo "php_version: ${php_version_with_dot}" >> "$yml_file"
	fi
}

function unignore_plugins() {
	cd ~/pantheon-local-copies/"${site_id}"
	local gitignore_file=".gitignore"

	echo ""
	echo -e "${YELLOW}Checking the gitignore...${RESET}"

	if ! grep -qxF "!web/app/plugins/test-bad.php" "$gitignore_file"; then
		echo "Unignoring the test-bad plugin." 
		echo "!web/app/plugins/test-bad.php" >> "$gitignore_file"
	fi

	if ! grep -qxF "!web/app/plugins/wp-tls-compatibility-checker" "$gitignore_file"; then
		echo "Unignoring the TLS Checker plugin."
		echo "!web/app/plugins/wp-tls-compatibility-checker" >> "$gitignore_file"
	fi

	echo ".gitignore set up to unignore required plugins"
}

function copy_bad_plugin() {
	echo -e "${YELLOW}Checking if TLS testing plugin exists...${RESET}"
	if ! terminus wp "${site_id}.pr-${pr_num}" -- plugin list --field=name | grep -q test-bad; then
		cp -r "${workspace}"/.github/fixtures/test-bad.php ~/pantheon-local-copies/"${site_id}"/web/app/plugins
	else
		echo "Test plugin already installed"
	fi
}

function copy_pr_updates() {
	echo "Commit message: ${commit_msg}"
	cd ~/pantheon-local-copies/"${site_id}"/web/app/plugins
	echo -e "${YELLOW}Copying latest changes to TLS Checker and committing to the site.${RESET}"
	mkdir -p wp-tls-compatibility-checker && cd wp-tls-compatibility-checker
	rsync -a --exclude=".git" "${workspace}/" .
	cd ~/pantheon-local-copies/"${site_id}"
	
	# Check if there are changes to commit
	if [[ -n $(git status --porcelain) ]]; then
		git add -A
		git commit -m "Update to latest commit: ${commit_msg}" || true
		git push origin "pr-${pr_num}" || true
		
		# Run workflow:wait only if changes were committed
		terminus workflow:wait "${site_id}.pr-${pr_num}"
	else
		echo "No changes detected. Skipping commit, push, and workflow:wait."
	fi
}

# Run the steps
cd "${workspace}"
log_into_terminus
create_site
clone_site
set_multidev
update_pantheon_php_version
unignore_plugins
copy_bad_plugin
copy_pr_updates
echo -e "${GREEN}Test environment setup complete.${RESET} 🚀"
