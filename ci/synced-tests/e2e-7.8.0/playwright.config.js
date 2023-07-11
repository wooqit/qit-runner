const { devices } = require( '@playwright/test' );
const {
	ALLURE_RESULTS_DIR,
	BASE_URL,
	CI,
	DEFAULT_TIMEOUT_OVERRIDE,
	E2E_MAX_FAILURES,
	PLAYWRIGHT_HTML_REPORT,
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
	retries: CI ? 2 : 2,
	workers: 1,
	globalTimeout: ( 60 * 1000 ) * 40,
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
	],
	maxFailures: E2E_MAX_FAILURES ? Number( E2E_MAX_FAILURES ) : 0,
	use: {
		baseURL: BASE_URL ?? 'http://localhost:8086',
		screenshot: 'only-on-failure',
		stateDir: 'e2e/storage/',
		trace: 'retain-on-failure',
		video: 'on-first-retry',
		viewport: { width: 1280, height: 720 },
	},
	projects: [
		{
			name: 'Chrome',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
};

module.exports = config;
