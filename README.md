# E-commerce Platform

[![CI/CD](https://github.com/RenalAhmedov/ecommerce-platform/actions/workflows/ci.yml/badge.svg)](https://github.com/RenalAhmedov/ecommerce-platform/actions/workflows/ci.yml)

A hybrid e-commerce backend: a **Nest.js API gateway** for the shop (customers, catalogue, checkout) and a **Laravel admin panel** for the people running it, sharing one PostgreSQL database, one Redis and one login.

| | api-gateway (Nest.js 12, Node 24) | admin-service (Laravel 13, PHP 8.4) |
|---|---|---|
| For | customers and shop frontends | admins |
| Offers | REST API with Swagger, read-only GraphQL, JWT auth | Blade panel, JSON admin API |
| Highlights | product catalogue with Redis cache and Elasticsearch search (typo-tolerant, relevance), checkout with stock reservation, mock payment and idempotency keys, confirmation emails on a queue, rate limiting, token revocation | product CRUD with image processing and CSV import/export, nested categories, order management, dashboard, sales reports (CSV/PDF), user management, live order notifications over WebSockets, shop search preview |

Everything runs in Docker. Nothing else needs to be installed.

## Quick start

Requirements: [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine with Compose v2), about 4 GB of memory for Docker, and a POSIX shell (macOS, Linux, or WSL on Windows).

```bash
git clone https://github.com/RenalAhmedov/ecommerce-platform.git
cd ecommerce-platform
./scripts/setup.sh
```

[`scripts/setup.sh`](scripts/setup.sh) creates the three `.env` files with freshly generated secrets (the ones both services must share are made to match), builds the panel's CSS/JS, starts the whole platform, waits until it is healthy and loads the demo data. The first run takes a few minutes; running it again changes nothing that already exists.

| What | Where | Login |
|---|---|---|
| Admin panel | http://localhost:8080 | `admin@example.com` / `password123` |
| REST API + Swagger | http://localhost:8080/api/docs | customers: `ada.lovelace@example.com` (and 7 more) / `password123` |
| GraphQL | `POST http://localhost:8080/api/graphql` | same tokens as REST |
| Admin JSON API | http://localhost:8080/admin/api | an admin's access token |
| Emails (Mailpit) | http://localhost:8025 | |

The demo data: 1 admin, 8 customers, a category tree, 20 products with pictures and stock (some low, one sold out, one inactive) and 24 orders over the last 30 days in every status.

Day to day: `docker compose up -d` / `docker compose stop`; `docker compose logs -f api-gateway` (or any service); `docker compose down -v` wipes all data.

### Try it

```bash
# log in as a customer and keep the token
TOKEN=$(curl -s -X POST localhost:8080/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"ada.lovelace@example.com","password":"password123"}' | sed -E 's/.*"accessToken":"([^"]+)".*/\1/')

# search with a typo (Elasticsearch)
curl -s 'localhost:8080/api/products?search=headphnes&sortBy=relevance'

# the same through GraphQL, asking only for the fields needed
curl -s localhost:8080/api/graphql -H 'Content-Type: application/json' \
  -d '{"query":"{ products(search: \"headphnes\") { data { name price stock } } }"}'

# your orders
curl -s localhost:8080/api/orders -H "Authorization: Bearer $TOKEN"
```

A [Postman collection](postman/ecommerce-api.postman_collection.json) walks through everything (auth, products, checkout including idempotency and stock conflicts, token revocation, search, GraphQL, the admin API, rate limiting), with a check on every response.

## API

Interactive documentation: **Swagger** at http://localhost:8080/api/docs (every endpoint, schema and error, with an "Authorize" button for the token), the **GraphQL schema** in [`schema.gql`](api-gateway/src/graphql/schema.gql), and the [**Postman collection**](postman/ecommerce-api.postman_collection.json). An overview:

### REST: shop API (api-gateway), `http://localhost:8080/api`

| Method | Path | Access | Notes |
|---|---|---|---|
| POST | `/auth/register` | public, 3/min | creates a customer, returns an access token |
| POST | `/auth/login` | public, 5/min | returns a JWT (1 h) for customers and admins |
| POST | `/auth/logout` | logged in | revokes this token in both services |
| GET | `/auth/me` | logged in | the user behind the token |
| GET | `/products` | public | active products, cached in Redis; `search` (Elasticsearch: typos, word order, as you type), `categoryId`, `minPrice`, `maxPrice`, `sortBy` (`createdAt`, `price`, `name`, `relevance`), `sortOrder`, `page`, `limit` (≤ 100) |
| GET | `/products/:id` | public | cached |
| POST | `/products` | admin | with `initialStock` |
| PUT | `/products/:id` | admin | partial update |
| POST | `/orders` | logged in, 10/min | reserves stock, charges the mock gateway (`paymentToken: "tok_declined"` fails), queues the confirmation email; send an `Idempotency-Key` header to make retries safe; `402` declined, `409` out of stock |
| GET | `/orders` | logged in | your orders, `status` filter, paginated |
| GET | `/orders/:id` | owner or admin | with items and shipping address |
| GET | `/health` | public | `ok` / `degraded` (Redis or Elasticsearch down) / `503` (no database) |
| POST | `/internal/webhooks/catalog` | `X-Api-Key` | called by the admin service after catalogue changes |

Every other endpoint is limited to 100 requests a minute per user (per IP when logged out), with `X-RateLimit-*` and `Retry-After` headers. Errors always look the same: `{ statusCode, error, message, path, timestamp }`, where `message` lists every validation problem.

### GraphQL (api-gateway), `POST http://localhost:8080/api/graphql`

Read-only, next to REST and sharing its services, cache, guards, rate limits and errors: `products(…)` and `product(id)` (public, same filters and search as REST), `me`, `myOrders(status, page, limit)` and `order(id)` (with a token). Writes stay in REST, where checkout has its idempotency handling. The admin panel's *Shop search* page is a real client of it.

### Admin JSON API (admin-service), `http://localhost:8080/admin/api`

Takes the same access token as the shop API, from an admin (the role is read from the database, not the token); 120 requests a minute. Responses use the shop API's field names and page format.

| Method | Path | Notes |
|---|---|---|
| POST | `/auth/logout` | revokes the token in both services |
| GET | `/dashboard` | revenue, orders, customers, low stock, top sellers, recent orders |
| GET | `/reports/sales` | `period` (`day`, `week`, `month`), `date_from`, `date_to` |
| GET, POST, GET, PUT/PATCH, DELETE | `/products`, `/products/{id}` | list with `search`, `category_id`, `status` (`active`, `inactive`, `trashed`); delete is a soft delete |
| PATCH | `/products/{id}/restore` | |
| POST, DELETE | `/products/{id}/image` | upload (`202`, processed on the queue) or remove the picture |
| GET | `/orders`, `/orders/{id}` | filters: `search`, `status`, `date_from`, `date_to` |
| PATCH | `/orders/{id}/status` | allowed steps only (`paid → processing → shipped → delivered`, cancel before shipping); cancelling returns the stock |
| GET, POST, GET, PUT/PATCH, DELETE | `/users`, `/users/{id}` | an admin can't remove their own access or the last admin |
| PATCH | `/users/{id}/restore` | |

The admin panel itself (product CRUD with pictures, CSV import/export, nested categories, orders, reports with CSV/PDF export, users) is described in [admin-service/README.md](admin-service/README.md).

## Architecture

```mermaid
flowchart LR
    browser([Browser / API client]) --> nginx[Nginx :8080]

    nginx -- "/api" --> api[api-gateway<br/>Nest.js]
    nginx -- "everything else" --> admin[admin-service<br/>Laravel + FrankenPHP]
    nginx -- "/app (WebSocket)" --> reverb[Reverb]

    api --> pg[(PostgreSQL)]
    admin --> pg
    api --> redis[(Redis)]
    admin --> redis
    api --> es[(Elasticsearch)]

    admin -- "catalogue webhook<br/>(API key, queued)" --> api
    admin -- "shop search preview<br/>(GraphQL)" --> api
    api -. "order.paid<br/>(Redis pub/sub)" .-> events[admin-events]
    events --> worker[admin-worker] --> reverb
    reverb -. "live toasts" .-> browser
```

| Container | Role |
|---|---|
| `nginx` | single entry point: `/api` → Nest, `/app` → Reverb, the rest → Laravel; request ids, real client IPs |
| `api-gateway`, `api-migrate` | the Nest app; its migrations (it owns the shared schema) run once before anything starts |
| `admin-service`, `admin-migrate` | the Laravel app served by FrankenPHP; its own few tables |
| `admin-worker` | Laravel queue: image processing, imports, webhooks, broadcasts |
| `admin-events` | turns Nest's `order.paid` events into live notifications |
| `reverb` | WebSocket server for the panel |
| `postgres`, `redis`, `elasticsearch`, `mailpit` | data, cache/queues/sessions, product search, caught emails |

### How the two services work together

- **One database, one owner.** Both services read and write the same tables; Nest owns the schema (TypeORM migrations), and Laravel keeps a copy of the definitions for its tests plus its own history table. The tables, their relationships and the reasons behind them are in [database/README.md](database/README.md). The same uniqueness rules are enforced on both sides with the same PostgreSQL advisory-lock keys (e.g. `products.sku:<SKU>`), so a shop sign-up and an admin can't create the same email at the same moment.
- **One login, one user service.** The api-gateway's auth module is the central user service: it registers customers, checks passwords and issues every JWT. Both services verify the same tokens (shared secret), and an admin can open the panel with that token: a shared session that ends when either side logs out. The admin panel manages the same `users` table (create, edit, delete, roles) with the same rules and locks, and its changes reach the tokens too: deleting a user, demoting an admin or changing a password revokes their tokens in both services through shared Redis keys.
- **Catalogue changes** made in the panel reach Nest through an API-key webhook, sent from Laravel's queue with retries and de-duplicated by event id; Nest drops its cached product responses and re-syncs its search index.
- **Live updates.** Paid orders (from Nest) and status changes (from other admins) appear in every open panel within a second, through Reverb and Echo.
- **Traceability.** Nginx gives every request an id that travels into both services' logs and across the webhook and GraphQL calls between them.

## Tech stack

| Technology | Used for | Why |
|---|---|---|
| **Nest.js 12** (Node 24) + TypeORM | the shop API | modules, dependency injection, guards and pipes give a clear structure; TypeORM owns the schema through migrations |
| **Laravel 13** (PHP 8.4) + Breeze | the admin panel | server-rendered pages, auth scaffolding, Form Requests, Resources, queues and Eloquent cover an admin panel with little code |
| **PostgreSQL 16** | the one shared database | transactions, row locks and advisory locks make stock reservation and uniqueness safe across both services |
| **Redis 7** | cache, queues, sessions, rate limits, token revocation, pub/sub | one fast in-memory store both services can reach; everything in it can be rebuilt or degrades gracefully |
| **BullMQ** / Laravel queues | emails, image processing, imports, webhooks, broadcasts | slow or retryable work leaves the request, with retries and backoff |
| **`@nestjs/microservices`** (Redis transport) | the `order.paid` event | event-driven: the admin side reacts to payments without Nest knowing who listens |
| **Elasticsearch 9** | product search | typo tolerance, relevance ranking and search-as-you-type that SQL `LIKE` can't do |
| **GraphQL** (Apollo) | read API next to REST | clients ask for exactly the fields they need in one request |
| **JWT** (HS256, shared secret) | one login for both services | stateless, so each service verifies tokens itself; a Redis key makes revocation possible |
| **Laravel Reverb + Echo** | live order notifications | first-party WebSocket server, no external service such as Pusher needed |
| **Nginx** | single entry point | routes `/api`, `/app` and the panel on one port, adds request ids and real client IPs |
| **FrankenPHP** | serves Laravel | a modern PHP app server in one container, no separate PHP-FPM + web server |
| **Intervention Image**, **DomPDF** | product pictures, PDF reports | resizing/format conversion and HTML-to-PDF in PHP |
| **Tailwind, Alpine.js, Vite** | the panel's frontend | small, no SPA framework needed for an admin panel |
| **Swagger** (`@nestjs/swagger`) | API documentation | generated from the code, so it can't drift from it |
| **Vitest**, **PHPUnit**, **Testcontainers** | tests | integration tests run against real PostgreSQL, Redis and Elasticsearch, never mocks of them |
| **Mailpit** | development emails | catches every email so it can be read in a browser |
| **Docker Compose**, **GitHub Actions** | running and CI/CD | one command starts everything; every push is tested and `main` publishes images |

## Testing

Only Docker is needed:

```bash
docker compose run --rm api-tests      # Nest: 290 unit + 42 integration tests
docker compose run --rm admin-tests    # Laravel: 282 unit + integration tests
```

Unit tests mock every collaborator. Integration tests run the real application over HTTP against real PostgreSQL, Redis and Elasticsearch, started as throwaway containers with [Testcontainers](https://testcontainers.com/) for each run, so the development data is never touched. They include the hard cases: concurrent registrations and orders racing for the last items, idempotent retries, cache invalidation, a search index catching up with changes made straight in the database, and falling back to Postgres when Elasticsearch stops.

## CI/CD

[GitHub Actions](.github/workflows/ci.yml) on every pull request and push to `main`: formatting, lint, typecheck and all tests of both services, the frontend build and a check of the compose and Nginx configuration. On `main`, once everything passes, both production images are built and pushed to GitHub's container registry (`ghcr.io/renalahmedov/ecommerce-platform-api-gateway` and `…-admin-service`). Actions are pinned to commit SHAs; Dependabot proposes updates weekly.

## Production

`docker compose -f docker-compose.prod.yml up -d --build` runs the production-like stack on http://localhost:8090, with its own data: images with the code baked in (multi-stage builds, unprivileged users, health checks), production PHP and Node settings, `queue:work`, no Swagger or GraphQL introspection, only Nginx published. Secrets come from the services' `.env` files.

## Design decisions

- **Shared database instead of service-to-service calls for data.** The admin panel works on the same rows the shop does, so nothing has to be copied or kept in sync, and a report is a query rather than a distributed join. The price is coupling on the schema, which is why one service owns it and both enforce the same rules with the same locks.
- **Correctness under concurrency.** Stock is reserved under row locks taken in a fixed order (no deadlocks, no overselling), order totals are computed from database prices in cents, checkout accepts an `Idempotency-Key`, and uniqueness checks run under advisory locks. Each of these has an integration test that races real requests.
- **Degrade, don't fail.** Redis being down disables caching and rate limiting but not the shop; Elasticsearch being down falls back to Postgres search; a missed webhook is caught up by a scheduled sync. The exceptions fail closed on purpose: logging out and revoking a deleted user's tokens refuse to report success while Redis can't record them.
- **Elasticsearch finds, Postgres answers.** The index holds only what is searched; prices and stock always come from the database. For a small catalogue Postgres alone would do; Elasticsearch earns its place with typo tolerance, relevance and large catalogues.
- **Queues for anything slow or retryable**: emails, image processing, imports, webhooks, broadcasts.
- **Layering in both services**: controllers stay thin, services hold the rules, repositories are the only code that talks to a data store.

## Repository layout

```
├── api-gateway/          Nest.js API gateway          → api-gateway/README.md
├── admin-service/        Laravel admin panel          → admin-service/README.md
├── nginx/                reverse proxy config
├── database/             schema (SQL) and its design    → database/README.md
├── postman/              API walkthrough collection
├── scripts/setup.sh      first start of a fresh clone
├── docker-compose.yml    development stack (+ test runners)
└── docker-compose.prod.yml
```

## Troubleshooting

- **`Cannot connect to the Docker daemon`**: start Docker Desktop and wait until it is running.
- **`port is already allocated`**: something else uses that port. Every published port can be changed in the root `.env` (`HTTP_PORT`, `DB_PORT`, `REDIS_PORT`, …, see [`.env.example`](.env.example)).
- **Elasticsearch keeps restarting or the stack is slow**: give Docker more memory (Docker Desktop → Settings → Resources; about 4 GB).
- **`/api/health` says `degraded`** right after starting: Elasticsearch takes about half a minute; search uses Postgres until then.
