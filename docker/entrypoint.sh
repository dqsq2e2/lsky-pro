#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/app/uploads \
    storage/debugbar \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

if [ ! -e .env ]; then
    if [ ! -L .env ]; then
        ln -s storage/.env .env
    fi
    cp .env.example .env
fi

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ ! -e installed.lock ] && [ ! -L installed.lock ]; then
    ln -s storage/installed.lock installed.lock
fi

if [ ! -e public/i ] && [ ! -L public/i ]; then
    ln -s ../storage/app/uploads public/i
fi

if [ ! -e public/storage ] && [ ! -L public/storage ]; then
    ln -s ../storage/app/public public/storage
fi

chown www-data:www-data .env
chown -R www-data:www-data bootstrap/cache public storage

exec "$@"
