#!/bin/sh
set -eu

mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
# SQLite needs its file; MySQL's database is on the server
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then touch "$DB_DATABASE"; fi

if [ -z "${APP_KEY:-}" ]; then
    if [ -f storage/app/app-key ]; then
        APP_KEY="$(cat storage/app/app-key)"
    else
        APP_KEY="$(php artisan key:generate --show)"
        printf '%s' "$APP_KEY" > storage/app/app-key
    fi
    export APP_KEY
fi

php artisan migrate --force
php artisan db:seed --force
# the scheduler (the Sunday weekly report e-mails), next to the web server
php artisan schedule:work >/dev/null 2>&1 &
exec php artisan serve --host=0.0.0.0 --port=8000