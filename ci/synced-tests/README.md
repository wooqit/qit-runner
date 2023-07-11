### Dynamic tests

This directory contains tests that are synced with WooCommerce Core.

We fetch tests from WooCommerce Core and save them here to be used by QIT.

### Updating Dynamic Tests

To update the tests, do the following steps:

- Create a new branch
- Open a terminal in the `ci` folder
- Run `make update_tests`
- Commit the resulting changes and open a PR

Note: if you run into any errors when pushing, you may need to update your git config postBuffer:

```
git config --global http.postBuffer 157286400
```

On the PR, we expect to see the following changes:

- Older tests will be removed
- New tests will be added
- The file `plugins/cd/manager/synced-with-woo.txt` will have changed to reflect that

### Testing the tests

- Spin up the QIT CLI repository locally
- Edit the self test environments to use the new version, eg: `_tests/api/main/env.php` file, change the `woocommerce_version` there.
- Point your local QIT CLI repo to staging with `qit env:switch staging`
- Deploy the Manager with the new tests to Staging
- Run the self-tests with `php QITSelfTests.php update`. Note: This will take 15 to 20 minutes because of E2E tests.
