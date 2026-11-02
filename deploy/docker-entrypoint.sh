#!/bin/sh
# Runs on every container start: wait for MySQL, apply migrations, cache config, then start Apache.
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is empty. Run: docker compose run --rm app php artisan key:generate --show  and put the result in .env" >&2
  exit 1
fi

mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

if [ "$DB_CONNECTION" = "mysql" ]; then
  echo "Waiting for the database..."
  i=0
  until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }'; do
    i=$((i+1)); [ $i -ge 60 ] && { echo "Database not reachable" >&2; exit 1; }; sleep 2
  done
fi

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
