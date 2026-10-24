#!/bin/sh
# First start of a fresh clone, with only Docker installed: creates the .env
# files with freshly generated secrets that match across the services, builds
# the admin panel's CSS/JS, starts the whole platform and loads the demo data.
# Safe to run again: values already in the .env files and existing data are kept.
set -eu
cd "$(dirname "$0")/.."

random_hex() { head -c "$1" /dev/urandom | od -An -tx1 | tr -d ' \n'; }

value() { sed -n "s/^$2=//p" "$1" | head -n 1; }

# Sets KEY=value in a .env file, only while KEY is still empty.
fill() {
  if grep -q "^$2=$" "$1"; then
    sed "s|^$2=\$|$2=$3|" "$1" > "$1.tmp" && mv "$1.tmp" "$1"
    echo "  $1: generated $2"
  fi
}

# A secret both services must share: reuse the one already set, or generate it.
fill_shared() {
  secret=$(value api-gateway/.env "$1")
  [ -n "$secret" ] || secret=$(value admin-service/.env "$1")
  [ -n "$secret" ] || secret=$(random_hex 32)
  fill api-gateway/.env "$1" "$secret"
  fill admin-service/.env "$1" "$secret"
  if [ "$(value api-gateway/.env "$1")" != "$(value admin-service/.env "$1")" ]; then
    echo "  WARNING: $1 differs between api-gateway/.env and admin-service/.env; it must be the same." >&2
  fi
}

echo "1/4 Configuration"
for file in .env api-gateway/.env admin-service/.env; do
  if [ ! -f "$file" ]; then
    cp "$file.example" "$file"
    echo "  created $file"
  fi
done
fill_shared JWT_SECRET
fill_shared INTERNAL_API_KEY
fill admin-service/.env APP_KEY "base64:$(head -c 32 /dev/urandom | base64)"
fill admin-service/.env REVERB_APP_KEY "$(random_hex 10)"
fill admin-service/.env REVERB_APP_SECRET "$(random_hex 20)"

echo "2/4 Building the admin panel's CSS/JS"
docker compose run --rm admin-assets

echo "3/4 Starting the platform (the first time downloads images and installs packages: a few minutes)"
docker compose up -d
# Nginx becomes healthy only once the api-gateway, the admin service and Reverb are.
docker compose up -d --wait nginx

echo "4/4 Loading the demo data"
# Only warnings: the seeders' info logs would flood the terminal (they go to stderr in Docker).
docker compose exec -T -e LOG_LEVEL=warning admin-service php artisan db:seed

port=$(value .env HTTP_PORT)
cat <<DONE

Ready: http://localhost:${port:-8080}
  Admin panel   http://localhost:${port:-8080}            admin@example.com / password123
  REST API      http://localhost:${port:-8080}/api/docs   (Swagger; customers: ada.lovelace@example.com / password123)
  GraphQL       http://localhost:${port:-8080}/api/graphql
  Emails        http://localhost:$(value .env MAIL_UI_PORT | grep . || echo 8025)
DONE
