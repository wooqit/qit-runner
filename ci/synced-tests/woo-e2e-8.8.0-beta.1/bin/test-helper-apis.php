<?php

/**
 * Plugin Name: Test Helper APIs
 * Description: Utility REST API designed for E2E testing purposes. Allows turning features on or off, and setting option values
 */
function register_helper_api() {
	register_rest_route(
		'e2e-feature-flags',
		'/update',
		array(
			'methods'             => 'POST',
			'callback'            => 'update_feature_flags',
			'permission_callback' => 'is_allowed',
		)
	);

	register_rest_route(
		'e2e-feature-flags',
		'/reset',
		array(
			'methods'             => 'GET',
			'callback'            => 'reset_feature_flags',
			'permission_callback' => 'is_allowed',
		)
	);

	register_rest_route(
		'e2e-options',
		'/update',
		array(
			'methods'             => 'POST',
			'callback'            => 'api_update_option',
			'permission_callback' => 'is_allowed',
		)
	);
}

add_action( 'rest_api_init', 'register_helper_api' );

/**
 * Update feature flags
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function update_feature_flags( WP_REST_Request $request ) {
	$features     = get_option( 'e2e_feature_flags', array() );
	$new_features = json_decode( $request->get_body(), true );

	if ( is_array( $new_features ) ) {
		$features = array_merge( $features, $new_features );
		update_option( 'e2e_feature_flags', $features );
		return new WP_REST_Response( 'Feature flags updated', 200 );
	}

	return new WP_REST_Response( 'Invalid request body', 400 );
}

/**
 * Reset feature flags
 * @return WP_REST_Response
 */
function reset_feature_flags() {
	delete_option( 'e2e_feature_flags' );
	return new WP_REST_Response( 'Feature flags reset', 200 );
}

/**
 * Enable experimental features
 * @param array $features Array of features.
 * @return array
 */
function enable_experimental_features( $features ) {
	$stored_features = get_option( 'e2e_feature_flags', array() );

	// We always enable this for tests at the moment.
	$features['product-variation-management'] = true;

	return array_merge( $features, $stored_features );
}

add_filter( 'woocommerce_admin_get_feature_config', 'enable_experimental_features' );

/**
 * Update a WordPress option.
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function api_update_option( WP_REST_Request $request ) {
	$option_name  = sanitize_text_field( $request['option_name'] );
	$option_value = sanitize_text_field( $request['option_value'] );

	if ( update_option( $option_name, $option_value ) ) {
		return new WP_REST_Response( 'Option updated', 200 );
	}

	return new WP_REST_Response( 'Invalid request body', 400 );
}

/**
 * Check if user is admin
 * @return bool
 */
function is_allowed() {
	return current_user_can( 'manage_options' );
}
