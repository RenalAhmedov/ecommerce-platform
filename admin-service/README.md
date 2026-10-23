# admin-service

Laravel 13 admin panel for the e-commerce platform. It shares the PostgreSQL database and Redis with the Nest.js `api-gateway`.

Everything runs in Docker: you do not need PHP or Composer installed locally.

## Run

From the repository root:

```bash
docker compose up -d          # starts postgres, redis, mailpit, admin-service and admin-worker
```

The panel is served at **http://localhost:8000** (`ADMIN_PORT` in the root `.env`).

First time only (creates `.env` and the encryption key):

```bash
cp admin-service/.env.example admin-service/.env
docker compose run --rm --no-deps admin-service composer install
docker compose run --rm --no-deps admin-service php artisan key:generate
docker compose up -d admin-service
```

Build the CSS/JS (Tailwind + Alpine via Vite) whenever `resources/css`, `resources/js` or the Blade views change. Node runs in a throwaway container:

```bash
docker run --rm -v "$PWD/admin-service":/app -w /app node:24-alpine sh -c "npm ci && npm run build"
```

## Admin access

Only users with the `admin` role can sign in at **/login**. Customers (who register through the Nest API) get the same "credentials do not match" error as a wrong password, and the `admin` middleware signs out any session whose user is no longer an admin. There is no self-registration: promote an existing user instead.

```bash
docker compose exec postgres psql -U ecommerce -d ecommerce -c "UPDATE users SET role = 'admin' WHERE email = 'you@example.com'"
```

Password reset emails go to Mailpit at http://localhost:8025 and are only sent to admin accounts.

## Database and migrations

Both services share one PostgreSQL database, so table ownership is explicit:

| Tables | Owned by | Migrations |
|---|---|---|
| users, categories, products, inventories, orders, order_items, inventory_movements | shared (created by the Nest.js `api-gateway`) | `database/migrations/shared/` – an exact Laravel copy of the schema, **not** run by `php artisan migrate` |
| password_reset_tokens, failed_jobs | Laravel | `database/migrations/` – run by `php artisan migrate` |

Laravel records its migrations in `laravel_migrations` (TypeORM uses `migrations`), so the two histories never collide.

```bash
docker compose exec admin-service php artisan migrate          # Laravel-owned tables only
docker compose exec admin-service php artisan migrate:status
```

To build the full schema with Laravel alone (e.g. a fresh database without the Nest service):

```bash
docker compose exec admin-service php artisan migrate --path=database/migrations/shared
docker compose exec admin-service php artisan migrate
```

Both frameworks produce an identical schema (verified by diffing `pg_dump --schema-only` of a database built by each).

## Redis and the queue worker

Sessions, cache and queues use the shared Redis. Every Laravel key starts with `admin:` (`REDIS_PREFIX`), so it never collides with the Nest service's keys (`bull:*`, `idempotency:*`, `products:*`, `auth:*`).

| What | Redis DB | Example key |
|---|---|---|
| Sessions | 0 | `admin:<session id>` |
| Queue (`default`) | 0 | `admin:queues:default` |
| Cache, login rate limiter | 1 | `admin:<cache key>` |

The cache lives in its own Redis database because `php artisan cache:clear` runs `FLUSHDB` on it: in database 0 it would also wipe the Nest queues and idempotency keys, and log every admin out.

Queued jobs are run by the `admin-worker` container (`php artisan queue:listen`, which reloads code for every job so edits apply without a restart). A job is tried 3 times; after that it is stored in the Postgres `failed_jobs` table:

```bash
docker compose logs -f admin-worker
docker compose exec admin-service php artisan queue:failed     # list
docker compose exec admin-service php artisan queue:retry all  # retry
```

Tests do not need Redis: `phpunit.xml` switches to the in-memory `array` cache/session and the `sync` queue.

## Tests

```bash
docker compose exec admin-service php artisan test
```

Tests run against the separate `ecommerce_admin_test` database (set in `phpunit.xml`), never the dev database. It is created automatically for fresh Postgres volumes (`docker/postgres/init/`); on an existing volume create it once:

```bash
docker compose exec postgres psql -U ecommerce -d postgres -c "CREATE DATABASE ecommerce_admin_test"
```

The schema is rebuilt from both migration folders at the start of a run and every test runs in a rolled-back transaction. `migrate:fresh`, `db:wipe` and similar commands are blocked outside tests because they would drop the Nest service's tables in the shared database.

## Day-to-day commands

Run PHP tools inside the container:

```bash
docker compose exec admin-service php artisan <command>   # e.g. route:list, tinker
docker compose exec admin-service composer <command>
docker compose logs -f admin-service
```
