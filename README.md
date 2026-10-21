# E-commerce Platform

Hybrid e-commerce platform: a Laravel admin panel and a Nest.js API gateway sharing one PostgreSQL database.

```
ecommerce-platform/
├── admin-service/       # Laravel (admin panel), coming soon
├── api-gateway/         # Nest.js (API gateway + core API)
├── database/schema/     # Reference SQL schema, one file per table
└── docker-compose.yml   # PostgreSQL, Redis, Mailpit
```

## Prerequisites

| Tool | Version | Check with |
|------|---------|------------|
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine + Compose v2) | Compose **v2** | `docker compose version` |
| [Node.js](https://nodejs.org/) | **24** or newer | `node -v` |
| Git | any | `git --version` |

If you use [nvm](https://github.com/nvm-sh/nvm), run `nvm install` inside `api-gateway/` and it picks up the right version from `.nvmrc`.

Docker runs the infrastructure (database, cache, mail). The Nest.js app runs directly on your machine with Node.

## Getting started

### 1. Start the infrastructure

Make sure Docker Desktop is running, then from the repository root:

```bash
docker compose up -d
docker compose ps        # all three services should be "running" / "healthy"
```

This starts:

| Service | Address | Login | Used for |
|---------|---------|-------|----------|
| PostgreSQL 16 | `localhost:5432` | database `ecommerce`, user `ecommerce`, password `secret` | Main database |
| Redis 7 | `localhost:6379` | none | Caching and job queues |
| Mailpit | SMTP `localhost:1025`, inbox http://localhost:8025 | none | Catches every email the app sends, so nothing is really delivered |

All ports are bound to `127.0.0.1`, so they are only reachable from your own machine. The credentials are local development defaults.

### 2. Configure the API

```bash
cd api-gateway
cp .env.example .env
```

Open `.env` and set `JWT_SECRET` to a random string of at least 32 characters. To generate one:

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

The other values in `.env` already match `docker-compose.yml`.

### 3. Install, migrate, run

```bash
npm ci                   # install exact dependency versions from package-lock.json
npm run migration:run    # create the database tables
npm run start:dev        # start the API on http://localhost:3000 (restarts on file changes)
```

### 4. Try it

```bash
# register a customer
curl -s -X POST localhost:3000/api/auth/register -H 'content-type: application/json' \
  -d '{"name":"Demo","email":"demo@example.com","password":"password123"}'

# log in and keep the token
TOKEN=$(curl -s -X POST localhost:3000/api/auth/login -H 'content-type: application/json' \
  -d '{"email":"demo@example.com","password":"password123"}' \
  | node -pe 'JSON.parse(require("fs").readFileSync(0)).accessToken')

# call a protected endpoint
curl -s localhost:3000/api/auth/me -H "Authorization: Bearer $TOKEN"
```

## Day-to-day commands

| What | Command (from the repository root) |
|------|---------|
| Stop the services, keep the data | `docker compose stop` |
| Start them again | `docker compose up -d` |
| Wipe the database and Redis completely | `docker compose down -v`, then rerun `npm run migration:run` |
| Open a SQL shell | `docker compose exec postgres psql -U ecommerce -d ecommerce` |
| See service logs | `docker compose logs -f postgres` |

More API commands (tests, migrations) are in [api-gateway/README.md](api-gateway/README.md).

## Troubleshooting

**`Cannot connect to the Docker daemon`**
Docker Desktop isn't running. Start it and wait until it says it's running.

**`Bind for 127.0.0.1:5432 failed: port is already allocated`** (or 6379, 1025, 8025)
Something on your machine already uses that port, usually a locally installed PostgreSQL or Redis. Either stop it, or pick another port: create a `.env` file in the **repository root** containing e.g. `DB_PORT=5433`, and set the **same** `DB_PORT=5433` in `api-gateway/.env`. The two must match. The other ports can be changed the same way with `REDIS_PORT`, `MAIL_SMTP_PORT` and `MAIL_UI_PORT` (see the root `.env.example`).

**`docker-compose: command not found`, or errors about the `name` field**
You have the old Compose v1. Use `docker compose` (with a space), which comes with Docker Desktop.

**`Config validation error: "JWT_SECRET" is not allowed to be empty`** (or `length must be at least 32 characters long`)
Set `JWT_SECRET` in `api-gateway/.env` as described in step 2.

**`npm warn EBADENGINE Unsupported engine`, `ReferenceError: crypto is not defined`, `ERR_REQUIRE_ESM`, or `The "path" argument must be of type string. Received undefined`**
Your Node.js is too old (these are what Node 18–22 print). Install Node 24 with `nvm install` inside `api-gateway/`, then reinstall: `rm -rf node_modules && npm ci`.

**`relation "users" does not exist`**
The tables haven't been created yet. Run `npm run migration:run` in `api-gateway/`.

**`ECONNREFUSED 127.0.0.1:5432` when starting the API**
PostgreSQL isn't running. Run `docker compose up -d` from the repository root.

**`EADDRINUSE: address already in use :::3000`**
Another app (or another copy of this one) already uses port 3000. Stop it, or set `PORT=3001` in `api-gateway/.env`.

**`npm ci` fails with `E401` / `Unable to authenticate`**
Your global npm config points at a private registry. `api-gateway/.npmrc` already forces the public npm registry for this project, so make sure you run `npm ci` **inside** `api-gateway/`.
