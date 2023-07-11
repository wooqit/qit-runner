<?php

/*
 * Takes a plugins JSON as input and downloads them:
 *
 * [
 *    {
 *      "host": "wccom",
 *      "name": "AB Testing for WooCommerce",
 *      "slug": "ab-testing-for-woocommerce"
 *    },
 *    {
 *      "host": "wporg",
 *      "name": "Foo",
 *      "slug": "wp-staging/wp-staging.php"
 *    }
 * ]
 */

$env = getenv();

$required_envs = [
	'PLUGINS_JSON',
	'TOKEN',
	'ACCEPT_HEADER',
	'WOO_DOWNLOAD_URL',
	'SHA_URL',
	'SHA_POSTFIELDS',
	// Optional 'SKIP_CACHE',
	// Optional 'SKIP_EXTRACT',
	// Optional 'SKIP_ON_FAILURE',
];

require __DIR__ . '/utils/download-utils.php';

foreach ( $required_envs as $required_env ) {
	if ( ! isset( $env[ $required_env ] ) ) {
		echo "Missing required env: $required_env\n";
		die( 1 );
	}
}

$skip_cache      = false;
$skip_extract    = false;
$skip_on_failure = false;

if ( isset( $env['SKIP_CACHE'] ) && $env['SKIP_CACHE'] ) {
	$skip_cache = true;
}

if ( isset( $env['SKIP_EXTRACT'] ) && $env['SKIP_EXTRACT'] ) {
	$skip_extract = true;
}

if ( isset( $env['SKIP_ON_FAILURE'] ) && $env['SKIP_ON_FAILURE'] ) {
	$skip_on_failure = true;
}

$plugins_json     = json_decode( trim( trim( $env['PLUGINS_JSON'] ), "'" ), true );
$token            = $env['TOKEN'];
$accept_header    = $env['ACCEPT_HEADER'];
$woo_download_url = $env['WOO_DOWNLOAD_URL'];
$sha_url          = $env['SHA_URL'];
$sha_postfields   = $env['SHA_POSTFIELDS'];
$plugins_dir      = __DIR__ . '/../plugins';

if ( ! file_exists( $plugins_dir ) && ! mkdir( $plugins_dir ) ) {
	echo "$plugins_dir does not exist and could not be created.";
	die( 1 );
}

$extract = function ( string $zip_file_path, string $plugin_slug ) use ( $plugins_dir ) {
	echo "Extracting $zip_file_path\n";
	$zip = new ZipArchive;
	if ( $zip->open( $zip_file_path ) ) {

		// Check if this zip has a parent directory matching the plugin slug.
		$found_parent_directory = false;
		$parent_dir             = strtolower( trim( trim( $plugin_slug ), '/' ) . '/' );
		for ( $i = 0; $i < $zip->numFiles; $i ++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$info = $zip->statIndex( $i );

			if ( ! $info ) {
				throw new UnexpectedValueException( 'Cannot read zip index' );
			}
			
			if ( strpos( strtolower( $info['name'] ), $parent_dir ) === 0 ) {
				echo "Found parent directory $parent_dir\n";
				$found_parent_directory = true;
				break;
			}
		}

		// If not, create the parent directory.
		if ( ! $found_parent_directory ) {
			if ( mkdir( $plugins_dir . '/' . $plugin_slug ) ) {
				echo "Created missing parent directory $parent_dir\n";
			} else {
				throw new UnexpectedValueException( 'Cannot create plugin directory' );
			}
		}

		// Extract to correct folder depending on whether the zip has parent directory or not.
		$extract_dir = $found_parent_directory ? $plugins_dir : $plugins_dir . '/' . $plugin_slug;

		echo "Extracting to directory $extract_dir\n";

		$zip->extractTo( $extract_dir );
		$zip->close();
	} else {
		throw new RuntimeException( "Could not unzip $zip_file_path.", 146 );
	}
};

foreach ( $plugins_json as $plugin ) {
	try {
		if ( ! is_array( $plugin ) ) {
			throw new RuntimeException( 'Invalid Entry in Plugins JSON. Not a JSON array.', 120 );
		}

		foreach ( [ 'host', 'slug', 'zip' ] as $required_key ) {
			if ( ! array_key_exists( $required_key, $plugin ) ) {
				throw new RuntimeException( "Invalid Entry in Plugins JSON. Missing Required Key: $required_key", 130 );
			}
		}

		if ( empty( $plugin['slug'] ) ) {
			throw new RuntimeException( 'Invalid Entry in Plugins JSON. Missing Slug.', 140 );
		}

		$zip_file = false;

		/**
		 * Zip Build.
		 */
		if ( ! empty( $plugin['zip'] ) ) {
			echo "Download plugin {$plugin['slug']} from Zip...\n";

			$contents = file_get_contents( $plugin['zip'] );

			if ( $contents === false ) {
				echo "Could not download plugin.\n";
				die( 1 );
			}

			file_put_contents( "$plugins_dir/{$plugin['slug']}.zip", $contents );

			$zip_file = "$plugins_dir/{$plugin['slug']}.zip";
		}

		$cached_file = __DIR__ . "/../all-plugins/product-packages/{$plugin['slug']}/{$plugin['slug']}.zip";

		if ( ! $zip_file && ! $skip_cache && file_exists( $cached_file ) ) {
			echo "All-plugins cache hit for {$plugin['slug']}\n";
			if ( ! $skip_extract ) {
				$extract( $cached_file, $plugin['slug'] );
			}

			return;
		}

		switch ( $plugin['host'] ) {
			case 'wccom':
				if ( empty( $zip_file ) ) {
					$zip_file = download_woo_plugin(
						$plugin,
						$token,
						$plugins_dir,
						$sha_url,
						$sha_postfields,
						$woo_download_url,
						$accept_header
					);
				}

				if ( ! $skip_extract ) {
					$extract( $zip_file, $plugin['slug'] );
				}
				break;
			case 'wporg':
				if ( empty( $zip_file ) ) {
					download_wp_plugin( $plugin, $plugins_dir );
					$zip_file = "$plugins_dir/{$plugin['slug']}.zip";
				}

				if ( ! $skip_extract ) {
					$extract( $zip_file, $plugin['slug'] );
				}
				break;
			default:
				throw new RuntimeException( 'Invalid Entry in Plugins JSON. Invalid Host.', 150 );
		}
	} catch ( Exception $e ) {
		echo $e->getMessage() . PHP_EOL;

		if ( $skip_on_failure ) {
			continue;
		} else {
			die( $e->getCode() );
		}
	}
}