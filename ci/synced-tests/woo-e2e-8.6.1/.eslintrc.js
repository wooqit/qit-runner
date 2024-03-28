module.exports = {
	extends: [ 'plugin:playwright/recommended' ],
	rules: {
		'playwright/no-skipped-test': 'off',
		'no-console': 'off',
		'jest/no-test-callback': 'off',
		'jest/no-disabled-tests': 'off',
	},
};
