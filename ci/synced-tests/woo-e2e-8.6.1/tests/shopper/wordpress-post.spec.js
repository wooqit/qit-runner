const { test, expect } = require( '@playwright/test' );

test.describe( 'WordPress', async () => {
	test.use( { storageState: process.env.CUSTOMERSTATE } );

	test.beforeEach( async ( { page } ) => {
		await page.goto( 'hello-world/' );
		await expect(
			page.getByRole( 'heading', { name: 'Hello world!' } )
		).toBeVisible();
	} );

	test( 'logged-in customer can comment on a post', async ( { page } ) => {
		const comment = `This is a test comment ${ Date.now() }`;
		await page.getByRole( 'textbox', { name: 'comment' } ).fill( comment );
		await page.getByRole( 'button', { name: 'Post Comment' } ).click();
		await expect( page.getByText( comment ) ).toBeVisible();
	} );
} );
