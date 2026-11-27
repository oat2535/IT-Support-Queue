FROM dunglas/frankenphp:1-php8.3

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    ACCEPT_EULA=Y \
    SERVER_NAME=":8000"

RUN set -eux; \
    install-php-extensions pdo_pgsql pgsql pdo_sqlsrv sqlsrv zip intl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'memory_limit = 512M'; \
        echo 'upload_max_filesize = 32M'; \
        echo 'post_max_size = 32M'; \
        echo 'max_execution_time = 120'; \
        echo 'date.timezone = Asia/Bangkok'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.enable_cli = 1'; \
        echo 'opcache.memory_consumption = 128'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'opcache.validate_timestamps = 0'; \
    } > "$PHP_INI_DIR/conf.d/99-queue.ini"

WORKDIR /app

COPY Caddyfile /etc/caddy/Caddyfile

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .

RUN mkdir -p storage/app \
             storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && php artisan package:discover --ansi \
    && sed -i 's/\r$//' docker-entrypoint.sh \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x docker-entrypoint.sh

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -f http://127.0.0.1:8000/up || exit 1

ENTRYPOINT ["./docker-entrypoint.sh"]
