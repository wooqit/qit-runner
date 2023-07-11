<?php

function verify_env_vars( array $required_vars ) {
	$env = getenv();

	foreach ( $required_vars as $required_var ) {
		if ( ! isset( $env[ $required_var ] ) ) {
			echo "Missing required env: $required_var\n";
			die( 1 );
		}
	}
}
