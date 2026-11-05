FROM php:8.4-apache

# Benutzer-ID an den Host anpassen, damit var/ und public/media auf Linux beschreibbar sind
ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libicu-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" exif gd intl opcache pdo_mysql zip \
    && a2enmod rewrite headers expires \
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data \
    && rm -rf /var/lib/apt/lists/*

COPY vhost.conf /etc/apache2/sites-available/000-default.conf
COPY php.ini /usr/local/etc/php/conf.d/redaxo.ini

WORKDIR /var/www/html
