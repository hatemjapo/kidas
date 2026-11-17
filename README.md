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
resources/js/auth/              session context and the route guard
resources/js/lib/api.js         axios client for /api/v1, Sanctum CSRF handling
resources/js/pages/             pages
```

Laravel serves `resources/views/app.blade.php` for every path that is not `/api`, `/admin`, `/up`, `/storage` or `/build`, so a reload on a client side route works. The API client sends cookies and the `X-XSRF-TOKEN` header; `csrf()` fetches `/sanctum/csrf-cookie` before the first writing request.

Every route except `/login` and `/register` sits behind the session: as long as `GET /api/v1/me` answers with 401, the app redirects to the login form. After signing in, the role decides which navigation items appear, and the header offers the way out again.

## Authentication

The SPA authenticates through Sanctum's cookie based session, not through tokens.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/v1/register` | registers a supplier company, status `pending` |
| `POST` | `/api/v1/login` | signs a user in, answers with the user and the role |
| `POST` | `/api/v1/logout` | ends the session |
| `GET` | `/api/v1/me` | the signed in user, `401` for a guest |

Wrong credentials answer with `422` and a field error, so the login form can show it. Both endpoints allow six attempts per minute and IP address.

A supplier registers through `/register` and lands on the status `pending`. Until an admin approves the registration, signing in answers with `403` and the reason; the approval mail carries the link that invites the supplier in. The status and the role are set by the application, never by the request.

## Roles

Three roles live in `App\Enums\UserRole`. Admins work in the Filament panel, warehouse staff and suppliers work in the React app, and all three share the same API.

| | Admin | Warehouse staff | Supplier |
| --- | --- | --- | --- |
| Filament panel | yes | no | no |
| App shell | yes | yes | yes |
| Dock schedule | yes | yes | no |
| Bookings | yes | no | own only |

Three gates keep that apart, each at its own level. `canAccessPanel()` decides who reaches Filament. The `role` middleware closes an API route to foreign roles (`->middleware('role:staff')`), answering `401` for a guest and `403` for the wrong role. Policies decide what somebody may do with a single record; `SupplierPolicy` is the first one, and the panel asks it before offering the decision buttons. Which records a role may see at all — a supplier sees only their own bookings — stays with the policies as well.

`tests/Feature/RouteMatrixTest.php` walks every route that exists today with every role and holds the expected answer, so a new route that forgets its gate shows up there.

In the browser the router repeats the same rules: the navigation only shows what a role may open, and typing a foreign path into the address bar sends the visitor back to the dashboard. The browser side is convenience, not protection — the API decides.

## Admin panel

Filament serves the internal panel at `/admin`. Access is restricted to the `admin` role through `canAccessPanel()` on the user model: guests are redirected to `/admin/login`, warehouse staff and suppliers receive a 403. Roles live in `App\Enums\UserRole` (`admin`, `staff`, `supplier`) and are stored in `users.role`, which is deliberately not mass assignable so that a request can never set it.

Supplier registrations are the first thing the panel manages. The list opens on the open registrations, and an admin approves or rejects each one; both decisions send the supplier an email, the approval one with the link to the sign-in form. A registration can only be decided once, and the panel offers no way to create or edit one — those come in through the API.

The panel also lists the ten docks of the warehouse. They are created by the migration, not by a seeder, because the application is not usable without them; an admin can only switch a dock active or inactive, since numbering and existence belong to the building. Their number and name are not mass assignable.

Opening hours are kept per weekday: open from and to, or closed all day. The migration starts the week at 06:00–18:00 from Monday to Friday with the weekend closed, and an admin changes each day in the panel. A day that would close before it opens is refused twice over, by the form and by a check constraint in the database, because such a day yields no bookable window at all. The times are stored as plain clock times, not as instants, so a daylight saving change never moves them.

`MAIL_MAILER=log` is the setting for development, so these mails are written to `storage/logs/laravel.log` instead of being delivered. Real delivery needs an SMTP account in `.env`.

### The first admin

`./vendor/bin/sail artisan make:filament-user` creates a user on the role `staff`, the default of the `role` column. To let that user into the panel, set their `role` to `admin` in the database by hand. The role is not mass assignable, so no request and no command can hand it out; the demo seeder in E9-S1 replaces this step.

## Tests & code quality

```bash
./vendor/bin/sail artisan test                        # Pest
./vendor/bin/sail npm test                            # Vitest
./vendor/bin/sail php vendor/bin/phpstan analyse      # Larastan
./vendor/bin/pint                                     # code style
```

Tests run with Pest against the `testing` database, which the PostgreSQL container creates on first start. The React side is tested with Vitest and Testing Library; those tests live in `tests/js`. Static analysis runs inside the container because PHPStan needs more memory than a typical local CLI configuration provides.

Every pull request runs on GitHub Actions in three jobs: `Quality` (Pint, Larastan), `Tests` (Pest against a PostgreSQL service, building the frontend first because the SPA view needs the Vite manifest) and `Frontend` (`npm ci`, `npm run build` and `npm test`).

## Project status

The project is built story by story along the plan in [`docs/kidas.md`](docs/kidas.md). Currently finished: **E0-S1** – Laravel scaffold with Sail and PostgreSQL, **E0-S2** – Pest, Pint, Larastan and the CI pipeline, **E0-S3** – the React scaffold, **E0-S4** – the Filament panel, **E0-S5** – the frontend tests, **E1-S1** – login and logout over the API, **E1-S2** – the supplier registration, **E1-S3** – the approval in the admin panel, **E1-S4** – roles and policies, **E2-S1** – the docks in the panel, and **E2-S2** – the default opening hours. The full README described in `docs/kidas.md` section 7 follows in E9-S2.
