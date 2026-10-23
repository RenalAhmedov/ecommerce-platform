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
docker compose exec admin-service php artisan storage:link   # serves storage/app/public at /storage
```

Build the CSS/JS (Tailwind + Alpine via Vite) the first time and whenever `resources/css`, `resources/js` or the Blade views change. Node 24 runs in a container, so nothing needs to be installed:

```bash
docker compose run --rm admin-assets
```

Its `node_modules` lives in a Docker volume (`admin-node-modules`), never in your folder, so building on the host as well (`cd admin-service && npm ci && npm run build`) keeps working side by side. The project's `.npmrc` pins the public npm registry, so a company-wide registry in your global npm config is not used for this project.

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
| password_reset_tokens, failed_jobs, product_imports | Laravel | `database/migrations/` – run by `php artisan migrate` |

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

## Admin JSON API

The same product management is available as JSON under **`/admin/api`** (`/api` is left to the Nest service for the Nginx routing). It uses the **access token issued by the Nest api-gateway** — one JWT for both services:

```bash
TOKEN=...   # from POST http://localhost:3000/api/auth/login as an admin
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "http://localhost:8000/admin/api/products?search=mug&limit=10"
```

| Method | Path | Notes |
|---|---|---|
| GET | `/admin/api/products` | `search`, `category_id`, `status` (`active`/`inactive`/`trashed`), `page`, `limit` (≤ 100) |
| GET | `/admin/api/products/{id}` | |
| POST | `/admin/api/products` | `name`, `sku`, `price`, optional `slug`, `description`, `category_id`, `is_active`, `initial_stock` → 201 |
| PUT/PATCH | `/admin/api/products/{id}` | partial update |
| DELETE | `/admin/api/products/{id}` | soft delete → 204 |
| PATCH | `/admin/api/products/{id}/restore` | |

Responses use the Nest field names (`isActive`, `imagePath`, `stock`, …) and list pages return `{ data, meta: { page, limit, total, totalPages } }`.

How a token is checked (`App\Services\JwtAuthService`): HS256 signature with `JWT_SECRET` (must equal the api-gateway's), not expired, has `sub` and `jti`, not revoked — the Nest logout key `auth:revoked-token:<jti>` is read from Redis without the `admin:` prefix (fails open if Redis is down, like Nest) — and the user still exists. The **admin role is read from the database**, not from the token, so a demoted admin loses access immediately (403). Rate limit: 120 requests/minute per user.

## Dashboard

`/dashboard` (and `GET /admin/api/dashboard`) shows revenue, order count and average order value for today / this week / this month, a 14-day revenue chart, orders waiting to be processed or shipped, orders by status, customers (+ new this month), low-stock products, the top sellers of the last 30 days and the latest orders. Revenue counts orders that were paid and not cancelled (`paid`, `processing`, `shipped`, `delivered`), by payment time, in UTC.

The figures are cached in Redis (`admin:dashboard:metrics`, cache DB 1) with stale-while-revalidate (`Cache::flexible`): fresh for 30 s, then the previous figures are served instantly for up to 5 minutes while one background refresh recomputes them. Because the Nest service also writes orders, a short lifetime is used instead of clearing the cache on every change; the page shows when its figures were computed.

## Sales reports

`/reports/sales` shows sales grouped by **day, week or month** for a date range (default: the last 30 days): orders, units sold, revenue and average order value per period, with totals. Periods without sales are listed with zeros, and the range is widened to whole periods (a monthly report from 15 Oct to 10 Nov covers all of October and November), so the rows always add up to the totals. A report covers at most 366 days, 260 weeks or 120 months.

- **Download CSV** — one line per period plus a `TOTAL` line, plain numbers for spreadsheets.
- **Download PDF** — an A4 report rendered with Dompdf (font subsetting keeps it around 20 KB). It is generated straight away: a report has at most a few hundred rows, which takes about a second, so a queued job would only add waiting.
- `GET /admin/api/reports/sales?period=week&date_from=…&date_to=…` returns the same data as JSON.

Revenue means the same as on the dashboard — both use `App\Repositories\SalesRepository`: orders that were paid and not cancelled, by payment time, in UTC.

## Users

`/users` (and `/admin/api/users`) lists customers and admins (search, role filter, deleted users), and creates, edits, deletes and restores them. The rules match the Nest registration exactly, so accounts work in both services:

- emails are trimmed and lower-cased, and unique case-insensitively **including deleted users**, checked under the same advisory lock as Nest (`users.email:<email>`) — a shop sign-up and an admin can't create the same email at the same moment;
- passwords are 8–72 characters (bcrypt ignores everything after 72 bytes) and stored as `$2y$` bcrypt, cost 12; leaving the password empty on edit keeps it, and a new one signs the user out of "remember me" sessions;
- an admin can't demote or delete their own account, and the last admin can't be removed — demotions and deletions of admins take a lock (`users.admins`), so two admins removing each other at the same moment can't leave none;
- deleting is a soft delete: the user can no longer log in (panel, API token or shop), their orders stay, and restoring brings the account back.

## Orders

Orders are placed and paid through the Nest api-gateway; the panel lists them (search by order number or customer, filter by status and date range) and moves them through fulfilment:

```
paid ──→ processing ──→ shipped ──→ delivered
  └──→ cancelled ←──┘          (cancelling puts the items back in stock)
```

- **pending** orders can't be changed here: Nest is charging them, and cancelling one mid-payment would leave a charged customer with a cancelled order. Nest's expiry job resolves stuck ones after 15 minutes. `payment_failed`, `cancelled` and `delivered` are final.
- A status change locks the order row and then the stock rows in product-id order — the same order as Nest — and re-checks the status under the lock, so two admins (or a double click) can never cancel an order twice and return its stock twice. Each returned item is recorded as a `cancellation` stock movement with the admin's id, shown on the order page.
- The same is available as JSON: `GET /admin/api/orders`, `GET /admin/api/orders/{id}`, `PATCH /admin/api/orders/{id}/status` (`{"status": "shipped"}`); responses use the Nest `OrderResponseDto` fields plus `customer` and `allowedStatuses`.

## Import and export

**Export** (`Products › Export CSV`) downloads the products the list currently shows (same search/category/status filters). It is streamed row by row, so its size doesn't matter. Cells starting with `= + - @` get a leading `'` so spreadsheet apps don't run them as formulas (CSV injection).

**Import** (`Products › Import`) takes the same format — columns `sku, name, slug, description, price, category_slug, is_active, stock` (only `sku, name, price` required), up to 2000 rows per file. The upload is stored, recorded in the `product_imports` table (owned by this service) and processed by the queue worker; the page refreshes until the import is done and lists every rejected row with its row number and reason.

- rows are matched by SKU: a new SKU creates the product with `stock` as opening stock, an existing SKU is updated (its stock is left alone, it only changes through stock movements);
- every row goes through `ProductService`, so it gets the same validation (`App\Validation\ProductRules`, also used by the forms and API), advisory locks and uniqueness rules as the forms;
- a bad row doesn't stop the import; importing the same file twice creates nothing new, and a job delivered twice runs only once (the import is claimed atomically: `queued → processing`).

An exported file can be edited in a spreadsheet and imported straight back.

## Categories

Categories form a tree through `parent_id` (Electronics › Computers › Laptops) and are managed only by this service (the Nest API reads them). Rules enforced in `App\Services\CategoryService`:

- the category dropdown used on every product page is cached in Redis and cleared by every category change (only this service writes categories);
- a category cannot be moved under itself or any of its subcategories (checked with a recursive SQL query up the parent chain);
- all category writes take one advisory lock (`categories.tree`), so two admins moving categories at the same moment can't create a loop, and slug checks can't race;
- a category with subcategories cannot be deleted; its products keep their `category_id` and show as uncategorised until the category is restored;
- a subcategory can only be restored after its parent;
- slugs stay reserved by trashed categories, like product SKUs.

## Catalogue sync with the api-gateway

The Nest api-gateway caches product responses in Redis. When this service changes the catalogue — products (created, updated, deleted, restored), product images, categories, or stock returned by a cancelled order — it tells the gateway through its internal webhook, so the shop shows the change at once:

```
ProductService / CategoryService / ProductImageService / OrderService
  └─ after the transaction commits → CatalogNotifier → queued NotifyCatalogChanged job
        └─ admin-worker → POST {NEST_API_URL}/api/internal/webhooks/catalog   (X-Api-Key: INTERNAL_API_KEY)
```

- **Queued, with retries** (6 attempts over about 10 minutes), so the panel never waits for the gateway and a gateway restart loses nothing. If every attempt fails the job lands in `failed_jobs`, and the gateway still catches up when its cache entries expire (60 s).
- **One webhook per burst**: the job is unique until it starts, so an import of 2000 rows sends one notification, not 2000. Changes made while it is being sent queue the next one, so none is missed.
- **Same event id on every retry**, so the gateway recognises a redelivery and handles it once.
- Configure `NEST_API_URL` (default `http://host.docker.internal:3000`, the gateway running on your machine) and `INTERNAL_API_KEY` (the same value as in `api-gateway/.env`) in `.env`. An empty `NEST_API_URL` turns the webhook off.

## Product images

Uploading an image (edit page, or `POST /admin/api/products/{id}/image` as multipart `image`) only stores the original on the private disk and queues `App\Jobs\ProcessProductImage`; the request returns at once (the API answers **202**). The `admin-worker` then:

1. turns the photo upright and re-encodes it as WebP (which also strips EXIF data such as GPS location): a 1200 px version (never enlarged) and a 300×300 thumbnail,
2. stores both as `storage/app/public/products/<id>/<uuid>.webp` and `<uuid>-thumb.webp`, served at `/storage/...`,
3. swaps `products.image_path` under a row lock and deletes the previous files and the original upload.

Only the newest upload of a product is ever applied (two quick uploads can't end with the older picture), removing an image also cancels an upload still in the queue, and a file that can't be decoded fails the job at once instead of retrying. Uploads are limited to JPEG/PNG/WebP, 5 MB and 8000 px per side.

## Redis and the queue worker

Sessions, cache and queues use the shared Redis. Every Laravel key starts with `admin:` (`REDIS_PREFIX`), so it never collides with the Nest service's keys (`bull:*`, `idempotency:*`, `products:*`, `auth:*`).

| What | Redis DB | Example key |
|---|---|---|
| Sessions | 0 | `admin:<session id>` |
| Queue (`default`) | 0 | `admin:queues:default` |
| Cache (dashboard figures, category dropdown, image upload markers), login and API rate limiters | 1 | `admin:<cache key>` |
| Nest keys Laravel reads (JWT revocation) | 0 | `auth:revoked-token:<jti>` — `shared` connection, no prefix |

The cache lives in its own Redis database because `php artisan cache:clear` runs `FLUSHDB` on it: in database 0 it would also wipe the Nest queues and idempotency keys, and log every admin out.

Queued jobs are run by the `admin-worker` container (`php artisan queue:listen`, which reloads code for every job so edits apply without a restart). A job is tried 3 times; after that it is stored in the Postgres `failed_jobs` table:

```bash
docker compose logs -f admin-worker
docker compose exec admin-service php artisan queue:failed     # list
docker compose exec admin-service php artisan queue:retry all  # retry
```

Tests do not need Redis: `phpunit.xml` switches to the in-memory `array` cache/session and the `sync` queue.

## Logs

Logs go to stderr (`LOG_STACK=stderr`), so they appear in `docker compose logs -f admin-service admin-worker`.

- **Every request** is logged once (`HTTP request`: method, path, status, duration, IP) and gets a **request id**, returned as the `X-Request-Id` header. A valid id sent by a proxy in front of the app is kept, so one request can be followed across services. The health check `/up` is not logged.
- The request id — and, behind the `admin` middleware, the acting admin's `user_id` — is attached to **every log line** written while handling the request, including lines from queued jobs it dispatched (Laravel `Context`).
- **Business events**: products, categories and users created / updated (which fields) / deleted / restored, order status changes (from → to, stock returned), imports queued / completed (counts) / failed, product images queued / processed / removed / discarded.
- **Security events** (warning): failed admin logins, lockouts, revoked tokens being used again, non-admins reaching admin routes, and the token revocation check being skipped because Redis is down. Passwords and tokens are never logged.

```
local.INFO: Order status changed {"order_id":12,"order_number":"ORD-…","from":"paid","to":"cancelled","stock_returned":true} {"request_id":"bc13…","user_id":1}
```

## Tests

Two suites, like the Nest api-gateway's unit and integration tests:

| Suite | Folder | What it covers | Needs |
|---|---|---|---|
| **Unit** | `tests/Unit` | one service at a time with its repositories mocked (business rules, lock order, edge cases) | nothing — no database, Redis or Docker; any accidental database call fails the test |
| **Integration** | `tests/Feature` (Laravel's name for them), one folder per area (`Api`, `Auth`, `Products`, `Orders`, …) | the real application over HTTP: routes, middleware, Form Requests, services, repositories, SQL, locks, queues, files, logs | Docker: a throwaway `postgres:16-alpine` and `redis:7-alpine` are started with **Testcontainers** for the run and removed afterwards |

```bash
docker compose run --rm admin-tests                                # everything (~1 min)
docker compose run --rm admin-tests --testsuite=Unit               # unit tests only (~15 s)
docker compose run --rm admin-tests --testsuite=Feature            # integration tests only
docker compose run --rm admin-tests --filter=OrderManagementTest   # one class or test
docker compose run --rm admin-tests tests/Feature/Api              # one folder
```

Anything after `admin-tests` is passed to `php artisan test`. Only Docker is needed, even on a fresh clone: the runner creates `.env` from `.env.example` if it is missing and runs `composer install` before the tests (about a second when nothing changed). The tests need no built CSS/JS and no app key of their own (`phpunit.xml` has a test-only one), and they don't need the app containers to be running.

`admin-tests` is a test-only service (compose profile `test`, never started by `docker compose up`). It is the only container that gets the Docker socket, so Testcontainers can start the throwaway databases; the running app and worker never get it. With PHP and Docker on the machine itself (as in CI) the same tests run with `composer test`, `composer test:unit` and `composer test:integration` and need no extra setup. Unit tests also run inside the app container (`docker compose exec admin-service composer test:unit`).

The integration tests never touch the development database or Redis: `phpunit.xml` points the database and Redis at placeholder hosts before Laravel boots, and `FeatureTestCase` aborts the run before any migration unless Laravel is connected to the throwaway containers. The full schema (the shared tables plus this service's own) is built once per run in the throwaway Postgres, and every test runs in a transaction that is rolled back. `migrate:fresh`, `db:wipe` and similar commands stay blocked outside tests because they would drop the Nest service's tables in the shared database.

If a run is killed half-way, its containers can be removed with:

```bash
docker rm -f $(docker ps -aq --filter label=ecommerce-platform.tests=admin-service)
```

## Day-to-day commands

Run PHP tools inside the container:

```bash
docker compose exec admin-service php artisan <command>   # e.g. route:list, tinker
docker compose exec admin-service composer <command>
docker compose logs -f admin-service
```
