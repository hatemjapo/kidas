# KiDas – Dock Appointment System by kiyeog

A warehouse with **10 docks** receives deliveries every day and is approached by carriers for pickups. Without planning, trucks queue at the gate while docks sit idle at other times. KiDas lets suppliers book a time slot, gives the warehouse a daily overview per dock, and makes the status of every handling visible to everyone involved.

Three roles share the system: the **admin** maintains opening hours, shift times and dock usage, releases days for booking and can book, reschedule or cancel at any time. **Warehouse staff** see the daily dock timeline, book pickups and record arrival, start and dock release. **Suppliers** register, book an inbound slot with order number, pallet count, vehicle and driver phone, and follow the live status of their own bookings.

The full concept, the epics and the architecture decisions live in [`docs/kidas.md`](docs/kidas.md).

## Stack

| Part | Choice |
|---|---|
| Backend | Laravel 13, API-first (`/api/v1`) |
| Auth | Sanctum (SPA cookie auth) |
| Frontend | React + Tailwind via Vite (`resources/js`) |
| Admin panel | Filament (`/admin`, internal only) |
| Database | PostgreSQL 18 via Laravel Sail |
| Tests | Pest (feature + unit) |
| Quality | Pint, Larastan, GitHub Actions |

## Requirements

- Docker (with Compose v2)
- PHP 8.3+ and Composer (to install dependencies before the first start)

## Setup

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

The application is then available at **http://localhost:8080**.

Ports are set in `.env`: the app uses `APP_PORT=8080` and PostgreSQL is forwarded to host port `FORWARD_DB_PORT=5433`, because 80 and 5432 are often already taken on a development machine. Adjust both values if they clash with something on your system.

Times are stored in UTC and displayed in `Europe/Berlin`, so `APP_TIMEZONE` stays `UTC`.

Stop the containers with `./vendor/bin/sail down`.

## Tests & code quality

```bash
./vendor/bin/sail artisan test
./vendor/bin/pint
```

Tests run against the `testing` database, which the PostgreSQL container creates on first start.

## Project status

The project is built story by story along the plan in [`docs/kidas.md`](docs/kidas.md). Currently finished: **E0-S1** – Laravel scaffold with Sail and PostgreSQL. Pest, Larastan and the CI pipeline (E0-S2), the React frontend (E0-S4), the Filament panel (E0-S5) and authentication (E1) are not in place yet, which is why this README covers the setup only. The full README described in `docs/kidas.md` section 7 follows in E9-S2.
