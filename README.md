# KiDas – Dock Appointment System by kiyeog

A warehouse with **10 docks** receives deliveries every day and is approached by carriers for pickups. Without planning, trucks queue at the gate while docks sit idle at other times. KiDas lets suppliers book a time slot, gives the warehouse a daily overview per dock, and makes the status of every handling visible to everyone involved.

Three roles share the system: the **admin** maintains opening hours, shift times and dock usage, releases days for booking and can book, reschedule or cancel at any time. **Warehouse staff** see the daily dock timeline, book pickups and record arrival, start and dock release. **Suppliers** register, book an inbound slot with order number, pallet count, vehicle and driver phone, and follow the live status of their own bookings.

The full concept, the epics and the architecture decisions live in [`docs/kidas.md`](docs/kidas.md).

## Stack

| Part | Choice |
|---|---|
| Backend | Laravel 13, API-first (`/api/v1`) |
| Auth | Sanctum (SPA cookie auth) |
| Frontend | React 19 + Tailwind 4 via Vite, React Router (`resources/js`) |
| Admin panel | Filament 5 (`/admin`, admin role only) |
| Database | PostgreSQL 18 via Laravel Sail |
| Tests | Pest (feature + unit) |
| Quality | Pint, Larastan, GitHub Actions |

## Requirements

- Docker (with Compose v2)
- PHP 8.4+ and Composer (to install dependencies before the first start)

## Setup

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

The application is then available at **http://localhost:8080**.

Ports are set in `.env`: the app uses `APP_PORT=8080` and PostgreSQL is forwarded to host port `FORWARD_DB_PORT=5433`, because 80 and 5432 are often already taken on a development machine. Adjust both values if they clash with something on your system.

Times are stored in UTC and displayed in `Europe/Berlin`, so `APP_TIMEZONE` stays `UTC`.

For development, run `./vendor/bin/sail npm run dev` instead of the build to get Vite's dev server with hot reloading on port 5173. Run it through Sail, not on the host: the container already publishes that port.

Stop the containers with `./vendor/bin/sail down`.

## Frontend

The React application is a single page app served by Laravel and talking to the API only.

```
resources/js/app.jsx            mounts the app
resources/js/router.jsx         client side routes
resources/js/layouts/           shell with the role based navigation
resources/js/navigation.js      maps a role to its navigation items
resources/js/auth/              session context, reads GET /api/v1/me
resources/js/lib/api.js         axios client for /api/v1, Sanctum CSRF handling
resources/js/pages/             pages
```

Laravel serves `resources/views/app.blade.php` for every path that is not `/api`, `/admin`, `/up`, `/storage` or `/build`, so a reload on a client side route works. The API client sends cookies and the `X-XSRF-TOKEN` header; `csrf()` fetches `/sanctum/csrf-cookie` before the first writing request.

Sign-in does not exist yet (E1), so the session context resolves to a guest and the navigation stays empty until then.

## Admin panel

Filament serves the internal panel at `/admin`. Access is restricted to the `admin` role through `canAccessPanel()` on the user model: guests are redirected to `/admin/login`, warehouse staff and suppliers receive a 403. Roles live in `App\Enums\UserRole` (`admin`, `staff`, `supplier`) and are stored in `users.role`, which is deliberately not mass assignable so that a request can never set it.

The panel has no resources yet; those arrive with the master data in E2.

## Tests & code quality

```bash
./vendor/bin/sail artisan test                        # Pest
./vendor/bin/sail php vendor/bin/phpstan analyse      # Larastan
./vendor/bin/pint                                     # code style
```

Tests run with Pest against the `testing` database, which the PostgreSQL container creates on first start. Static analysis runs inside the container because PHPStan needs more memory than a typical local CLI configuration provides.

Every pull request runs on GitHub Actions in three jobs: `Quality` (Pint, Larastan), `Tests` (Pest against a PostgreSQL service, building the frontend first because the SPA view needs the Vite manifest) and `Frontend` (`npm ci` and `npm run build`).

## Project status

The project is built story by story along the plan in [`docs/kidas.md`](docs/kidas.md). Currently finished: **E0-S1** – Laravel scaffold with Sail and PostgreSQL, **E0-S2** – Pest, Pint, Larastan and the CI pipeline, **E0-S3** – the React scaffold, and **E0-S4** – the Filament panel. Authentication (E1) is not in place yet, which is why this README covers the setup only. The full README described in `docs/kidas.md` section 7 follows in E9-S2.
