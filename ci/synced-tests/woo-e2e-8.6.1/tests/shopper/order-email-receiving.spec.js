const { test, expect } = require( '@playwright/test' );
const { customer, storeDetails } = require( '../../test-data/data' );
const { api } = require( '../../utils' );

let productId, orderId;

const product = {
	name: 'Order email product',
	type: 'simple',
	regular_price: '42.77',
};

const storeName = 'WooCommerce Core E2E Test Suite';

test.describe( 'Shopper Order Email Receiving', () => {
	test.use( { storageState: process.env.ADMINSTATE } );

	test.beforeAll( async () => {
		productId = await api.create.product( product );
		await api.update.enableCashOnDelivery();
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto(
			`wp-admin/tools.php?page=wpml_plugin_log&s=${ encodeURIComponent(
				customer.email
			) }`
		);
		// clear out the email logs before each test
		while (
			await page.locator( '#bulk-action-selector-top' ).isVisible()
		) {
			// In WP 6.3, label intercepts check action. Need to force.
			await page
				.getByLabel( 'Select All' )
				.first()
				.check( { force: true } );
			await page
				.locator( '#bulk-action-selector-top' )
				.selectOption( 'delete' );
			await page.locator( '#doaction' ).click();
		}
	} );

	test.afterAll( async () => {
		await api.deletePost.product( productId );
		if ( orderId ) {
			await api.deletePost.order( orderId );
		}
		await api.update.disableCashOnDelivery();
	} );

	test( 'should receive order email after purchasing an item', async ( {
		page,
	} ) => {
		// ensure that the store's address is in the US
		await api.update.storeDetails( storeDetails.us.store );

		await page.goto( `/shop/?add-to-cart=${ productId }` );
		await page.waitForLoadState( 'networkidle' );

		await page.goto( '/checkout/' );

		await page
			.locator( '#billing_first_name' )
			.fill( customer.billing.us.first_name );
		await page
			.locator( '#billing_last_name' )
			.fill( customer.billing.us.last_name );
		await page
			.locator( '#billing_address_1' )
			.fill( customer.billing.us.address );
		await page.locator( '#billing_city' ).fill( customer.billing.us.city );
		await page
			.locator( '#billing_country' )
			.selectOption( customer.billing.us.country );

		await page
			.locator( '#billing_state' )
			.selectOption( customer.billing.us.state );

		await page
			.locator( '#billing_postcode' )
			.fill( customer.billing.us.zip );
		await page
			.locator( '#billing_phone' )
			.fill( customer.billing.us.phone );
		await page.locator( '#billing_email' ).fill( customer.email );

		await page.locator( 'text=Place order' ).click();

		await expect(
			page.locator( 'li.woocommerce-order-overview__order > strong' )
		).toBeVisible();
		orderId = await page
			.locator( 'li.woocommerce-order-overview__order > strong' )
			.textContent();

		// search to narrow it down to just the messages we want
		await page.goto(
			`wp-admin/tools.php?page=wpml_plugin_log&s=${ encodeURIComponent(
				customer.email
			) }`
		);
		await page.waitForLoadState( 'networkidle' );
		await expect(
			page.locator( 'td.column-receiver >> nth=0' )
		).toContainText( customer.email );
		await expect(
			page.locator( 'td.column-subject >> nth=1' )
		).toContainText( `[${ storeName }]: New order #${ orderId }` );
	} );
} );
