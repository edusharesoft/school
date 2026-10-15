#!/usr/bin/env bash
# Zero-surprise deployment for /var/www/school. Run as the deploy user.
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/school}"
cd "$APP_DIR"

php artisan down --retry=30 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # idempotent permission sync
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
php artisan up
echo "Deployed $(git rev-parse --short HEAD) at $(date '+%F %T %Z')"
