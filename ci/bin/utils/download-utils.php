<?php

function get_sha ( $plugin, $token, $sha_url, $sha_postfields ): string {
	$curl = curl_init();

	$path      = $plugin['slug'] . '/' . $plugin['slug'];
	$postfields = str_replace( 'PATH', $path, $sha_postfields );
	curl_setopt_array( $curl, [
		CURLOPT_URL            => $sha_url,
		CURLOPT_POST           => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_USERAGENT      => 'curl/7.68.0',
		CURLOPT_HTTPHEADER     => [
			'Content-Type: application/json',
			'Accept: application/json',
			"Authorization: bearer $token"
		],
		CURLOPT_POSTFIELDS     => $postfields 
	] );

	$response = curl_exec( $curl );

	if ( curl_getinfo( $curl, CURLINFO_HTTP_CODE ) !== 200 ) {
		throw new RuntimeException( "Could not get WooCommerce extension SHA. Response: $response", 50 );
	}

	curl_close( $curl );

	$response = json_decode( $response, true );

	if ( ! is_array( $response ) || empty( $response["data"]["repository"]["content"]["oid"] ) ) {
		throw new RuntimeException( sprintf( 'Could not get WooCommerce extension SHA. Response: %s', json_encode( $response ) ), 51 );
	}

	return $response["data"]["repository"]["content"]["oid"];
};

function download_woo_plugin ( $plugin, $token, $plugins_dir, $sha_url, $sha_postfields, $woo_download_url, $accept_header ): string {
	$sha = get_sha( $plugin, $token, $sha_url, $sha_postfields );

	$curl = curl_init();
	$url  = "$woo_download_url/$sha";
	curl_setopt_array( $curl, [
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_USERAGENT      => 'curl/7.68.0',
		CURLOPT_HTTPHEADER     => [
			$accept_header,
			"Authorization: bearer $token"
		],
	] );

	$response = curl_exec( $curl );

	if ( curl_getinfo( $curl, CURLINFO_HTTP_CODE ) !== 200 ) {
		throw new RuntimeException( "Could not download WooCommerce extension. Response: $response URL: $url", 50 );
	}

	curl_close( $curl );

	file_put_contents( "$plugins_dir/{$plugin['slug']}.zip", $response );

	return "$plugins_dir/{$plugin['slug']}.zip";
};

function download_github_plugin ( $plugin, $plugins_dir ) {
	$curl = curl_init();
	$url  = $plugin['url'];

	curl_setopt_array( $curl, [
		CURLOPT_URL				=> $url,
		CURLOPT_FOLLOWLOCATION	=> true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_AUTOREFERER		=> true,
	] );

	$response = curl_exec( $curl );

	if ( curl_getinfo( $curl, CURLINFO_HTTP_CODE ) !== 200 ) {
		throw new RuntimeException( "Could not download {$plugin['name']}. Response: $response URL: $url" );
	}

	curl_close( $curl );

	file_put_contents( $plugins_dir . "/{$plugin['slug']}.zip", $response );
}

function download_wp_plugin ( $plugin, $plugins_dir ) {

	/*
	* To download the latest stable version of a plugin from WordPress.org,
	* we can either poke around the undocumented internals of the WordPress Plugin API,
	* leveraging the same logic that WordPress Core uses to check if there are plugin updates,
	* or we can simply crawl the HTML of the plugin page.
	*
	* This approach uses the HTML of the plugin page in WordPress.org to read the Schema,
	* that is available in the header.
	*/
	if ( $plugin['slug'] === 'wporg-woocommerce' ) {
		echo "Normalizing 'wporg-woocommerce' to 'woocommerce' to query wporg.\n";
		$url = "https://wordpress.org/plugins/woocommerce/";
	} else {
		$url = "https://wordpress.org/plugins/{$plugin['slug']}/";
	}
	$html = file_get_contents( $url );

	if ( empty( $html ) ) {
		throw new RuntimeException( "Could not download WordPress.org plugin information from: $url", 141 );
	}

	$dom = new DOMDocument();

	libxml_use_internal_errors( true );

	if ( ! $dom->loadHTML( $html ) ) {
		throw new RuntimeException( sprintf( 'Could not parse WordPress.org plugin information. URL: %s Errors: %s', $url, json_encode( libxml_get_errors() ) ), 142 );
	}

	$plugin_schema = ( new DOMXpath( $dom ) )->query( '//script[@type="application/ld+json"]' );

	if ( empty( $plugin_schema ) ) {
		throw new RuntimeException( sprintf( 'Could not find plugin schema in WordPress.org plugin page. URL: %', $url ), 143 );
	}

	$plugin_schema = json_decode( trim( $plugin_schema->item( 0 )->nodeValue ), true );

	if ( ! is_array( $plugin_schema ) || empty( $plugin_schema ) ) {
		throw new RuntimeException( sprintf( 'Could not parse plugin schema in WordPress.org plugin page. URL: %', $url ), 144 );
	}

	if ( ! array_key_exists( 'downloadUrl', $plugin_schema[0] ) ) {
		throw new RuntimeException( sprintf( 'Could not parse plugin schema in WordPress.org plugin page. Missing: downloadUrl". URL: %s Plugin Schema: %s', $url, var_export( $plugin_schema ) ), 145 );
	}

	file_put_contents( "$plugins_dir/{$plugin['slug']}.zip", file_get_contents( $plugin_schema[0]['downloadUrl'] ) );
}

function download_wp_theme ( $theme, $themes_dir ) {

	$url  = "https://wordpress.org/themes/{$theme['slug']}/";
	$html = file_get_contents( $url );

	if ( empty( $html ) ) {
		throw new RuntimeException( "Could not download WordPress.org theme information from: $url", 141 );
	}

	$dom = new DOMDocument();

	libxml_use_internal_errors( true );

	if ( ! $dom->loadHTML( $html ) ) {
		throw new RuntimeException( sprintf( 'Could not parse WordPress.org theme information. URL: %s Errors: %s', $url, json_encode( libxml_get_errors() ) ), 142 );
	}

	$theme_link  = ( new DOMXpath( $dom ) )->query( "//a[contains(@href,'downloads.wordpress.org/theme/{$theme['slug']}')]" )[0];
	$downloadUrl = $theme_link->getAttribute("href");

	if ( ! filter_var($url, FILTER_VALIDATE_URL) ) {
		throw new RuntimeException(  'Could not parse WordPress.org theme information for valid download URL.' );
	}

	file_put_contents( "$themes_dir/{$theme['slug']}.zip", file_get_contents( $downloadUrl ) );
}

function extract_zip ( $plugin, $dir ): array {
	$zip = new ZipArchive;
	$file = "{$dir}/{$plugin['slug']}.zip";
	if ( $zip->open( $file ) ) {
		$zip->extractTo( $dir );

		echo "Successfully Extracted {$file} \n";

		if ( unlink( $file ) ) {
			echo "Successfully Deleted {$file} \n";
		} else {
			$zip->close();
			throw new RuntimeException( "Could not delete {$file}." );
		}

		$info =  [
			'extracted_name'	=> trim($zip->getNameIndex(0), '/'),
			'file'				=> "{$plugin['slug']}.zip",
			'slug'				=> $plugin['slug'],
		];

		$zip->close();

		return $info;

	} else {
		throw new RuntimeException( "Could not unzip {$file}." );
	}
}
