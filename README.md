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

Seeded accounts (password `password`, local only): `admin@example.com` (admin), `staff@example.com` (staff), `inventory-service@example.com` (service), plus 5 random staff.

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

## CLI

```bash
php artisan user:create boss@example.com --role=admin [--name="Boss"] [--password=...]   # generates a password if omitted
php artisan user:token boss@example.com [--name=cli]                                    # prints a new token once
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
