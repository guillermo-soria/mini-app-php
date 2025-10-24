# Dockerfile for Fly.io deployment (PHP 8.1 with Apache)
FROM php:8.1-apache

# Install PDO and SQLite support
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev zip unzip libsqlite3-dev pkg-config \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Copy app
COPY . /var/www/html/
WORKDIR /var/www/html

# Set Apache DocumentRoot to public/ and adjust Apache config
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/*.conf \
 && sed -ri "s#<Directory /var/www/html>#<Directory /var/www/html/public>#" /etc/apache2/apache2.conf /etc/apache2/sites-available/*.conf || true

# Ensure AllowOverride All so .htaccess works and enable rewrite module
RUN sed -ri "s/AllowOverride None/AllowOverride All/g" /etc/apache2/apache2.conf /etc/apache2/sites-available/*.conf || true \
 && a2enmod rewrite headers || true

# Ensure data directory exists and is writable by www-data
RUN mkdir -p /data /var/www/html/public && chown -R www-data:www-data /var/www/html /data && chmod -R 755 /var/www/html/public

# Copy entrypoint script and make executable
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Environment variable for DB path (can be overridden on Fly)
ENV FAVORITES_DB=/data/favorites.sqlite

# Expose default HTTP port
EXPOSE 80

# Use entrypoint to fix permissions on mounted volumes at runtime, then start Apache
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
