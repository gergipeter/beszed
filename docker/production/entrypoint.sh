#!/bin/sh
# Production start: migrate, (re)seed the game content, cache config/routes/views, run.
# Unlike the dev entrypoint it never creates the demo parent.
set -eu
cd /app

: "${APP_KEY:?APP_KEY is required - generate one with: docker run --rm dunglas/frankenphp:1-php8.4 php -r \"echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;\"}"

mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
DB="${DB_DATABASE:-/app/storage/app/database.sqlite}"
[ -f "$DB" ] || touch "$DB"

php artisan migrate --force
php artisan db:seed --class=BeszedContentSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# the scheduler (the Sunday weekly report e-mails), next to the web server
php artisan schedule:work >/dev/null 2>&1 &
exec "$@"
