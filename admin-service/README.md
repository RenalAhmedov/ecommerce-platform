# admin-service

Laravel 13 admin panel for the e-commerce platform. It shares the PostgreSQL database and Redis with the Nest.js `api-gateway`.

Everything runs in Docker: you do not need PHP or Composer installed locally.

## Run

From the repository root:

```bash
docker compose up -d          # starts postgres, redis, mailpit and admin-service
```

The panel is served at **http://localhost:8000** (`ADMIN_PORT` in the root `.env`).

First time only (creates `.env` and the encryption key):

```bash
cp admin-service/.env.example admin-service/.env
docker compose run --rm --no-deps admin-service composer install
docker compose run --rm --no-deps admin-service php artisan key:generate
docker compose up -d admin-service
```

## Day-to-day commands

Run PHP tools inside the container:

```bash
docker compose exec admin-service php artisan <command>   # e.g. route:list, tinker
docker compose exec admin-service composer <command>
docker compose logs -f admin-service
```
