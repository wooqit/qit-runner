<?php

echo "(Maybe Retry E2E) Start...\n";

/*
 * Exit status code:
 * - 0 (Retry test)
 * - Anything else (Do not retry test)
 */

$test_results_json = json_decode( file_get_contents( __DIR__ . './../e2e/test-results.json' ), true );

// Could not parse JSON
if ( empty( $test_results_json ) ) {
	echo "(Maybe Retry E2E) Could not parse JSON file.\n";
	die( 1 );
}

// Count failures.
$failed = 0;
foreach ( $test_results_json['suites'] as $suite ) {
	foreach ( $suite['suites'] as $test ) {
		foreach ( $test['specs'] as $spec ) {
			foreach ( $spec['tests'] as $single_test ) {
				if ( empty( $single_test['results'] ) ) {
					continue;
				}
				$final_try = end( $single_test['results'] );
				if ( in_array( $final_try['status'], [ 'failed', 'timedOut' ] ) ) {
					$failed ++;
				}
			}
		}
	}
}

echo "(Maybe Retry E2E) Failed tests: $failed\n";

if ( $failed > 5 ) {
	echo sprintf( "(Maybe Retry E2E) Skipping retry because there were too many failures. Failures: %d Max Allowed Failures: %d\n", $failed, 5 );
	die( 1 ); // Too many failures.
}

if ( is_array( $test_results_json['errors'] ) && ! empty( $test_results_json['errors'] ) ) {
	foreach ( $test_results_json['errors'] as $error ) {
		// Timed out.
		if ( stripos( $error['message'], 'timed out waiting' ) !== false ) {
			echo sprintf( "(Maybe Retry E2E) Skipping retry because the test timed out. Time out message: %s\n", $error['message'] );
			die( 2 );
		}
	}
}
