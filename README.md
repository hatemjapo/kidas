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
| `GET` | `/api/v1/profile` | the supplier's own profile |
| `PATCH` | `/api/v1/profile` | changes company, company phone and contact name |
| `GET` | `/api/v1/slots` | the days and start times a booking fits into |
| `GET` | `/api/v1/bookings` | the supplier's own bookings, page by page |
| `POST` | `/api/v1/bookings` | books one of those start times |
| `GET` | `/api/v1/bookings/{id}` | one booking with its history, `404` for a foreign one |
| `PATCH` | `/api/v1/bookings/{id}` | moves a booking to another time or pallet count |
| `POST` | `/api/v1/bookings/{id}/cancel` | cancels a booking, reason optional |
| `GET` | `/api/v1/vehicles` | the supplier's saved license plates |
| `POST` | `/api/v1/vehicles` | saves a license plate |
| `DELETE` | `/api/v1/vehicles/{id}` | removes one, `404` for a foreign one |

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

The day is split into an early and a late shift, 06:00–14:00 and 14:00–18:00 by default. The two must not overlap, which no check constraint can see because the rule spans two rows; a validation rule compares a shift against the other one and refuses an overlap. Shifts that merely touch are allowed — an early shift ending at 14:00 and a late one starting at 14:00 leave no gap, because the intervals are half open.

Days are defined one by one, not as a weekly pattern. A month calendar under `/admin/days` marks every day with a green or red dot — released or not — and opening a day shows an editor: the usage of each dock in each shift, the opening times and the shift times of that day. Everything is prefilled from the defaults, every dock starts on inbound, and saving writes the whole day in one go. Once a day is saved, it keeps its own times: a later change to the defaults does not reach it. Days in the past cannot be changed.

How long a booking blocks a dock follows from the number of pallets. Seven tiers cover every load up to the maximum of 70 — up to 10, 20, and so on — and an admin picks the blocked time for each tier from a list that runs in quarter hours up to three hours. The tiers themselves are fixed by the migration, so every pallet count from 1 to 70 always finds its tier. `config/booking.php` holds the maximum.

A supplier keeps their own profile at `/profile` in the app: company, company phone and contact name. The email address stays as it is, because changing a sign-in address properly needs a confirmation on both the old and the new one. Both profile routes carry `role:supplier`, so staff and admins are answered with `403` — an admin works in the panel.

The same page keeps the supplier's license plates. Whatever is typed is reduced to letters and digits in upper case, so `hh-ab 1234` is stored as `HHAB1234` and cannot be saved twice in another spelling. Two suppliers may well keep the same lorry, so the plates are unique per supplier rather than globally. Normalizing happens on the model, not only in the request, because the warehouse will compare a plate against a booking later.

### Finding a slot

A supplier says what they want — inbound or outbound — and how many pallets they carry; the system answers with the days on which that fits and the start times of each. Five days at a time, and only days on which something is actually possible, so a weekend never shows up as an empty tile. The arrows page forward and back from the day on screen.

A start time survives four filters: it lies inside the opening hours **and** inside a shift, the whole booking fits before the shift changes, at least one active dock carries that usage in that shift, and the start is at least three hours away. The grid runs in quarter hours, so a booking ending at 9:30 and one starting at 9:30 are both fine.

The arithmetic runs on instants in UTC while opening hours are wall clock times in `Europe/Berlin`, which is what `config/booking.php` sets. That is why the clock change needs no special case: a window from 01:00 to 05:00 simply holds four fewer quarter hours on the morning the clocks jump forward, and four more when they fall back. `app/Support/SlotGrid.php` does that part without touching the database, so it is covered by unit tests.

`GET /api/v1/slots?type=inbound&pallets=24` is how a supplier asks. The answer carries up to five days, each with its start times as instants in UTC, plus the duration that this load takes. `after` and `before` page forward and back from a date, and a page beyond the last day simply comes back empty rather than as an error. No dock number appears anywhere in the answer, because the dock is assigned when the booking is saved (ADR-5).

### Booking one

`POST /api/v1/bookings` takes the type, the pallets, a start time, an order number, a driver phone and either a saved vehicle or a new plate — which is then remembered for next time. The dock is chosen by the system: the lowest free one that carries this usage in that shift (ADR-5). The answer names it, so the driver knows where to go.

Three layers keep a dock from being booked twice, each with its own job. The slot list leaves out a start time once no dock is free for it, which is fast but only advisory. Saving decides for real, inside a transaction that locks the docks in question. And a Postgres exclusion constraint is the last net for two requests arriving in the same instant:

```sql
EXCLUDE USING gist (dock_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&)
WHERE (status IN ('booked', 'arrived', 'in_progress', 'completed'))
```

The half open range is why a booking ending at 9:30 and one starting at 9:30 are both fine, and the condition is why a cancelled booking stops holding its dock. Whichever layer catches it, the supplier gets `409` and the message that the slot has just been taken, so the app can refresh the list. A start time that was never bookable — outside the opening hours, across a shift change, on an unreleased day, or on a day whose docks all serve the other direction — answers `422` instead.

Every booking starts on the status `booked` and writes its first entry in `booking_events`; the transitions themselves arrive with the warehouse view (ADR-4).

`/bookings` in the app lists what a supplier has booked, the latest first and twenty to a page, and opening one shows its details together with the history from `booking_events` — who moved it to which status, and when. A booking of another supplier answers `404` rather than `403`, because the lookup runs through the supplier's own bookings and foreign ones are not reachable at all.

A booking can be moved to another time, another day or another pallet count, which changes how long it blocks a dock. The new slot has to pass the same rules as a new booking, and the dock is assigned again, so it may change. Moving is only allowed while the booking is merely `booked` and up to three hours before its current slot — `change_deadline_hours` in `config/booking.php`, the same deadline the plan sets for cancelling. Too late or the wrong state answers `403`, an impossible new time `422`, and a time where no dock is free `409`. The move itself goes into the history as a note, so it stays visible that the slot was not the original one.

Cancelling follows the same deadline and the same states, and it is a `POST` to `/cancel` rather than a `DELETE`, because nothing is deleted: the booking keeps its row with the status `cancelled`, the time of the cancellation and an optional reason, and it stays in the supplier's list. The dock becomes free again by itself — `cancelled` is not one of the states that hold a dock, so neither the slot search nor the database constraint counts it any more.

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

The project is built story by story along the plan in [`docs/kidas.md`](docs/kidas.md). Currently finished: **E0-S1** – Laravel scaffold with Sail and PostgreSQL, **E0-S2** – Pest, Pint, Larastan and the CI pipeline, **E0-S3** – the React scaffold, **E0-S4** – the Filament panel, **E0-S5** – the frontend tests, **E1-S1** – login and logout over the API, **E1-S2** – the supplier registration, **E1-S3** – the approval in the admin panel, **E1-S4** – roles and policies, **E2-S1** – the docks in the panel, **E2-S2** – the default opening hours, **E2-S3** – the default shift times, **E2-S4** – the day editor, **E2-S5** – the pallet durations, **E4-S1** – the supplier profile, **E4-S2** – the supplier's vehicles, **E5-S1** – the slot calculation, **E5-S2** – the slot suggestion API, **E5-S3** – creating a booking, **E5-S4** – the supplier's own bookings, **E5-S5** – moving a booking, and **E5-S6** – cancelling one. The full README described in `docs/kidas.md` section 7 follows in E9-S2.
