<?php

$env = getenv();

$required_envs = [
	'TEST_TYPE',
	'PHP_VERSION',
	'VOLUMES',
	'QIT_DOCKER_NGINX',
	'QIT_DOCKER_REDIS',
];

foreach ( $required_envs as $required_env ) {
	if ( ! isset( $env[ $required_env ] ) ) {
		echo "Missing required env: $required_env\n";
		die( 1 );
	}
}

$default_volumes = [
	// If the extension cache exists, it will be mapped.
	'/ci/all-plugins/product-packages'               => 'ci/all-plugins/product-packages',
	'/ci/cache/wporg-extensions'                     => 'ci/cache/wporg-extensions',
	'/ci/html'                                       => '/var/www/html',
	'/ci/bin/mu-plugins/wp-cli-github-cache.php'     => '/var/www/html/wp-content/mu-plugins/wp-cli-github-cache.php',
	"/ci/cache/test-type/{$env['TEST_TYPE']}/wp-cli" => '/qit/cache/wp-cli',
];

// Required envs
$php_version = $env['PHP_VERSION'] ?? '7.4';
$volumes     = array_merge( json_decode( $env['VOLUMES'], true ) ?? [], $default_volumes );
$with_nginx  = $env['QIT_DOCKER_NGINX'] === 'yes';
$with_cache  = $env['QIT_DOCKER_REDIS'] === 'yes';

$volumes_yml = '';

$github_workspace = rtrim( getenv( 'GITHUB_WORKSPACE' ), '/' ) . '/';

foreach ( $volumes as $local => &$in_container ) {
	// Make sure all volumes are mapped relative to the root of the project.
	$local_path = $github_workspace . ltrim( $local, '/' );

	/*
	 * Pre-create directories so that permissions are mapped correctly in Docker.
	 */
	if ( ! file_exists( $local_path ) ) {
		exec( "mkdir -p $local_path" );
		if ( file_exists( $local_path ) ) {
			echo "Created directory $local_path\n";
		} else {
			echo "Failed to create directory $local_path\n";
		}
	}

	/*
	 * Pre-create volume directories so that permissions are mapped correctly in Docker.
	 */
	if ( stripos( $in_container, '/var/www/html/' ) !== false ) {
		// Eg: [ 'wp-content', 'mu-plugins', 'foo.txt' ]
		$parts = explode( '/', str_replace( '/var/www/html/', '', $in_container ) );

		foreach ( $parts as $p ) {
			if ( stripos( $p, '.' ) === false ) {
				$to_create = "{$github_workspace}ci/html/$p";

				if ( file_exists( $to_create ) ) {
					echo "Skipping directory that already exists $p\n";
					continue;
				}

				exec( "mkdir -p $to_create" );

				if ( file_exists( $to_create ) ) {
					echo "Created directory $to_create\n";
				} else {
					echo "Failed to create directory $to_create\n";
				}
			} else {
				echo "Skipping file $p\n";
			}
		}
	}

	// Make sure all $in_container relative paths are mapped with the same paths as in host.
	if ( substr( $in_container, 0, 1 ) !== '/' ) {
		$in_container = $github_workspace . ltrim( $in_container, '/' );
	}

	// Create the volumes string, following YAML indentation.
	$volumes_yml .= "      - $local_path:$in_container\n";
}

$docker_images = [
	'db'      => 'jbergstroem/mariadb-alpine:10.6.12',
	'php_fpm' => "automattic/qit-runner-php-$php_version-fpm-alpine:latest",
	'nginx'   => 'nginx:stable-alpine3.17-slim',
	'cache'   => 'redis:6.2.11-alpine',
];

$current_file = __FILE__;

$header = <<<YML
##
## This file is generated from $current_file and it's meant to be used on a disposable CI environment.
##
version: '3.8'
YML;

$database_service = <<<YML
  ci_runner_db:
    image: {$docker_images['db']}
    container_name: ci_runner_db
    command:
      --max_allowed_packet=128000000
      --innodb_log_file_size=128000000 
    environment:
      - MYSQL_DATABASE=wordpress
      - MYSQL_USER=admin
      - MYSQL_PASSWORD=password
      - MYSQL_ROOT_PASSWORD=password
    volumes:
      - ./db:/var/lib/mysql
      - $github_workspace/ci/docker/mariadb-alpine/mariadb-dump:/usr/bin/mysqldump
    healthcheck:
      test: "mariadb --host=ci_runner_db --user=root --password=password wordpress --execute 'SELECT 1'"
      interval: 2s
      retries: 10
YML;

$php_fpm_service = <<<YML
  ci_runner_php_fpm:
    image: {$docker_images['php_fpm']} 
    container_name: ci_runner_php_fpm
    user: \${FIXUID:-1000}:\${FIXGID:-1000}
    environment:
      - WP_CLI_CACHE_DIR=/qit/cache/wp-cli
    depends_on:
      ci_runner_db:
        condition: service_healthy
    volumes:
$volumes_yml
YML;

$nginx_service = <<<YML
  ci_runner_nginx:
    image: {$docker_images['nginx']}
    container_name: ci_runner_nginx
    depends_on:
      - ci_runner_php_fpm
    restart: on-failure:1
    ports:
      - "80:80"
    volumes:
      - $github_workspace/ci/docker/nginx:/etc/nginx/conf.d
$volumes_yml
YML;

$cache_service = <<<YML
  ci_runner_cache:
    image: {$docker_images['cache']}
    container_name: ci_runner_cache
    ports:
      - '6379:6379'
YML;


$docker_compose = <<<DOCKER_COMPOSE
$header

services:
$database_service
$php_fpm_service
DOCKER_COMPOSE;

if ( $with_nginx ) {
	$docker_compose .= "\n$nginx_service";
}

if ( $with_cache ) {
	$docker_compose .= "\n$cache_service";
}

file_put_contents( __DIR__ . '/docker-compose.yml', $docker_compose );
file_put_contents( $github_workspace . 'ci/html/phpinfo.php', <<<'PHP'
<?php
echo "\nget_loaded_extensions():\n";
$loaded_extensions = get_loaded_extensions();
sort($loaded_extensions); 
echo json_encode( $loaded_extensions, JSON_PRETTY_PRINT ) . "\n";

$expected_extensions = json_decode($_GET['php_extensions']);

echo "Expecting extensions: {$_GET['php_extensions']} \n";

foreach ($expected_extensions as $ext) {
	if (!in_array($ext, $loaded_extensions)) {
		echo "\nERROR: $ext is not loaded\n";
		exit(1);
	} else {
		echo "\nOK: $ext is loaded\n";
	}
}
PHP
);