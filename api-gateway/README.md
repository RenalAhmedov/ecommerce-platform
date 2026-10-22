# api-gateway

Nest.js API gateway and core API for the e-commerce platform.

First-time setup (Docker, `.env`, migrations) is described in the [root README](../README.md#getting-started). Requires Node.js 24+ (see `.nvmrc`).

## Run

```bash
npm run start:dev   # watch mode
npm run start:prod  # compiled build (run `npm run build` first)
```

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
