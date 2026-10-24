# api-gateway

Nest.js API gateway and core API for the e-commerce platform.

First-time setup (Docker, `.env`, migrations) is described in the [root README](../README.md#getting-started). Requires Node.js 24+ (see `.nvmrc`).

## Run

With Docker (from the repository root), no local Node.js needed:

```bash
docker compose up -d          # api-migrate runs the migrations, then api-gateway starts in watch mode
docker compose logs -f api-gateway
```

`api-migrate` is a one-shot service: it applies pending migrations and exits, and both apps wait for it to succeed (this service owns the shared schema). Code changes reload automatically. After a dependency change (`package-lock.json`), the containers reinstall `node_modules` by themselves on their next start.

Without Docker for the app itself: `npm run start:dev` (stop the `api-gateway` container first, it uses port 3000).

### Health check

`GET /api/health` → `200 {"status":"ok","checks":{"database":"up","redis":"up","elasticsearch":"up"}}` (`"disabled"` without `ELASTICSEARCH_URL`). `"degraded"` (still 200) means Redis or Elasticsearch is down, which every feature survives; no database means `503`. Docker uses it for the container's health, and it is never rate-limited or logged.

### Docker image

The `Dockerfile` has two targets: `dev` (dependencies only; compose mounts the source) and `prod` (compiled JavaScript and production dependencies only, runs as the unprivileged `node` user, built-in `HEALTHCHECK`). Migrations for a production deployment: `npm run typeorm -- migration:run` with the same image, before starting new instances.

## API documentation

Interactive Swagger UI: **http://localhost:3000/api/docs** (raw OpenAPI JSON at `/api/docs-json`). Log in with `POST /api/auth/login`, click **Authorize** and paste the `accessToken` to call protected endpoints. Schemas are generated from the DTOs at build time by the `@nestjs/swagger` CLI plugin (see `nest-cli.json`). The docs are not served when `NODE_ENV=production`.

## Security

- **Rate limiting** (stored in Redis, shared by all instances): login 5/min, register 3/min, place order 10/min, everything else 100/min. Logged-in users are limited per user, anonymous requests per IP. Over the limit: `429` with a `Retry-After` header. If Redis is down, requests are allowed. Disable with `RATE_LIMIT_ENABLED=false`.
- **Logout revokes the token**: every JWT carries a unique `jti`; `POST /api/auth/logout` stores it in Redis (`auth:revoked-token:<jti>`) until the token would expire, and revoked tokens get `401`. Other sessions of the same user keep working. The admin service reads the same key, and its `POST /admin/api/auth/logout` writes it, so a logout counts in both services.
- **Tokens die with the account**: when the admin service deletes a user, changes their role or sets a new password, it writes `auth:user-tokens-valid-after:<userId>` (unix time); the guard rejects that user's tokens issued before it (`401`), checking both keys in one Redis round trip. Tokens must have an `iat`, and `JWT_EXPIRES_IN` is capped at a day so a cutoff always outlives the tokens it kills.
- **Security headers** via `helmet` (CSP, HSTS, no-sniff, frame protection, no `X-Powered-By`).
- **CORS** is off unless `CORS_ORIGINS` lists allowed browser origins (comma-separated).

## Product search (Elasticsearch)

`GET /api/products?search=…` (and the GraphQL `products(search: …)` query) searches with Elasticsearch when `ELASTICSEARCH_URL` is set (docker compose sets it):

- **typos are forgiven**: `headphnes` → Wireless Headphones, `algoritms` → Introduction to Algorithms;
- **words in any order**, in the name (weighted ×3) and the description: `kettle electric` → Electric Kettle;
- **as you type**: the last word may be unfinished, `wireless headph`;
- **exact SKU**, any case: `acc-key-01`;
- `sortBy=relevance` puts the best match first; the other sorts and the `categoryId` / `minPrice` / `maxPrice` filters work as without a search.

**Elasticsearch finds, Postgres answers.** The index (`products_v2`) holds only what is searched, filtered or sorted on; it returns the ids of the matches in order, and the products are then read from Postgres, so prices and stock are never stale. Only active, not deleted products are indexed.

**Kept in sync with both services.** Products change here and, directly in the database, in the admin service. A sync job writes every product changed since the last sync again (or removes it when inactive or deleted), and runs after this service's product writes, after the admin service's catalogue webhook, at start and every 5 minutes as a safety net. It runs on a BullMQ queue with deduplication: at most one sync runs and one waits, so a change made during a sync always gets a sync after it. Rewriting a product is harmless, so syncs can overlap (each one re-reads a minute before the last), and the time up to which the index is in sync is stored in the index's own metadata: a new or lost index is rebuilt completely. A changed mapping gets a new index name (`products_v1` → `products_v2`); the new one fills itself on start and the old one is deleted.

**Never a hard dependency.** With `ELASTICSEARCH_URL` empty, or while Elasticsearch is down or still building its index, the search runs on Postgres instead (a plain substring match, newest first for `relevance`), and `/api/health` reports `"elasticsearch": "down"` with status `degraded`. Cached search results are dropped whenever the index changes, so answers given by the fallback don't linger once Elasticsearch is back.

**Why Elasticsearch here?** With a small catalogue Postgres is just as fast; what Elasticsearch adds is search quality (typos, relevance, word order), plus speed and load off the main database once the catalogue grows large (`ILIKE '%…%'` reads every row). A small shop could start with Postgres's own full-text search and trigram indexes, and add Elasticsearch when search outgrows them.

## GraphQL

A read-only GraphQL API at **`POST /api/graphql`** (also through Nginx: http://localhost:8080/api/graphql), next to the REST API. The resolvers call the same services as the REST controllers, so the Redis cache, access rules, validation, rate limits and errors are shared; writes stay in REST, where checkout has its Idempotency-Key handling. The schema is in [`src/graphql/schema.gql`](src/graphql/schema.gql), regenerated from the decorated classes on every start outside production, so every change to the API shows up in review.

| Query | Access |
|---|---|
| `products(search, categoryId, minPrice, maxPrice, sortBy, sortOrder, page, limit)` | public |
| `product(id)` | public |
| `me` | logged in |
| `myOrders(status, page, limit)` | logged in |
| `order(id)` | your own order, or any order for admins |

```bash
curl -s localhost:8080/api/graphql -H 'Content-Type: application/json' \
  -d '{"query":"{ products(limit: 3, sortBy: Price, sortOrder: Desc) { data { name price stock category { name } } meta { total } } }"}'

curl -s localhost:8080/api/graphql -H 'Content-Type: application/json' -H "Authorization: Bearer $TOKEN" \
  -d '{"query":"{ me { email } myOrders { data { orderNumber status total } } }"}'
```

- **Errors** keep the REST status in GraphQL's shape: `extensions.code` (`BAD_REQUEST`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `TOO_MANY_REQUESTS`, …), `extensions.status` and every validation message in `extensions.details`.
- **Enums** use their GraphQL names: `sortBy: Price`, `sortOrder: Desc`, `status: Shipped`.
- An unused filter may be left out or sent as `null` (as GraphQL clients do with unset variables): both mean "no filter".
- **Used by** the admin panel's *Shop search* page, which asks this API what customers find (only this service has the Elasticsearch search).
- Money is a decimal string (`"12.50"`) like in REST; ids are `Int`; dates are ISO strings.
- The guards (JWT, roles, rate limiting) and the error filter handle GraphQL operations the same way as REST calls, so a query can't be used to get around them.
- Introspection is off in production, and there is no built-in browser IDE (it would need the strict Content-Security-Policy relaxed): use Postman, Insomnia or any GraphQL client against the endpoint.

## Logs

- **One line per request**: method, path, status, duration, the user id and a request id. The id is returned as `X-Request-Id`; a valid one sent by the caller is kept, and the admin service sends the id of the admin action that triggered its webhook, so one change can be followed through both services' logs.
- **Business events**: registrations, logins, logouts, product writes (with the admin's user id), orders placed, paid and declined, confirmation emails sent, stale orders expired.
- **Security events** (warnings): failed logins, revoked tokens being used again.
- **Infrastructure trouble** (Redis, queue or SMTP unavailable) is logged once a minute at most, so an outage does not flood the log. Passwords and tokens are never logged.

## Catalogue sync from the admin service

Products, categories and stock are also changed by the Laravel admin service, directly in the shared database. It then calls **`POST /api/internal/webhooks/catalog`** (body `{ eventId, type: "catalog.changed", occurredAt }`), and this API drops its cached product responses at once instead of after their TTL.

- Authenticated with the shared **`INTERNAL_API_KEY`** in the `X-Api-Key` header (at least 32 characters, the same value in both services' `.env`). No JWT is accepted on this route, and the key is compared in constant time.
- Each `eventId` is remembered in Redis for a day (`webhook:event:<id>`, `SET NX`), so a retried delivery is answered `202 {"status":"duplicate"}` and handled once.
- Answers `202 {"status":"processed"}`. Shown in Swagger under **Internal** (authorize with the API key).

## Database migrations

```bash
npm run migration:run       # apply pending migrations
npm run migration:revert    # undo the last migration
npm run migration:show      # list applied and pending migrations
npm run migration:create src/database/migrations/<Name>
```

## Code quality

```bash
npm run lint      # oxlint
npm run format    # prettier
```

## Test

With only Docker, from the repository root (nothing else installed):

```bash
docker compose run --rm api-tests                     # unit + integration tests
docker compose run --rm api-tests npm test            # unit tests only
docker compose run --rm api-tests npm run lint        # any npm script
```

Or with Node 24 on your machine, from `api-gateway/`:

```bash
npm test                  # unit tests (no Docker needed)
npm run test:cov          # unit test coverage
npm run test:integration  # integration tests (needs Docker running)
```

Integration tests use [Testcontainers](https://testcontainers.com/): each run starts its own throwaway `postgres:16-alpine` and `redis:7-alpine` containers (the same images as `docker-compose.yml`), runs the migrations, boots the real app over HTTP with the real Redis event listener, and removes the containers afterwards; the search suite also starts its own Elasticsearch (the others run with search off). Your `docker compose` services and dev data are never touched, and the compose stack does not need to be running. Only outgoing email is faked. Tables and Redis are wiped before every test.

In Docker, `node_modules` is a volume shared by the development containers (`api-gateway`, `api-migrate`, `api-tests`). [`docker/install-dependencies.sh`](docker/install-dependencies.sh) reinstalls it only when `package-lock.json` changed since the last install, so a fresh clone and a pulled dependency change both just work.
