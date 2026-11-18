
FROM php:8.3-apache

# Install SQLite support and enable Apache rewrite
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Set Apache document directory
WORKDIR /var/www/html

# Copy the complete ChatConnected source code
COPY . /var/www/html/

# Prepare writable directories
RUN mkdir -p /var/lib/chatconnect /var/www/html/uploads \
    && chown -R www-data:www-data /var/lib/chatconnect \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80
