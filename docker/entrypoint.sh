#!/bin/sh
set -eu

mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch "$DB_DATABASE"

if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="$(php artisan key:generate --show)"
    export APP_KEY
fi

php artisan migrate --force
php artisan db:seed --force
exec php artisan serve --host=0.0.0.0 --port=8000