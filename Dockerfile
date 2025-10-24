# Dockerfile for Fly.io deployment (PHP 8.1 with Apache)
FROM php:8.1-apache

# Install PDO and SQLite support
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev zip unzip \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Copy app
COPY . /var/www/html/
WORKDIR /var/www/html

# Ensure data directory exists and is writable by www-data
RUN mkdir -p /data && chown -R www-data:www-data /data

# Environment variable for DB path (can be overridden on Fly)
ENV FAVORITES_DB=/data/favorites.sqlite

# Expose default HTTP port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]

