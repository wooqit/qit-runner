const { devices } = require( '@playwright/test' );
require( 'dotenv' ).config( { path: __dirname + '/.env' } );

const {
	ALLURE_RESULTS_DIR,
	BASE_URL,
	CI,
	DEFAULT_TIMEOUT_OVERRIDE,
	E2E_MAX_FAILURES,
	PLAYWRIGHT_HTML_REPORT,
	REPEAT_EACH,
} = process.env;

const config = {
	timeout: DEFAULT_TIMEOUT_OVERRIDE
		? Number( DEFAULT_TIMEOUT_OVERRIDE )
		: 120 * 1000,
	expect: { timeout: 20 * 1000 },
	outputDir: './e2e/report',
	globalSetup: require.resolve( './global-setup' ),
	globalTeardown: require.resolve( './global-teardown' ),
	testDir: 'tests',
	retries: CI ? 4 : 0,
	repeatEach: REPEAT_EACH ? Number( REPEAT_EACH ) : 1,
	workers: 1,
	reportSlowTests: { max: 5, threshold: 30 * 1000 }, // 30 seconds threshold
	reporter: [
		[ 'list' ],
		[
			'html',
			{
				outputFolder:
					PLAYWRIGHT_HTML_REPORT ??
					'./e2e/playwright-report',
				open: CI ? 'never' : 'always',
			},
		],
		[
			'allure-playwright',
			{
				outputFolder:
					ALLURE_RESULTS_DIR ??
					'./e2e/allure-results',
				detail: true,
				suiteTitle: true,
			},
		],
		[ 'json', { outputFile: './e2e/test-results.json' } ],
		[ 'github' ],
	],
	maxFailures: E2E_MAX_FAILURES ? Number( E2E_MAX_FAILURES ) : 0,
	use: {
		baseURL: BASE_URL ?? 'http://localhost:8086',
		screenshot: { mode: 'only-on-failure', fullPage: true },
		stateDir: 'e2e/storage/',
		trace: 'retain-on-failure',
		video: 'retain-on-failure',
		viewport: { width: 1280, height: 720 },
		actionTimeout: 20 * 1000,
		navigationTimeout: 20 * 1000,
	},
	projects: [
		{
			name: 'Chrome',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
};

module.exports = config;
