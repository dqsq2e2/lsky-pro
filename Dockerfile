FROM php:8.2-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1

# hadolint ignore=DL3008
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libmagickwand-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
        unzip; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        dom \
        exif \
        ftp \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        simplexml \
        sockets \
        xml \
        xmlreader \
        xmlwriter \
        zip; \
    pecl install imagick-3.8.1 redis-6.3.0; \
    docker-php-ext-enable imagick redis; \
    a2enmod expires headers rewrite; \
    rm -rf /tmp/pear /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/lsky.ini

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

COPY . .

RUN cp .env.example .env; \
    php artisan key:generate --force --no-interaction; \
    composer install \
        --classmap-authoritative \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist; \
    rm -f .env; \
    mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/app/uploads \
        storage/debugbar \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs; \
    ln -s storage/.env .env; \
    ln -s storage/installed.lock installed.lock; \
    chown -R www-data:www-data bootstrap/cache public storage

COPY docker/entrypoint.sh /usr/local/bin/lsky-entrypoint
RUN chmod +x /usr/local/bin/lsky-entrypoint

EXPOSE 80

ENTRYPOINT ["lsky-entrypoint"]
CMD ["apache2-foreground"]
