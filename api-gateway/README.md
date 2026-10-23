# api-gateway

Nest.js API gateway and core API for the e-commerce platform.

First-time setup (Docker, `.env`, migrations) is described in the [root README](../README.md#getting-started). Requires Node.js 24+ (see `.nvmrc`).

## Run

```bash
npm run start:dev   # watch mode
npm run start:prod  # compiled build (run `npm run build` first)
```

## API documentation

Interactive Swagger UI: **http://localhost:3000/api/docs** (raw OpenAPI JSON at `/api/docs-json`). Log in with `POST /api/auth/login`, click **Authorize** and paste the `accessToken` to call protected endpoints. Schemas are generated from the DTOs at build time by the `@nestjs/swagger` CLI plugin (see `nest-cli.json`). The docs are not served when `NODE_ENV=production`.

## Security

- **Rate limiting** (stored in Redis, shared by all instances): login 5/min, register 3/min, place order 10/min, everything else 100/min. Logged-in users are limited per user, anonymous requests per IP. Over the limit: `429` with a `Retry-After` header. If Redis is down, requests are allowed. Disable with `RATE_LIMIT_ENABLED=false`.
- **Logout revokes the token**: every JWT carries a unique `jti`; `POST /api/auth/logout` stores it in Redis (`auth:revoked-token:<jti>`) until the token would expire, and revoked tokens get `401`. Other sessions of the same user keep working.
- **Security headers** via `helmet` (CSP, HSTS, no-sniff, frame protection, no `X-Powered-By`).
- **CORS** is off unless `CORS_ORIGINS` lists allowed browser origins (comma-separated).

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

```bash
npm test                  # unit tests (no Docker needed)
npm run test:cov          # unit test coverage
npm run test:integration  # integration tests (needs Docker running)
```

Integration tests use [Testcontainers](https://testcontainers.com/): each run starts its own throwaway `postgres:16-alpine` and `redis:7-alpine` containers (the same images as `docker-compose.yml`), runs the migrations, boots the real app over HTTP with the real Redis event listener, and removes the containers afterwards. Your `docker compose` services and dev data are never touched, and the compose stack does not need to be running. Only outgoing email is faked. Tables and Redis are wiped before every test.
