#!/usr/bin/env bats

pr_num="${PR_NUMBER:-""}"
terminus_token="${TERMINUS_TOKEN}"
site_id="${SITE_ID:-""}

@test "Debug Terminus output in BATS" {
    terminus auth:login --machine-token="${terminus_token}" | tee debug.log
    cat debug.log
}

@test "Authenticate terminus" {
	run terminus auth:login --machine-token="${terminus_token}
	echo "Output: '$output'"
	echo "Status: '$status'"
	# Fail explicitly if status is empty (command didn't run properly)
	[[ -n "$status" ]] || { echo "Status is empty"; exit 1; }
	[ "$status" -eq 0 ]
}

@test "Activate the plugin" {
	run terminus wp "${site_id}.pr-${pr_num}" -- plugin activate pantheon-tls-compatibility-checker
	echo "Output: $output"
	echo "Site ID: ${site_id}"
	echo "PR number: ${pr_num}"
	echo "Status: $status"  
	[ "$status" -eq 0 ]	
}

@test "Run TLS checker on all default folders" {
	run terminus wp "${site_id}.pr-${pr_num}" -- tls-checker scan
	echo "Output: $output"
	echo "Site ID: ${site_id}"
	echo "PR number: ${pr_num}"
	echo "Status: $status"
	[ "$status" -eq 0 ]
	[[ "$output" == *"tls-v1-1.badssl.com:1011"* ]]	
}

@test "Run TLS data reset" {
  run terminus wp "${site_id}.pr-${pr_num}" -- tls-checker reset
  echo "Output: $output"
  echo "Site ID: ${site_id}"
  echo "PR number: ${pr_num}"
  echo "Status: $status"
  [ "$status" -eq 0 ]  
  [[ "$output" == *"All stored TLS check data has been reset."* ]]
}

@test "Run TLS checker report on empty data" {
  run terminus drush "${site_id}.pr-${pr_num}" -- tls-checker report
  [ "$status" -eq 0 ]  
  [[ "$output" == *"No scan data found."* ]]  
}

@test "Run TLS checker on just custom modules (specified directory)" {
  run terminus wp "${site_id}.pr-${pr_num}" -- tls-checker scan --directory=app/plugins
  echo "Output: $output"
  echo "Site ID: ${site_id}"
  echo "PR number: ${pr_num}"
  echo "Status: $status"
  [ "$status" -eq 0 ]
  [[ "$output" == *"tls-v1-1.badssl.com:1011"* ]]    
}

@test "Run TLS checker report" {
  run terminus drush "${site_id}.pr-${pr_num}" -- tls-checker report
    echo "Output: $output"
    echo "Site ID: ${site_id}"
    echo "PR number: ${pr_num}"
    echo "Status: $status"
  [ "$status" -eq 0 ]
  [[ "$output" == *"tls-v1-1.badssl.com:1011"* ]]	
}

@test "Check if tls_checker_results table exists" {
	run terminus wp "${site_id}.pr-${pr_num}" -- option get tls_checker_passing_urls
	echo "Output: $output"
	echo "Site ID: ${site_id}"
	echo "PR number: ${pr_num}"
	echo "Status: $status"
	[ "$status" -eq 0 ]
  
	run terminus wp "${site_id}.pr-${pr_num}" -- option get tls_checker_failing_urls
	echo "Output: $output"
	echo "Site ID: ${site_id}"
	echo "PR number: ${pr_num}"
	echo "Status: $status"
	[ "$status" -eq 0 ]  
	[[ "$output" == *"tls-v1-1.badssl.com:1011"* ]]
}

@test "Reset the data and confirm the tls_checker_results table does not exist" {
	terminus wp "${site_id}.pr-${pr_num}" -- tls-checker reset
	echo "Output: $output"
	echo "Site ID: ${site_id}"
	echo "PR number: ${pr_num}"
	echo "Status: $status"
	run terminus wp "${site_id}.pr-${pr_num}" -- option get tls_checker_failing_urls
	[ "$status" -eq 1 ]

	run terminus wp "${site_id}.pr-${pr_num}" -- option get tls_checker_passing_urls
	[ "$status" -eq 1 ]
}