<?php

function convert_pw_to_puppeteer( array $results ): array {
	if ( array_key_exists( 'config', $results ) && array_key_exists( '_testGroupsCount', $results['config'] ) ) {
		$numTotalSuites = $results['config']['_testGroupsCount'];
	} else if ( array_key_exists( 'suites', $results ) ) {
		$numTotalSuites = count( $results['suites'] );
	}

	$formatted_result = [
		'numFailedTestSuites'  => 0,
		'numPassedTestSuites'  => 0,
		'numPendingTestSuites' => 0,
		'numTotalTestSuites'   => $numTotalSuites ?? 0,
		'numFailedTests'       => 0,
		'numPassedTests'       => 0,
		'numPendingTests'      => 0,
		'numTotalTests'        => 0,
		'testResults'          => [],
		'summary'              => '',
	];

	if ( ! empty( $results['suites'] ) ) {
		foreach ( $results[ 'suites' ] as $suite ) {
			$result = [
				'file'         => $suite[ 'file' ],
				'status'      => 'passed',
				'has_pending' => false,
				'tests'       => []
			];

			foreach ( $suite[ 'suites' ] as $test ) {
				$key                       = $test[ 'title' ];
				$result[ 'tests' ][ $key ] = [];

				foreach ( $test[ 'specs' ] as $spec ) {
					parse_specs( $spec, $result, $formatted_result, $key );
				}

				parse_possible_suite( $test, $result, $formatted_result, $key );
			}

			if ( $result[ 'status' ] === 'failed' ) {
				$formatted_result[ 'numFailedTestSuites' ] = $formatted_result[ 'numFailedTestSuites' ] + 1;
			}

			if ( $result[ 'status' ] === 'passed' ) {
				$formatted_result[ 'numPassedTestSuites' ] = $formatted_result[ 'numPassedTestSuites' ] + 1;
			}

			if ( $result[ 'status' ] === 'flaky' ) {
				$formatted_result[ 'numPassedTestSuites' ] = $formatted_result[ 'numPassedTestSuites' ] + 1;
			}

			if (
				array_key_exists( 'is_pending', $result ) &&
				$result[ 'has_pending' ]
			) {
				$formatted_result[ 'numPendingTestSuites' ] = $formatted_result[ 'numPendingTestSuites' ] + 1;
			}

			$formatted_result[ 'testResults' ][] = $result;
		}
	}

	$formatted_result['summary'] = sprintf(
		'Test Suites: %d skipped, %d failed, %d passed, %d total | Tests: %d skipped, %d failed, %d passed, %d total.',
		$formatted_result['numPendingTestSuites'],
		$formatted_result['numFailedTestSuites'],
		$formatted_result['numPassedTestSuites'],
		$formatted_result['numTotalTestSuites'],
		$formatted_result['numPendingTests'],
		$formatted_result['numFailedTests'],
		$formatted_result['numPassedTests'],
		$formatted_result['numTotalTests']
	);

	return $formatted_result;
}

function get_status( $spec ) {
	if ( array_key_exists( 'status', $spec[ 'tests' ][0] )  ) {
		$status = $spec[ 'tests' ][0]['status'];
	} else {
		$status = end($spec[ 'tests' ][0][ 'results' ] );
	}

	if ( $status === 'skipped' ) {
		$status = 'pending';
	}

	if ( $status === 'expected' ) {
		$status = 'passed';
	}

	if ( $status === 'unexpected' ) {
		$status = 'failed';
	}

	if ( $status === 'flaky' ) {
		$status = 'passed';
	}

	return $status;
}


function parse_specs($spec, &$result, &$formatted_result, $key ) {
	$title  = $spec[ 'title' ];
	$status = get_status( $spec );

	$result[ 'tests' ][ $key ][] = [
		'title'  => $title,
		'status' => $status
	];

	switch ( $status ) {
		case 'failed':
			$result[ 'status' ] = 'failed';
			// increment failed test count
			$formatted_result[ 'numFailedTests' ] = $formatted_result[ 'numFailedTests' ] + 1;
			break;
		case 'passed':
			// increment passed test count
			$formatted_result[ 'numPassedTests' ] = $formatted_result[ 'numPassedTests' ] + 1;
			break;
		case 'skipped':
		case 'pending':
			$result[ 'has_pending' ] = true;
			// increment skipped/pending test count
			$formatted_result[ 'numPendingTests' ] = $formatted_result[ 'numPendingTests' ] + 1;
			break;
		default:
			$result[ 'status' ] = 'failed';
			// increment failed test count
			$formatted_result[ 'numFailedTests' ] = $formatted_result[ 'numFailedTests' ] + 1;
			break;
	}

	$formatted_result[ 'numTotalTests' ] = $formatted_result[ 'numTotalTests' ] + 1;
}

function parse_possible_suite( $suite, &$result, &$formatted_result, $parent_key ) {
	if ( array_key_exists( 'suites', $suite ) ) {
		foreach ( $suite['suites'] as $suite ) {
			$suite_key                       = $parent_key . ' > ' . $suite[ 'title' ];
			$result[ 'tests' ][ $suite_key ] = [];

			foreach ( $suite[ 'specs' ] as $spec ) {
				parse_specs( $spec, $result, $formatted_result, $suite_key );
			}

			parse_possible_suite( $suite, $result, $formatted_result, $suite_key );
		}
	}
}