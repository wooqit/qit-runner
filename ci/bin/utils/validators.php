<?php

function dir_is_empty( $dir ): bool {
	$handle = opendir( $dir );
	while ( false !== ( $entry = readdir( $handle ) ) ) {
		if ( $entry != "." && $entry != ".." ) {
			closedir( $handle );
			return false;
		}
	}
	closedir( $handle );
	return true;
}