<?php

namespace CI_CLI;

class HeaderHandler {
	private array $patterns = [
		'/Theme Name:\s+(.*)/'  => 'Theme Name',
		'/Plugin Name:\s+(.*)/' => 'Plugin Name',
		'/Theme URI:\s+(.*)/'   => 'Theme URI',
		'/Plugin URI:\s+(.*)/'  => 'Plugin URI',
		'/Description:\s+(.*)/' => 'Description',
		'/Author:\s+(.*)/'      => 'Author',
		'/Author URI:\s+(.*)/'  => 'Author URI',
		'/Template:\s+(.*)/'    => 'Template',
		'/Version:\s+(.*)/'     => 'Version',
		'/License:\s+(.*)/'     => 'License',
		'/License URI:\s+(.*)/' => 'License URI',
		'/Tags:\s+(.*)/'        => 'Tags',
		'/Text Domain:\s+(.*)/' => 'Text Domain'
	];

	private function to_snake_case( $string ): string {
		$string = strtolower($string);
		return str_replace( ' ', '_', $string );
	}

	private function find_theme_or_plugin_entry_point( string $directory ) {
		$files = array_merge( glob( $directory . '/*.php' ), glob( $directory . '/*.css' ) );

		foreach ( $files as $file ) {
			$content = file_get_contents( $file );

			$patterns = array_keys( $this->patterns );
			$patterns = [ $patterns[0], $patterns[1] ];

			foreach ( $patterns as $pattern ) {
				preg_match( $pattern, $content, $matches );

				if ( isset( $matches[1] ) ) {
					return basename( $file );
				}
			}
		}

		return null;
	}

	public function fetch_common_header_info( string $file_path ): array {
		$contents    = file_get_contents( $file_path );
		$header_info = [];

		if ( ! $contents ) {
			throw new \Exception( "Cannot read file: {$file_path}" );
		}

		foreach ( $this->patterns as $pattern => $key ) {
			if ( preg_match( $pattern, $contents, $matches ) ) {
				$snake_key = $this->to_snake_case( $key );
				$header_info[ $snake_key ] = isset( $matches[1] ) ? trim( $matches[1] ) : '';
			} else {
				$snake_key = $this->to_snake_case( $key );
				$header_info[ $snake_key ] = '';
			}
		}

		return $header_info;
	}

	public function fetch_single_plugin_header_item( string $file_path, string $header ): string {

		if ( ! file_exists( $file_path ) ) {
			$plugin_directory = dirname( $file_path );
			$entry_point      = $this->find_theme_or_plugin_entry_point( $plugin_directory );

			if ( is_null( $entry_point ) ) {
				throw new \Exception( "Cannot find theme or plugin entry point in {$plugin_directory}" );
			}

			$file_path = $plugin_directory . '/' . $entry_point;
		}

		$contents    = file_get_contents( $file_path );

		if ( ! $contents ) {
			throw new \Exception( "Cannot read file: {$file_path}" );
		}

		$pattern = "/{$header}:\s+(.*)/";

		if ( preg_match( $pattern, $contents, $matches ) ) {
			return isset( $matches[1] ) ? trim( $matches[1] ) : '';
		} else {
			return '';
		}
	}

	public function fetch_single_plugin_header_item_from_contents( string $contents, string $header ): string {
		$pattern = "/{$header}:\s+(.*)/";

		if ( preg_match( $pattern, $contents, $matches ) ) {
			return isset( $matches[1] ) ? trim( $matches[1] ) : '';
		} else {
			return '';
		}
	}
}