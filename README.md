# Office Users API

Laravel 13 + Sanctum service that owns user accounts and issues API tokens. The [Office Inventory API](../office-inventory-api) uses it to authenticate callers and look up users.

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve                       # http://127.0.0.1:8000
```

Seeded accounts (password `password`, local only): `admin@example.com` (admin), `staff@example.com` (staff), `inventory-service@example.com` (service).

### Docker

`Dockerfile` builds a production image (PHP 8.3 + Apache, no dev dependencies). It needs `APP_KEY` and `DB_*` env vars, runs `migrate --force` on start (disable with `RUN_MIGRATIONS=0`), and caches config and routes. For a full local stack with Postgres and the inventory API, use `docker compose` in `office-inventory-api`.

For container setup, `php artisan service:bootstrap <email> [--token-file=path] [--seed]` makes sure a service user exists and issues it a token, writing the token to the file only if the file is missing or empty. `--seed` adds the demo accounts.

Optional boot-time env vars, for hosts without a shell (both idempotent, applied after migrations):

| Variable | Effect |
|---|---|
| `ADMIN_EMAIL` + `ADMIN_PASSWORD` | Create that admin if no user has the email |
| `INVENTORY_SERVICE_TOKEN` | Accept this value (32+ chars) as the service user's token; changing it revokes the previous one. Service user email from `INVENTORY_SERVICE_EMAIL` (default `inventory-service@example.com`) |
| `PORT` | Port Apache listens on (default 80) |
| `CORS_ALLOWED_ORIGINS` | Comma-separated browser origins allowed on `api/*` (default `*`) |
| `CORS_ALLOWED_ORIGIN_PATTERN` | Optional regex without delimiters, e.g. `https://office-inventory-ui-.*\.vercel\.app` |

`APP_KEY` may also be a bare base64 32-byte value (as Render's `generateValue` produces); the `base64:` prefix is added. Proxy headers are trusted, so the app sees HTTPS behind a TLS-terminating host. Render deployment: see `render.yaml` in `office-inventory-api`.

## Roles

| Role | Can |
|---|---|
| `staff` | Log in, read own profile (`/api/user`, `/api/users/{own id}`) |
| `admin` | Everything staff can, plus read the full directory |
| `service` | Read the full directory; for machine accounts like the inventory API |

`role` is never mass-assignable; `/api/register` always creates `staff`. Set roles via `php artisan user:create`.

## Endpoints

All JSON. Send `Accept: application/json`. Authenticated routes need `Authorization: Bearer <token>`.

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/api/register` | none | `name`, `email`, `password`, `password_confirmation`, optional `device_name`. Returns `{token, token_type, user}` |
| POST | `/api/login` | none | `email`, `password`, optional `device_name`. Returns `{token, token_type, user}` |
| POST | `/api/logout` | token | Revokes the token used for the request |
| GET | `/api/user` | token | `{"data": {id, name, email, role, created_at}}` |
| GET | `/api/users?page=N&per_page=50` | admin/service | Paginated `{"data": [...], "links", "meta"}`, `per_page` max 100 |
| GET | `/api/users/{id}` | admin/service/self | `{"data": {...}}`, 404 if missing |

Register and login are rate limited to 10/minute per email + IP.

### Token lifetime

- Login/register tokens expire after `SANCTUM_USER_TOKEN_EXPIRATION` minutes (default 43200 = 30 days).
- `user:token` tokens never expire unless `--expires=<minutes>` is given. To rotate a service token: issue a new one, update `USERS_API_TOKEN` in the inventory API, then delete the old row from `personal_access_tokens`.
- Expired tokens are pruned daily by `sanctum:prune-expired`. This needs the scheduler running (`php artisan schedule:work` locally, or a cron entry for `schedule:run` in production).

The inventory API caches token lookups for `AUTH_CACHE_TTL` seconds, so a token may keep working there for that long after it expires or is revoked here.

## CLI

```bash
php artisan user:create boss@example.com --role=admin [--name="Boss"] [--password=...] [--if-missing]   # generates a password if omitted
php artisan user:token boss@example.com [--name=cli] [--expires=<minutes>]               # prints a new token once
```

## Connecting the inventory API

```bash
php artisan user:token inventory-service@example.com --name=inventory-api
```

In `office-inventory-api/.env`:

```
USERS_API_URL=http://127.0.0.1:8000
USERS_API_TOKEN=<token from above>
AUTH_ADMIN_FIELD=role
AUTH_ADMIN_VALUES=admin
```

Clients log in here, then call the inventory API with the same token.

## Tests

```bash
php artisan test
```

Code style (also run in CI): `vendor/bin/pint --test`, or `vendor/bin/pint` to fix.
