# syntax=docker/dockerfile:1

# ---- Dependencies ----
FROM composer:2 AS composer-stage

WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

# ---- Runtime ----
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        git \
        icu-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        zip \
    && docker-php-ext-enable opcache

COPY backend /var/www/html
COPY --from=composer-stage /app/vendor /var/www/html/vendor
COPY docker/laravel/php.ini /usr/local/etc/php/conf.d/medexplain.ini
COPY docker/laravel/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p /run/php \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]