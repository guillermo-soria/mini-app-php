#!/bin/sh
set -e

# Ensure data directory exists and is writable by www-data
if [ ! -d "/data" ]; then
  mkdir -p /data
fi
chown -R www-data:www-data /data || true
chmod 775 /data || true

# Ensure logs directory exists and is writable
if [ ! -d "/var/www/html/logs" ]; then
  mkdir -p /var/www/html/logs
fi
chown -R www-data:www-data /var/www/html/logs || true
chmod -R 775 /var/www/html/logs || true

# Ensure public dir permissions
if [ -d "/var/www/html/public" ]; then
  chown -R www-data:www-data /var/www/html/public || true
  chmod -R 755 /var/www/html/public || true
fi

# Ensure DB file exists and has correct ownership
DB_PATH="${FAVORITES_DB:-/data/favorites.sqlite}"
DB_DIR=$(dirname "$DB_PATH")
if [ ! -d "$DB_DIR" ]; then
  mkdir -p "$DB_DIR"
fi
# create the file if it doesn't exist
if [ ! -f "$DB_PATH" ]; then
  touch "$DB_PATH" || true
fi
chown www-data:www-data "$DB_PATH" || true
chmod 664 "$DB_PATH" || true

# Execute the given command (Apache)
exec "$@"

