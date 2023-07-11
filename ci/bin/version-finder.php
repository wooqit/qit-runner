<?php

/*
 * This script scans a given directory for PHP files and tries to find a "Version" header.
 */

$env = getenv();

$required_envs = [
	'VERSION_FINDER_DIR',
];

foreach ( $required_envs as $required_env ) {
	if ( ! isset( $env[ $required_env ] ) ) {
		fwrite( STDERR, "Missing required env: $required_env\n" );
		die( 1 );
	}
}

try {
	$itDir = new DirectoryIterator( $env['VERSION_FINDER_DIR'] );
	while ( $itDir->valid() ) {
		if ( $itDir->isFile() && ! $itDir->isLink() && $itDir->getExtension() === 'php' ) {
			/**
			 * Try to find "Version" on each PHP file in this directory.
			 *
			 * Logic ported from WordPress Core.
			 *
			 * @see get_file_data()
			 * @see get_plugin_data()
			 */
			// Pull the first 8kbs of contents.
			$file_data = file_get_contents( $itDir->getPathname(), false, null, 0, 8 * 1024 );

			$headers = [
				'Version' => 'Version',
			];

			$all_headers = [];

			foreach ( $headers as $field => $regex ) {
				if ( preg_match( '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
					$all_headers[ $field ] = trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) );
				}
			}

			// Header found.
			if ( ! empty( $all_headers['Version'] ) ) {
				echo $all_headers['Version'];
				die( 0 );
			}
		}
		$itDir->next();
	}
} catch ( \Exception $e ) {
	fwrite( STDERR, $e->getMessage() );
	die( 1 );
}

fwrite( STDERR, 'Did not find a Version header in the given directory.' );
die( 1 );