const { exec } = require( 'node:child_process' );

export const activateTheme = ( themeName ) => {
	return new Promise( ( resolve, reject ) => {
		const command = `docker exec --user=www-data ci_runner_php_fpm wp theme activate ${ themeName }`;

		exec( command, ( error, stdout, stderr ) => {
			if ( error ) {
				console.error( `Error executing command: ${ error }` );
				return reject( error );
			}

			console.log( stdout ); resolve( stdout );
		} );
	} );
};
