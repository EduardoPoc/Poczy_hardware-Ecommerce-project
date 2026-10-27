FROM php:8.5-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git unzip libicu-dev libzip-dev libsqlite3-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pcntl pdo_mysql pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ARG LOCAL_UID=1000
ARG LOCAL_GID=1000
RUN groupadd --gid "$LOCAL_GID" app \
    && useradd --uid "$LOCAL_UID" --gid app --create-home app \
    && mkdir -p /var/www/html \
    && chown app:app /var/www/html

WORKDIR /var/www/html

COPY --chown=app:app . .
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/api-entrypoint

USER app

RUN composer install --no-interaction --prefer-dist --no-scripts

EXPOSE 8000

ENTRYPOINT ["api-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
