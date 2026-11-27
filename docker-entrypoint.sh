#!/bin/sh
set -e

mkdir -p storage/app \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache 2>/dev/null || true

chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Auto-generate and persist APP_KEY inside the storage volume if omitted in Portainer env vars
if [ -z "${APP_KEY:-}" ]; then
    APP_KEY_FILE="storage/app/.app_key"
    if [ -s "$APP_KEY_FILE" ]; then
        APP_KEY="$(cat "$APP_KEY_FILE")"
    else
        APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
        printf '%s' "$APP_KEY" > "$APP_KEY_FILE"
        chmod 600 "$APP_KEY_FILE" 2>/dev/null || true
    fi
    export APP_KEY
fi

if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "uat" ]; then
    php artisan optimize:clear 2>/dev/null || true
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

if [ "${RUN_SCHEDULER:-true}" = "true" ]; then
    (
        while true; do
            php artisan schedule:work --verbose --no-interaction || true
            sleep 5
        done
    ) &
fi

if command -v frankenphp >/dev/null 2>&1; then
    exec frankenphp run --config /etc/caddy/Caddyfile
elif command -v php-fpm >/dev/null 2>&1; then
    exec php-fpm
else
    exec php artisan serve --host=0.0.0.0 --port=8000
fi
