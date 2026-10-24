#!/bin/sh
set -e

# Production image only. Caches config, routes, views and events from this
# container's environment, so it must run at start (the build has no .env).
php artisan optimize --quiet

exec "$@"
