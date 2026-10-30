#!/bin/sh
set -eu

: "${APP_KEY:?Configure uma APP_KEY persistente para a aplicação.}"

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction

exec "$@"
