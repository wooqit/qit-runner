#!/bin/bash

# Exits immediately if it encounters an error.
set -e

# Variables
EXTRA_COMMANDS="${1}"
DBNAME="wordpress"
DBUSER="admin"
DBPASS="password"
DBHOST="ci_runner_db"
SITEURL="http://qit-runner.test"
SITETITLE="Test Site"
ADMIN_USER="admin"
ADMIN_EMAIL="admin@woocommercecoree2etestsuite.com"
ADMIN_PASSWORD="password"
SKIP_ACTIVATING_WOOCOMMERCE="${SKIP_ACTIVATING_WOOCOMMERCE:-no}"

# Require the environment variable
: ${PHP_EXTENSIONS:?Please set the PHP_EXTENSIONS environment variable}
: ${WORDPRESS_VERSION:?Please set the WORDPRESS_VERSION environment variable}
: ${WOOCOMMERCE_VERSION:?Please set the WOOCOMMERCE_VERSION environment variable}

# Feature Flags
ENABLE_HPOS="${ENABLE_HPOS:-0}"
QIT_ENABLE_NEW_PRODUCT_EDITOR="${QIT_ENABLE_NEW_PRODUCT_EDITOR:-0}"
ENABLE_TRACKING="${ENABLE_TRACKING:-0}"

# Function to abstract docker exec
docker_wp() {
  docker exec --user=www-data ci_runner_php_fpm bash -c "set -e; ${*}"
}

# Download and configure WordPress
docker_wp "
wp core download --version=\"$WORDPRESS_VERSION\" --force --path=/var/www/html &&
wp core config --dbname=\"$DBNAME\" --dbuser=\"$DBUSER\" --dbpass=\"$DBPASS\" --dbhost=\"$DBHOST\" --path=/var/www/html &&
wp core install --url=\"$SITEURL\" --title=\"$SITETITLE\" --admin_user=\"$ADMIN_USER\" --admin_email=\"$ADMIN_EMAIL\" --admin_password=\"$ADMIN_PASSWORD\" &&
wp config set WP_DEBUG true --raw &&
wp config set WP_DEBUG_LOG true --raw &&
wp config set WP_DEBUG_DISPLAY false --raw &&
wp config set WP_DISABLE_FATAL_ERROR_HANDLER true --raw &&
wp config set DISABLE_WP_CRON true --raw &&
wp config set WP_AUTO_UPDATE_CORE false --raw &&
wp config set WP_MEMORY_LIMIT 256M &&
wp plugin delete akismet hello &&
wp plugin activate --all &&
wp rewrite structure '/%postname%/' &&
wp option update blogname 'WooCommerce Core E2E Test Suite' &&
wp user create customer customer@woocommercecoree2etestsuite.com \
    --user_pass=password \
    --user_registered='2022-01-01 12:23:45' \
    --role=subscriber \
    --first_name=Jane \
    --last_name=Smith
"

# Install and activate WooCommerce.
echo "WOOCOMMERCE_VERSION = $WOOCOMMERCE_VERSION"

docker_wp "wp plugin install https://github.com/woocommerce/woocommerce/releases/download/$WOOCOMMERCE_VERSION/woocommerce.zip"

# Activate WooCommerce as needed.
if [ "$SKIP_ACTIVATING_WOOCOMMERCE" != "yes" ]; then
  docker_wp "wp plugin activate woocommerce"
fi

# Execute additional docker_wp commands
# Example usage: EXTRA_COMMANDS="wp plugin install some-plugin --activate && wp theme install some-theme --activate"
if [[ -n "${EXTRA_COMMANDS}" ]]; then
  docker_wp "${EXTRA_COMMANDS}"
fi

# Download all extra plugins so that they are available in cache.
docker_wp wp plugin install https://gist.github.com/MrJnrman/76dd14ed556b96309d229a5b8ea3cc97/archive/c61e9aa13708c756dd16f18f88de1d32282357a3.zip # HPOS
docker_wp wp plugin install https://github.com/woocommerce/woocommerce-experimental-enable-new-product-editor/releases/download/0.1.0/woocommerce-experimental-enable-new-product-editor.zip # New Product Editor.

# High-performance order storage feature.
echo "ENABLE_HPOS = $ENABLE_HPOS"
if [[ $ENABLE_HPOS == 1 ]]; then  
  echo 'Enable the COT feature'
  docker_wp wp plugin install https://gist.github.com/MrJnrman/76dd14ed556b96309d229a5b8ea3cc97/archive/c61e9aa13708c756dd16f18f88de1d32282357a3.zip --force --activate # HPOS

fi
# Todo: Wire up the new product editor feature in the Manager as an optional feature.
echo "QIT_ENABLE_NEW_PRODUCT_EDITOR = $QIT_ENABLE_NEW_PRODUCT_EDITOR"
if [ $QIT_ENABLE_NEW_PRODUCT_EDITOR == 1 ]; then  
	echo 'Enable the new product editor feature'
  docker_wp wp plugin install https://github.com/woocommerce/woocommerce-experimental-enable-new-product-editor/releases/download/0.1.0/woocommerce-experimental-enable-new-product-editor.zip --force --activate # New Product Editor.
fi

# Todo: Wire up enable tracking in the Manager as an optional feature (?)
echo "ENABLE_TRACKING = $ENABLE_TRACKING"
if [ $ENABLE_TRACKING == 1 ]; then
	echo 'Enable tracking'
	docker_wp option update woocommerce_allow_tracking 'yes'
fi

# Install additional PHP Extensions, if any.
echo "PHP_EXTENSIONS = $PHP_EXTENSIONS"
for extension in $(echo "${PHP_EXTENSIONS}" | jq -r '.[]'); do
  echo "Installing PHP extension: ${extension}"
    # Check if the extension is "soap"
    if [[ "${extension}" == "soap" ]]; then
      docker exec --user=root ci_runner_php_fpm apk update
      docker exec --user=root ci_runner_php_fpm apk add --no-cache libxml2-dev
    fi
  docker exec --user=root ci_runner_php_fpm docker-php-ext-install "${extension}"
done

# Restart PHP-FPM if at least one extension was installed.
if [[ -n "${PHP_EXTENSIONS}" ]]; then
  docker exec --user=root ci_runner_php_fpm kill -USR2 1
fi