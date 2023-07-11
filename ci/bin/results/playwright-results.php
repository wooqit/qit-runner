<?php

require __DIR__ . '/format_json_results.php';

$env = getenv();

$required_envs = [
	'TEST_RUN_ID',
	'CI_SECRET',
	'CI_STAGING_SECRET',
	'MANAGER_HOST',
	'RESULTS_ENDPOINT',
	'TEST_RESULT',
	'GITHUB_WORKSPACE',
	'PARTIAL_PATH',
	'SUT_VERSION',
	'CANCELLED',
];

foreach ( $required_envs as $required_env ) {
	if ( ! isset( $env[ $required_env ] ) ) {
		echo "Missing required env: $required_env\n";
		die( 1 );
	}
}

if ( stripos( $env['MANAGER_HOST'], 'stagingcompatibilitydashboard' ) !== false ) {
	$secret = $env['CI_STAGING_SECRET'];
} else {
	$secret = $env['CI_SECRET'];
}

if ( empty( $secret ) ) {
	echo "Please check that your repo has the CI_SECRET and CI_STAGING_SECRET configured in the Secrets.\n";
	die( 1 );
}

$url = sprintf( 'https://%s/%s?ci_secret=%s', $env['MANAGER_HOST'], $env['RESULTS_ENDPOINT'], $secret );

/*
 * Playwright Results.
 */
$results_file = realpath( __DIR__ . sprintf('/../../%s/test-results.json', $env['PARTIAL_PATH'] ) );

if ( file_exists( $results_file ) ) {
	$results = json_decode( file_get_contents( $results_file ), true );
	$test_result_json = convert_pw_to_puppeteer( $results );
} else {
	/*
	 * The result file might not exist if the test run failed before it
	 * was executed, such as during environment setup.
	 *
	 * Environment setup can fail if the plugin under test generates
	 * a fatal error that prevents it from being activated at all, for instance.
	 */
	$results = [];
	$test_result_json = convert_pw_to_puppeteer([]);
	$test_result_json['summary'] = 'Test failed before it was executed.';
}

// PHP Errors.
$debug_log_file = $env['GITHUB_WORKSPACE'] . '/ci/debug_prepared.log';
$debug_log      = '';

if ( file_exists( $debug_log_file ) ) {
	$debug_log = file_get_contents( $debug_log_file );
	if ( empty( $debug_log ) ) {
		echo "$debug_log_file is empty.";
	} else {
		// Limit debug_log to a max of 8mb.
		if ( strlen( $debug_log ) > 8 * 1024 * 1024 ) {
			$debug_log = substr( $debug_log, 0, 8 * 1024 * 1024 );
		}
	}
} else {
	echo "$debug_log_file does not exist.";
}

$status = $env['TEST_RESULT'] === 'success' ? 'success' : 'failed';

if ( $env['CANCELLED'] == true ) {
	$status = 'cancelled';
}

$data = [
	'test_run_id'      => $env['TEST_RUN_ID'],
	'status'           => $status,
	'test_result_json' => json_encode( $test_result_json ),
	'debug_log'        => $debug_log,
	'aws_allure'       => '',
	'sut_version'      => $env['SUT_VERSION'],
];

// Allure Report.
$aws_presign_data = realpath( __DIR__ . '/presign.json' );

// attaches aws presign data to payload if 'presign.json' exists
if ( file_exists( $aws_presign_data ) ) {
	$data['aws_allure'] = file_get_contents( $aws_presign_data );
}

$data = json_encode( $data );

$curl = curl_init();
curl_setopt_array( $curl, [
	CURLOPT_URL            => $url,
	CURLOPT_POST           => true,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_USERAGENT      => 'curl/7.68.0',
	CURLOPT_HTTPHEADER     => [
		'Content-Type: application/json',
		'Accept: application/json'
	],
	CURLOPT_POSTFIELDS     => $data
] );

echo sprintf( "Sending request to the Manager. URL: %s Body: %s", $url, $data );
$start    = microtime( true );
$response = curl_exec( $curl );
echo sprintf( "Received response in %s seconds. Response: %s\n", number_format( microtime( true ) - $start, 2 ), json_encode( $response ) );

if ( curl_getinfo( $curl, CURLINFO_HTTP_CODE ) !== 200 ) {
	echo 'Did not receive a successful response from the Manager. Response: ' . json_encode( $response ) . PHP_EOL;
	die( 1 );
}

curl_close( $curl );