<?php

$env = getenv();

$required_envs = [
	'GITHUB_WORKSPACE',
	'PHP_VERSION',
	'WP_VERSION',
	'SUT_SLUG',
];

foreach ( $required_envs as $required_env ) {
	if ( ! isset( $env[ $required_env ] ) ) {
		echo "Missing required env: $required_env\n";
		die( 1 );
	}
}

// PHP Errors.
$debug_log_file     = $env['GITHUB_WORKSPACE'] . '/ci/debug.log';
$new_debug_log_file = $env['GITHUB_WORKSPACE'] . '/ci/debug_prepared.log';

if ( file_exists( $debug_log_file ) ) {
	$log_entries = [];
	$debug_log   = new SplFileObject( $debug_log_file, 'r' );

	$current_entry = '';

	while ( $debug_log->valid() ) {
		$line = $debug_log->current();
		$debug_log->next();

		if (
			stripos( $line, 'in phar://' ) !== false // Ignore notices that come from Phar context, which can be triggered by WP CLI.
			|| empty( trim( $line ) ) // Ignore empty lines.
			|| stripos( $line, '/var/www/html/wp-content/mu-plugins/' ) !== false // Ignore notices from test site mu-plugins.
		) {
			continue;
		}

		$error_from_woocommerce_core = stripos( $line, 'in /var/www/html/wp-content/plugins/woocommerce/' ) !== false;
		$is_testing_woocommerce_core = in_array( $env['SUT_SLUG'], [ 'woocommerce', 'wporg-woocommerce' ], true );
		$has_sut_slug_in_error       = stripos( $line, $env['SUT_SLUG'] ) !== false;

		// Ignore errors coming from WooCommerce Core if not testing WooCommerce Core, and the error doesn't reference the SUT slug.
		if ( $error_from_woocommerce_core && ! $is_testing_woocommerce_core && ! $has_sut_slug_in_error ) {
			continue;
		}

		/*
		 * If we are running PHP 8+ on WordPress 6.1 or lower, ignore the following notices.
		 * @link https://core.trac.wordpress.org/ticket/54504
		 */
		if ( version_compare( $env['PHP_VERSION'], '8', '>=' ) && version_compare( $env['WP_VERSION'], '6.2', '<' ) ) {
			if (
				stripos( $line, 'attribute should be used to temporarily suppress the notice in /var/www/html/wp-includes/Requests/Cookie/Jar.php' ) !== false
				|| stripos( $line, 'attribute should be used to temporarily suppress the notice in /var/www/html/wp-includes/Requests/Utility/CaseInsensitiveDictionary.php' ) !== false
				|| ( stripos( $line, 'PHP Deprecated: http_build_query()' ) !== false && stripos( $line, 'wp-includes/Requests/Transport/cURL.php' ) !== false )
			) {
				continue;
			}
		}

		/*
		 * Match the timestamp at the beginning of the line, and capture the rest of the line.
		 * Eg: [13-Mar-2023 15:33:33 UTC] The wc_get_min_max_price_meta_query() function is deprecated since version 3.6.
		 * Becomes: The wc_get_min_max_price_meta_query() function is deprecated since version 3.6.
		 */
		preg_match( '/^\[.*\]\s+(.*)$/', $line, $matches );

		if ( isset( $matches[1] ) ) {
			// We have a timestamp.
			$current_entry = $matches[1];
		} else {
			// There won't be a timestamp if the error entry is multi-line.
			$current_entry = rtrim( $current_entry ) . "\n" . $line;
		}

		/*
		 * Look for a timestamp in the next line. If there is one, we have reached the end of the current entry,
		 * otherwise, keep grouping the lines as a single entry.
		 */
		if ( ! preg_match( '/^\[.*\]\s+(.*)$/', $debug_log->current() ) ) {
			continue;
		}

		$hash = md5( $current_entry );

		// Write log error only once, and count how many times it appeared.
		if ( ! array_key_exists( $hash, $log_entries ) ) {
			$log_entries[ $hash ] = [
				'count'   => 0,
				'message' => $current_entry,
			];

			$current_entry = '';
		}

		$log_entries[ $hash ]['count'] ++;
	}

	// Log the last entry.
	if ( ! empty( $current_entry ) ) {
		$hash = md5( $current_entry );
		if ( ! array_key_exists( $hash, $log_entries ) ) {
			$log_entries[ $hash ] = [
				'count'   => 1,
				'message' => $current_entry,
			];
		} else {
			$log_entries[ $hash ]['count'] ++;
		}
	}

	$written = file_put_contents( $env['GITHUB_WORKSPACE'] . '/ci/debug_prepared.log', json_encode( $log_entries ) );

	if ( ! $written ) {
		echo "Failed to write $new_debug_log_file";
		die( 1 );
	}
} else {
	echo "$debug_log_file does not exist for prepare.";
}