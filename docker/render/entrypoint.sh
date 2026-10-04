#!/bin/sh
# Render entrypoint: prepares the SQLite DB on the persistent disk, then hands off to supervisor.
# Only touches what's needed to boot; never runs in the dev docker-compose flow.
set -e

# Render's persistent disk mounts over /app/storage empty on first boot, hiding the
# directory skeleton that normally ships via storage/**/.gitignore placeholders.
mkdir -p /app/storage/app/public /app/storage/app/private \
    /app/storage/framework/cache/data /app/storage/framework/sessions \
    /app/storage/framework/testing/disks /app/storage/framework/views \
    /app/storage/logs
chmod -R 755 /app/storage

DB_PATH="${DB_DATABASE:-/app/storage/app/database.sqlite}"
mkdir -p "$(dirname "$DB_PATH")"
if [ ! -f "$DB_PATH" ]; then
    echo "Creating SQLite database at $DB_PATH"
    touch "$DB_PATH"
fi

php artisan migrate --force

# Generate the Piper TTS cache dir etc. if storage was just created fresh on an empty disk.
php artisan storage:link --force 2>/dev/null || true

exec supervisord -c /etc/supervisor/supervisord.conf
