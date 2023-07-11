<?php

/**
 * CI uses this file to determine which tests to skip when a given extension exists
 * in the PLUGIN_ACTIVATION_STACK. When a test fails in CI against a given extension,
 * we can disable that specific test here until we update our E2E tests accordingly.
 *
 * FORMAT:
 * [
 *        'tag' => [
 *            'extension-slug' => [
 *                'e2e/tests/admin-tasks/payment.spec.js' => [
 *                    'Payment setup task',
 *                ]
 *            ]
 *        ]
 * ]
 */

if ( ! isset( $qit_woocommerce_version, $qit_test_type ) ) {
	throw new Exception( 'This file must be called from update-test-tags.php' );
}

switch ( $qit_test_type ) {
	case 'e2e':
		$data = [
			'skip' => [
				'all'                                         => [
					'tests/admin-marketing/overview.spec.js'         => [
						'Marketing page'
					],
					'tests/shopper/order-email-receiving.spec.js'    => [
						'Shopper Order Email Receiving'
					],
					'tests/smoke-tests/update-woocommerce.spec.js'   => [
						'WooCommerce update',
						'can run the database update',
					],
					'tests/smoke-tests/upload-plugin.spec.js'        => [
						'can upload and activate',
					],
					'tests/merchant/create-variable-product.spec.js' => [
						'Add New Variable Product Page'
					],
					'tests/merchant/create-coupon.spec.js'           => [
						'can create new coupon'
					]
				],
				'woocommerce-payments'                        => [
					'tests/admin-tasks/payment.spec.js' => [
						'Payment setup task'
					]
				],
				'woocommerce-gateway-paypal-express-checkout' => [
					'tests/admin-tasks/payment.spec.js' => [
						'Payment setup task'
					]
				],
			]
		];

		if ( version_compare( $qit_woocommerce_version, '7.8', '>=' ) ) {
			unset( $data['skip']['all']['tests/merchant/create-variable-product.spec.js'] );
		}

		if ( version_compare( $qit_woocommerce_version, '7.6', '<' ) ) {
			unset( $data['skip']['all']['tests/smoke-tests/update-woocommerce.spec.js'] );
			$data['skip']['all']['tests/activate-and-setup/complete-onboarding-wizard.spec.js'] = [
				'can choose not to install any extensions'
			];
		}

		return $data;
		break;
	case 'api':
		return [
			'skip' => [
				'all'                  => [
					'tests/data/data-crud.test.js'                   => [
						'can view all continents',
						'can view continent data'
					],
					'tests/system-status/system-status-crud.test.js' => [
						'can view all system status items'
					]
				],
				'woocommerce-payments' => [
					'tests/settings/settings-crud.test.js' => [
						'can retrieve all products settings'
					],
					'tests/products/products-crud.test.js' => [
						'can add a product variation',
						'can retrieve a product variation',
						'can retrieve all product variations',
						'can update a product variation',
						'can permanently delete a product variation',
						'can batch update product variations',
						'can view a single product',
						'can update a single product',
						'can delete a product',
					]
				],
			]
		];
		break;
	default:
		throw new Exception( 'Unknown test type.' );
}