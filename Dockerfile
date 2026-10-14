FROM php:8.3-fpm-bookworm AS php
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libgmp-dev && docker-php-ext-install pdo_pgsql pcntl zip bcmath gmp && pecl install redis && docker-php-ext-enable redis && rm -rf /var/lib/apt/lists/*
# Production PHP settings (display_errors off, etc.)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts \
    && test -f vendor/autoload.php \
    && composer check-platform-reqs --no-dev
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]
