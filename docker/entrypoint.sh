#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# Run artisan as www-data so files it creates (logs, caches) stay writable by Apache.
artisan() {
    if [ "$(id -u)" = "0" ]; then
        runuser -u www-data -- php artisan "$@"
    else
        php artisan "$@"
    fi
}

# Only the web container migrates; scheduler/one-off containers set RUN_MIGRATIONS=0.
if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    artisan migrate --force
fi

artisan config:cache
artisan route:cache

exec "$@"
