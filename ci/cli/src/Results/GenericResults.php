<?php

namespace CI_CLI\Results;

use CI_CLI\RequestBuilder;

class GenericResults extends Results{
	protected function get_test_result_json():string{
		$json_file = "{$this->workspace}/{$this->test_result_json}";

		if ( file_exists( $json_file ) ) {
			return file_get_contents( $json_file );
		} else {
			$this->output->writeln( "$json_file was not found. Sending raw test_result_json." );
			return $this->test_result_json;
		}
	}

	public function send_results(): string {
		return ( new RequestBuilder( $this->get_url(), $this->output ) )
			->with_method( 'POST' )
			->with_post_body( [
				'test_run_id'      => $this->test_run_id,
				'sut_version'      => $this->sut_version,
				'status'           => $this->get_test_status(),
				'test_result_json' => $this->get_test_result_json(),
				'ci_secret'        => $this->ci_secret,
			] )
			->with_expected_status_codes( [ 200 ] )
			->with_timeout_in_seconds( 15 )
			->with_headers( [
				'Content-Type: application/json',
				'Accept: application/json'
			] )
			->request();
	}
}