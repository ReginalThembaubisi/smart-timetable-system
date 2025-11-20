#!/bin/bash
set -e

echo "Installing PHP extensions..."
composer install --no-dev --optimize-autoloader

echo "Checking PHP extensions..."
php -m

echo "Checking for PDO MySQL..."
php -r "if (!extension_loaded('pdo_mysql')) { echo 'ERROR: pdo_mysql not loaded\n'; phpinfo(); exit(1); } else { echo 'SUCCESS: pdo_mysql is loaded\n'; }"

