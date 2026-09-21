#!/usr/bin/env bash
set -e

echo "Starting Docker Entrypoint..."

# Wait for database to be ready
echo "Waiting for database connection..."
until php artisan db:monitor --databases=mysql > /dev/null 2>&1; do
  echo "Database is unavailable - sleeping"
  sleep 2
done

# Check if we should run migrations
echo "Running database migrations..."
php artisan migrate --force

echo "Caching Laravel configuration..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Starting application..."
exec "$@"
