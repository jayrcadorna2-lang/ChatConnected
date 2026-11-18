
FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/lib/chatconnect \
    && chown -R www-data:www-data /var/lib/chatconnect

WORKDIR /var/www/html

EXPOSE 80
