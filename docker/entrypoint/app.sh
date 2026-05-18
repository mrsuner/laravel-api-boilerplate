#!/usr/bin/env bash
# Shared container entrypoint.
#
# Runs once per container start, regardless of role (php-fpm, queue worker,
# scheduler). Handles bootstrap that every flavour needs — storage dir
# scaffolding, optional production caches, opt-in migrations — then execs
# whatever CMD the image / compose file supplied.

set -euo pipefail

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "${APP_ENV:-local}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache || true
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
