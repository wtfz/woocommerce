<?php
/**
 * Script to automatically enforce the release code freeze.
 *
 * @package WooCommerce/GithubActions
 */

require_once __DIR__ . '/post-request-shared.php';

$now = time();
if ( getenv( 'TIME_OVERRIDE' ) ) {
	$now = strtotime( getenv( 'TIME_OVERRIDE' ) );
}

// Code freeze comes 26 days prior to release day.
$release_time         = strtotime( '+26 days', $now );
$release_day_of_week  = date( 'l', $release_time );
$release_day_of_month = (int) date( 'j', $release_time );

// If 26 days from now isn't the second Tuesday, then it's not code freeze day.
if ( 'Tuesday' !== $release_day_of_week || $release_day_of_month < 8 || $release_day_of_month > 14 ) {
	echo 'Info: Today is not the Thursday of the code freeze.' . PHP_EOL;
	return;
}

$latest_version_with_release = get_latest_version_with_release();

if ( empty( $latest_version_with_release ) ) {
	echo '*** Error: Unable to get latest version with release' . PHP_EOL;
	return;
}

// Because we go from 5.9 to 6.0, we can get the next major_minor by adding 0.1 and formatting appropriately.
$latest_float          = (float) $latest_version_with_release;
$branch_major_minor    = number_format( $latest_float + 0.1, 1 );
$milestone_major_minor = number_format( $latest_float + 0.2, 1 );

// We use those values to get the release branch and next milestones that we need to create.
$release_branch_to_create = "release/{$branch_major_minor}";
$milestone_to_create      = "{$milestone_major_minor}.0";

if ( getenv( 'GITHUB_OUTPUTS' ) ) {
	echo '::set-output name=release_version::' . $branch_major_minor . PHP_EOL;
	echo '::set-output name=branch::' . $release_branch_to_create . PHP_EOL;
	echo '::set-output name=milestone::' . $milestone_to_create . PHP_EOL;
}

if ( getenv( 'CREATE_CHANGELOG_PR' ) ) {
	$working_dir     = posix_getcwd();
	$woocommerce_dir = __DIR__ . '/../../../plugins/woocommerce';
	$change_dir      = $woocommerce_dir . '/changelog';
	$changelogger    = $woocommerce_dir . '/vendor/bin/changelogger';

	// Read the change files in the change dir prior to running changelogger.
	$change_files = scandir( $change_dir );
	if ( false === $change_files ) {
		echo '*** Error: Unable to read change files' . PHP_EOL;
		return;
	}

	// Filter out hidden files and current/parent directory.
	$change_files = array_filter( $change_files, function( $file ) {
		return substr( $file, 0, 1 ) !== '.';
	} );

	print_r( $change_files );

	chdir( $woocommerce_dir );

	$output      = array();
	$result_code = 0;
	exec( escapeshellcmd( $changelogger ) . ' write -n --use-version ' . escapeshellarg( $branch_major_minor ), $output, $result_code );

	chdir( $working_dir );

	if ( 0 === $result_code ) {

	} else {
		echo '*** Error: Unable to generate changelog file...' . PHP_EOL;
		echo 'Changelogger output: ' . PHP_EOL . implode( PHP_EOL, $output ) . PHP_EOL . PHP_EOL;
		return;
	}

	return;
}

if ( getenv( 'DRY_RUN' ) ) {
	echo 'DRY RUN: Skipping actual creation of release branch and milestone...' . PHP_EOL;
	echo "Release Branch: {$release_branch_to_create}" . PHP_EOL;
	echo "Milestone: {$milestone_to_create}" . PHP_EOL;
	return;
}

if ( create_github_milestone( $milestone_to_create ) ) {
	echo "Created milestone {$milestone_to_create}" . PHP_EOL;
} else if ( '422' === $github_api_response_code ) {
	// The milestone already existed when GitHub returns a 422 status.
	echo "Notice: Unable to create {$milestone_to_create} milestone. Maybe it already exists? Skipping..." . PHP_EOL;
} else {
	echo "*** Error: Unable to create {$milestone_to_create} milestone" . PHP_EOL;
}

if ( create_github_branch_from_branch( 'trunk', $release_branch_to_create ) ) {
	echo "Created branch {$release_branch_to_create}" . PHP_EOL;
} else if ( '422' === $github_api_response_code ) {
	// The release branch already existed when GitHub returns a 422 status.
	echo "Notice: Unable to create {$release_branch_to_create} branch. Maybe it already exists? Skipping..." . PHP_EOL;
} else {
	echo "*** Error: Unable to create {$release_branch_to_create}" . PHP_EOL;
}
