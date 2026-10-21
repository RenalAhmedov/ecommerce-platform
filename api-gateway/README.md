# api-gateway

Nest.js API gateway and core API for the e-commerce platform.

## Setup

```bash
# from the repository root: start PostgreSQL, Redis and Mailpit
docker compose up -d

# from api-gateway/
cp .env.example .env
npm install
npm run migration:run
```

`JWT_SECRET` in `.env` must be set to a random string of at least 32 characters, e.g. the output of `openssl rand -hex 32`. The app refuses to start without it.

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

## Test

```bash
npm test          # unit tests
npm run test:cov  # coverage
```
