#!/bin/sh
set -eu

app_dir=/var/www/html
template_dir=/var/www/lsky

mkdir -p "$app_dir"

if [ ! -f "$app_dir/public/index.php" ]; then
    echo "Initializing Lsky Pro in $app_dir..."
    cp -a "$template_dir/." "$app_dir/"
fi

cd "$app_dir"

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

if [ -f .env ] && [ ! -L .env ]; then
    mv -f .env storage/.env
fi

if [ ! -f storage/.env ]; then
    cp .env.example storage/.env
fi

if [ ! -L .env ]; then
    rm -f .env
    ln -s storage/.env .env
fi

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ -f installed.lock ] && [ ! -L installed.lock ]; then
    mv -f installed.lock storage/installed.lock
fi

if [ ! -L installed.lock ]; then
    rm -f installed.lock
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
