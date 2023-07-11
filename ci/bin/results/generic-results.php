<?php

$env = getenv();

$required_envs = [
	'TEST_RUN_ID',
	'CI_SECRET',
	'CI_STAGING_SECRET',
	'MANAGER_HOST',
	'RESULTS_ENDPOINT',
	'TEST_RESULT',
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

$url   = sprintf( 'https://%s/%s?ci_secret=%s', $env['MANAGER_HOST'], $env['RESULTS_ENDPOINT'], $secret );
$status = $env['TEST_RESULT'] === 'success' ? 'success' : 'failed';

if ( $env['CANCELLED'] == true ) {
	$status = 'cancelled';
}

$data = [
	'test_run_id' => $env['TEST_RUN_ID'],
	'status'      => $status,
	'sut_version' => $env['SUT_VERSION'],
];

if ( ! empty( getenv( 'TEST_LOG' ) ) ) {
	$data['test_log'] = getenv( 'TEST_LOG' );
}

if ( ! empty( getenv( 'TEST_RESULT_JSON' ) ) ) {
	$maybe_file = getenv( 'GITHUB_WORKSPACE' ) . '/' . getenv( 'TEST_RESULT_JSON' );
	if ( file_exists( $maybe_file ) ) {
		$data['test_result_json'] = file_get_contents( $maybe_file );
	} else {
		$data['test_result_json'] = getenv( 'TEST_RESULT_JSON' );
	}
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