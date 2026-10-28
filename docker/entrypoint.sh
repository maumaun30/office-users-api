#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# Hosts that generate secrets (e.g. Render's generateValue) give a bare base64
# 256-bit value; Laravel wants it prefixed.
case "$APP_KEY" in
    base64:*) ;;
    *)
        if [ "$(printf %s "$APP_KEY" | base64 -d 2>/dev/null | wc -c)" -eq 32 ]; then
            export APP_KEY="base64:$APP_KEY"
        fi
        ;;
esac

# Listen on $PORT when the host assigns one (Render, Heroku-style platforms).
if [ -n "$PORT" ]; then
    sed -ri "s/^Listen [0-9]+/Listen $PORT/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
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

    # Optional first-boot setup for hosts without a shell (env-driven, idempotent).
    if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
        artisan user:create "$ADMIN_EMAIL" --role=admin --password="$ADMIN_PASSWORD" --if-missing
    fi
    if [ -n "$INVENTORY_SERVICE_TOKEN" ]; then
        artisan service:bootstrap "${INVENTORY_SERVICE_EMAIL:-inventory-service@example.com}" --token-env=INVENTORY_SERVICE_TOKEN
    fi
fi

artisan config:cache
artisan route:cache

exec "$@"
