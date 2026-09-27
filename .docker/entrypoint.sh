#!/usr/bin/env bash
# Runs before the container's command (php-fpm for `app`, `queue:work` for `worker`).
#   RUN_MIGRATIONS=true  migrate the database (set on ONE container only, so two never migrate at once)
#   DB_WAIT_TRIES        how many 2-second tries to wait for the database before giving up (default 60)
set -euo pipefail

READY_FILE=/tmp/ready   # the compose healthcheck of `app` looks for it: the worker starts once migrations are done
log() { echo "[entrypoint] $*"; }

rm -f "$READY_FILE"

log "waiting for the database..."
tries=0
until out=$(php artisan db:monitor --databases="${DB_CONNECTION:-mysql}" 2>&1); do
  tries=$((tries + 1))
  if [ "$tries" -ge "${DB_WAIT_TRIES:-60}" ]; then
    log "the database is still unavailable after $tries tries, giving up. Last output:" >&2
    echo "$out" >&2
    exit 1
  fi
  sleep 2
done

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  log "running migrations..."
  php artisan migrate --force
fi

# Cached config / routes / views are for a standalone production image only. In dev the project is mounted over the image,
# so these files would be written into the host's bootstrap/cache and storage/framework/views — and a cached config would
# then win over the host's own .env there.
if [ "${APP_ENV:-production}" = "production" ]; then
  log "caching config, routes and views..."
  php artisan config:cache || log "config:cache failed, continuing" >&2
  php artisan route:cache || log "route:cache failed, continuing" >&2
  php artisan view:cache || log "view:cache failed, continuing" >&2
fi

touch "$READY_FILE"
log "starting: $*"
exec "$@"
