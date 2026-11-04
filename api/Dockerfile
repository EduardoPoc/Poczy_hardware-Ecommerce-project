# syntax=docker/dockerfile:1
FROM php:8.5-fpm-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

FROM base AS build
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

FROM php:8.5-fpm-bookworm AS production
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu72 libzip4 \
    && rm -rf /var/lib/apt/lists/*
COPY --from=base /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=base /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
WORKDIR /var/www/html
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
COPY --from=build --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=build --chown=www-data:www-data /var/www/html/app ./app
COPY --from=build --chown=www-data:www-data /var/www/html/bootstrap ./bootstrap
COPY --from=build --chown=www-data:www-data /var/www/html/config ./config
COPY --from=build --chown=www-data:www-data /var/www/html/database ./database
COPY --from=build --chown=www-data:www-data /var/www/html/routes ./routes
COPY --from=build --chown=www-data:www-data /var/www/html/public ./public
COPY --from=build --chown=www-data:www-data /var/www/html/artisan /var/www/html/composer.json /var/www/html/composer.lock ./
COPY --chmod=755 <<'ENTRYPOINT_SCRIPT' /usr/local/bin/api-entrypoint
#!/bin/sh
set -eu

: "${APP_KEY:?Configure uma APP_KEY persistente para a aplicação.}"

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction

exec "$@"
ENTRYPOINT_SCRIPT
COPY php-production.ini /usr/local/etc/php/conf.d/production.ini
RUN mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
ENTRYPOINT ["api-entrypoint"]
CMD ["php-fpm"]

FROM nginx:stable-alpine AS web
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public
