<?php
/*
 * Individual image caches are not being used for now due to 429 in actions/cache@v3
 */
/*
$docker_php_cache = <<<'YML'
      - name: Restore PHP Image Cache - Step 1
        if: env.QIT_DOCKER_PHP == 'yes'
        id: cache-docker-php
        uses: actions/cache@v3
        with:
          fail-on-cache-miss: false
          path: ci/cache/docker/php-${{ github.event.client_payload.shared_matrix_data.php_version }}
          key: ${{ env.daily-cache-burst }}-cache-docker-php-${{ github.event.client_payload.shared_matrix_data.php_version }}

      - name: Restore PHP Image Cache - Step 2
        if: env.QIT_DOCKER_PHP == 'yes'
        run: docker image load --input ./ci/cache/docker/php-${{ github.event.client_payload.shared_matrix_data.php_version }}/image.tar
YML;

$docker_mysql_cache = <<<'YML'
      - name: Restore MySQL Image Cache - Step 1
        if: env.QIT_DOCKER_MYSQL == 'yes'
        id: cache-docker-mysql
        uses: actions/cache@v3
        with:
          fail-on-cache-miss: false
          path: ci/cache/docker/mysql
          key: ${{ env.daily-cache-burst }}-cache-docker-mysql

      - name: Restore MySQL Image Cache - Step 2
        if: env.QIT_DOCKER_MYSQL == 'yes'
        run: docker image load --input ./ci/cache/docker/mysql/image.tar
YML;

$docker_redis_cache = <<<'YML'
      - name: Restore Redis Image Cache - Step 1
        if: env.QIT_DOCKER_REDIS == 'yes'
        id: cache-docker-redis
        uses: actions/cache@v3
        with:
          fail-on-cache-miss: false
          path: ci/cache/docker/redis
          key: ${{ env.daily-cache-burst }}-cache-docker-redis

      - name: Restore Redis Image Cache - Step 2
        if: env.QIT_DOCKER_REDIS == 'yes'
        run: docker image load --input ./ci/cache/docker/redis/image.tar
YML;

$docker_nginx_cache = <<<'YML'
      - name: Restore Nginx Image Cache - Step 1
        if: env.QIT_DOCKER_NGINX == 'yes'
        id: cache-docker-nginx
        uses: actions/cache@v3
        with:
          fail-on-cache-miss: false
          path: ci/cache/docker/nginx
          key: ${{ env.daily-cache-burst }}-cache-docker-nginx

      - name: Restore Nginx Image Cache - Step 2
        if: env.QIT_DOCKER_NGINX == 'yes'
        run: docker image load --input ./ci/cache/docker/nginx/image.tar
YML;
*/

$docker_bundle_cache = <<<'YML'
      - name: Restore Bundle Docker Image Cache
        id: cache-docker-bundle
        uses: actions/cache@v3
        with:
          fail-on-cache-miss: false
          path: ci/cache/docker/bundle/php-${{ github.event.client_payload.shared_matrix_data.php_version }}
          key: ${{ env.daily-cache-burst }}-cache-docker-bundle-php-${{ github.event.client_payload.shared_matrix_data.php_version }}
          restore-keys: | # Allow to restore from last day cache if current day doesn't exist.
            ${{ env.yesterday-cache-burst }}-cache-docker-bundle-php-${{ github.event.client_payload.shared_matrix_data.php_version }}

      - name: Restore Redis Image from Bundle
        if: env.QIT_DOCKER_REDIS == 'yes'
        run: docker image load --input ./ci/cache/docker/bundle/php-${{ github.event.client_payload.shared_matrix_data.php_version }}/redis/image.tar

      - name: Restore MySQL Image from Bundle
        if: env.QIT_DOCKER_MYSQL == 'yes'
        run: docker image load --input ./ci/cache/docker/bundle/php-${{ github.event.client_payload.shared_matrix_data.php_version }}/mysql/image.tar

      - name: Restore PHP Image from Bundle
        if: env.QIT_DOCKER_PHP == 'yes'
        run: docker image load --input ./ci/cache/docker/bundle/php-${{ github.event.client_payload.shared_matrix_data.php_version }}/php-${{ github.event.client_payload.shared_matrix_data.php_version }}/image.tar

      - name: Restore Nginx Image from Bundle
        if: env.QIT_DOCKER_NGINX == 'yes'
        run: docker image load --input ./ci/cache/docker/bundle/php-${{ github.event.client_payload.shared_matrix_data.php_version }}/nginx/image.tar
YML;

$header = <<<'YML'
      - name: Set dynamic environment variables.
        run: |
          echo "TEST_TYPE_CACHE_DIR=$GITHUB_WORKSPACE/ci/cache/test-type/${{ matrix.test_run.test_type }}" >> $GITHUB_ENV
          echo "WP_CLI_CACHE_DIR=$GITHUB_WORKSPACE/ci/cache/test-type/${{ matrix.test_run.test_type }}/wp-cli" >> $GITHUB_ENV
          echo "PLAYWRIGHT_BROWSERS_PATH=$GITHUB_WORKSPACE/ci/cache/test-type/${{ matrix.test_run.test_type }}/playwright-browsers" >> $GITHUB_ENV
        
      - name: Plugin Under Test - ${{ matrix.test_run.sut_name }}
        run: echo "Starting test for plugin \"${{ matrix.test_run.sut_name }}\""
        
      - name: Checkout code.
        uses: actions/checkout@v3
YML;

$test_type_cache = <<<'YML'
      - name: Restore Test Type Cache (WP CLI Cache (WP zips, plugins zips, etc), Playwright cache if needed, etc)
        id: cache-qit-daily-test-type
        uses: actions/cache@v3
        with:
          fail-on-cache-miss: false
          path: ci/cache/test-type
          key: ${{ env.daily-cache-burst }}-test-type-${{ matrix.test_run.test_type }}-${{ matrix.test_run.environment.woocommerce_version }}-${{ matrix.test_run.environment.wordpress_version }}
          restore-keys: | # Allow to restore from last day cache if current day doesn't exist.
            ${{ env.yesterday-cache-burst }}-test-type-${{ matrix.test_run.test_type }}-${{ matrix.test_run.environment.woocommerce_version }}-${{ matrix.test_run.environment.wordpress_version }}
YML;

$job_env = <<<'YML'
      PHP_EXTENSIONS: ${{ toJSON( matrix.test_run.environment.php_extensions ) }}
      ENABLE_HPOS: ${{ toJSON( matrix.test_run.environment.optional_features.hpos ) }}
      QIT_ENABLE_NEW_PRODUCT_EDITOR: ${{ toJSON( matrix.test_run.environment.optional_features.new_product_editor ) }}
      PHP_VERSION: ${{ github.event.client_payload.shared_matrix_data.php_version }}
      WOOCOMMERCE_VERSION: ${{ matrix.test_run.environment.woocommerce_version }}
      WORDPRESS_VERSION: ${{ matrix.test_run.environment.wordpress_version }}
      SUT_SLUG: ${{ matrix.test_run.sut_slug }}
      TEST_TYPE: ${{ matrix.test_run.test_type }}
      SUT_VERSION: Undefined
YML;

$daily_cache_burst = <<<'YML'
      - name: Daily Cache Burst.
        run: echo "daily-cache-burst=$(date +'%Y-%m-%d')" >> $GITHUB_ENV
        
      - name: Yesterday Cache Burst.
        run: echo "yesterday-cache-burst=$(date -u -d 'yesterday' +'%Y-%m-%d')" >> $GITHUB_ENV
YML;

$sut_version = <<<'YML'
      - name: Get SUT version (Debug)
        if: always()
        run: ls -la $GITHUB_WORKSPACE/ci/plugins && ls -la $GITHUB_WORKSPACE/ci/plugins/${{ matrix.test_run.sut_slug }}
        
      - name: Get SUT version (Debug)
        if: always()
        env:
          VERSION_FINDER_DIR: ${{ github.workspace }}/ci/plugins/${{ matrix.test_run.sut_slug }}
        run: php $GITHUB_WORKSPACE/ci/bin/version-finder.php || true
        
      - name: Get SUT version.
        env:
          VERSION_FINDER_DIR: ${{ github.workspace }}/ci/plugins/${{ matrix.test_run.sut_slug }}
        run: |
          SUT_VERSION=$(php $GITHUB_WORKSPACE/ci/bin/version-finder.php 2>/dev/null || true)
          if [ -n "$SUT_VERSION" ]; then
            echo "SUT_VERSION=$SUT_VERSION" >> $GITHUB_ENV
          fi
YML;

$update_test_status = <<<'YML'
      - name: Notify Manager That Test Has Started
        if: ${{ matrix.test_run.custom_payload.is_mass_test != 'yes' }}
        id: test-in-progress
        working-directory: ci
        env:
          TEST_RUN_ID: ${{ matrix.test_run.test_run_id }}
          CI_SECRET: ${{ secrets.CI_SECRET }}
          CI_STAGING_SECRET: ${{ secrets.CI_STAGING_SECRET }}
          MANAGER_HOST: ${{ github.event.client_payload.shared_matrix_data.manager_host }}
          RESULTS_ENDPOINT: ${{ github.event.client_payload.shared_matrix_data.in_progress_endpoint }}
          TEST_RUN_HASH: ${{ matrix.test_run.custom_payload.hash }}
          WORKFLOW_ID: ${{ github.run_id }}
          CANCELLED: ${{ steps.workflow-cancelled.outputs.cancelled }}
        run: php ./bin/results/test-in-progress-results.php
YML;

$it = new DirectoryIterator( __DIR__ . '/..' );

$modified          = [];
$nothing_to_change = [];

while ( $it->valid() ) {
	if ( ! $it->isFile() || $it->getExtension() !== 'yml' ) {
		$it->next();
		continue;
	}

	$workflow = file_get_contents( $it->getPathname() );

	// Docker.
	$workflow = preg_replace(
		'/DOCKER_CACHE_START.*?DOCKER_CACHE_END/s',
		"DOCKER_CACHE_START\n" . $docker_bundle_cache . "\n      # DOCKER_CACHE_END",
		$workflow
	);

	// Header.
	$workflow = preg_replace(
		'/HEADER_START.*?HEADER_END/s',
		"HEADER_START\n" . $daily_cache_burst . "\n\n" . $header . "\n      # HEADER_END",
		$workflow
	);

	// Env.
	$workflow = preg_replace(
		'/JOB_ENV_START.*?JOB_ENV_END/s',
		"JOB_ENV_START\n" . $job_env . "\n      # JOB_ENV_END",
		$workflow
	);

	// Test Type Cache.
	$workflow = preg_replace(
		'/TEST_TYPE_CACHE_START.*?TEST_TYPE_CACHE_END/s',
		"TEST_TYPE_CACHE_START\n" . $test_type_cache . "\n      # TEST_TYPE_CACHE_END",
		$workflow
	);

	// Daily Cache Burst.
	$workflow = preg_replace(
		'/DAILY_CACHE_BURST_START.*?DAILY_CACHE_BURST_END/s',
		"DAILY_CACHE_BURST_START\n" . $daily_cache_burst . "\n      # DAILY_CACHE_BURST_END",
		$workflow
	);

	// Sut Version.
	$workflow = preg_replace(
		'/SUT_VERSION_START.*?SUT_VERSION_END/s',
		"SUT_VERSION_START\n" . $sut_version . "\n      # SUT_VERSION_END",
		$workflow
	);

	// Updte Manager about test status.
	$workflow = preg_replace(
		'/UPDATE_TEST_STATUS_START.*?UPDATE_TEST_STATUS_END/s',
		"UPDATE_TEST_STATUS_START\n". $update_test_status . "\n      # UPDATE_TEST_STATUS_END",
		$workflow
	);

	if ( file_get_contents( $it->getPathname() ) !== $workflow ) {
		if ( ! file_put_contents( $it->getPathname(), $workflow ) ) {
			echo "Failed to write {$it->getBasename()}\n";
			die( 1 );
		}

		$modified[] = $it->getBasename();
	} else {
		$nothing_to_change[] = $it->getBasename();
	}

	$it->next();
}

if ( ! empty( $modified ) ) {
	echo "=== Modified ===\n\n";
	echo implode( "\n", $modified ) . "\n";
	echo "\n";
}

if ( ! empty( $nothing_to_change ) ) {
	echo "=== Nothing to change ===\n\n";
	echo implode( "\n", $nothing_to_change ) . "\n";
	echo "\n";
}