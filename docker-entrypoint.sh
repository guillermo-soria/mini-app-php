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

# Retry logic: sometimes mounted volumes appear with root ownership only after mount is ready.
TRIES=0
MAX_TRIES=6
SLEEP_MS=250000
while [ $TRIES -lt $MAX_TRIES ]; do
  # try to create the DB file if it doesn't exist
  if [ ! -f "$DB_PATH" ]; then
    touch "$DB_PATH" 2>/dev/null || true
  fi
  # attempt to chown the file and directory
  chown -R www-data:www-data "$DB_DIR" 2>/dev/null && chmod 775 "$DB_DIR" 2>/dev/null && chown www-data:www-data "$DB_PATH" 2>/dev/null && chmod 664 "$DB_PATH" 2>/dev/null
  # verify ownership
  OWNER=$(stat -c '%U' "$DB_PATH" 2>/dev/null || echo "")
  if [ "$OWNER" = "www-data" ]; then
    break
  fi
  TRIES=$((TRIES+1))
  usleep $SLEEP_MS
done

# If after retries ownership is not www-data, log a warning but continue
OWNER_FINAL=$(stat -c '%U' "$DB_PATH" 2>/dev/null || echo "")
if [ "$OWNER_FINAL" != "www-data" ]; then
  echo "[warning] DB file $DB_PATH owner is $OWNER_FINAL; attempted to set to www-data" >&2
fi

# Execute the given command (Apache)
exec "$@"
