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
npm test          # unit tests
npm run test:cov  # coverage
```
