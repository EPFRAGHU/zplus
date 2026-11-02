#!/usr/bin/env bash
# Pull the latest code and update the live app. Run on the VPS from /var/www/zplus:
#   bash deploy/update.sh
set -euo pipefail
cd "$(dirname "$0")/.."

BRANCH="${BRANCH:-claude/manpower-app-foundation}"

php artisan down --retry=30 || true
trap 'php artisan up' EXIT

git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"     # the server never keeps its own code changes; data lives in MySQL and storage/

composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo systemctl reload php8.3-fpm

echo "Updated to $(git log --oneline -1)"
