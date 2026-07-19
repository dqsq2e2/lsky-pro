#!/bin/sh
set -eu

web_port=${WEB_PORT:-80}
app_dir=/var/www/html
template_dir=/var/www/lsky

case "$web_port" in
    ''|*[!0-9]*)
        echo "Invalid WEB_PORT: $web_port" >&2
        exit 1
        ;;
esac

if [ "$web_port" -lt 1 ] || [ "$web_port" -gt 65535 ]; then
    echo "WEB_PORT must be between 1 and 65535" >&2
    exit 1
fi

sed "s/__WEB_PORT__/$web_port/g" \
    /etc/apache2/sites-available/000-default.conf.template \
    > /etc/apache2/sites-available/000-default.conf
printf 'Listen %s\n' "$web_port" > /etc/apache2/ports.conf

mkdir -p "$app_dir"

mkdir -p "$app_dir/storage"

if [ -f "$app_dir/.env" ] && [ ! -L "$app_dir/.env" ]; then
    mv -f "$app_dir/.env" "$app_dir/storage/.env"
fi

if [ -f "$app_dir/installed.lock" ] && [ ! -L "$app_dir/installed.lock" ]; then
    mv -f "$app_dir/installed.lock" "$app_dir/storage/installed.lock"
fi

if [ ! -f "$app_dir/public/index.php" ] || \
    ! cmp -s "$template_dir/.lsky-image-version" "$app_dir/.lsky-image-version"; then
    echo "Synchronizing Lsky Pro $LSKY_IMAGE_VERSION in $app_dir..."
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
