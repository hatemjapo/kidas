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
./vendor/bin/sail artisan db:seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

The application is then available at **http://localhost:8080**.

### Demo logins

`db:seed` fills the database with something to look at: the coming two weeks are released (weekends stay shut), docks 8 to 10 serve pickups, and there are bookings in every state the application knows. Today is laid out around the current hour — a lorry at a dock right now, the next one waiting behind it, one that was handled earlier and one that never turned up — so the warehouse timeline and the live status on a booking have something to show. The password is `password` for all of them:

| Login | Role |
|---|---|
| `admin@kidas.test` | admin: books for suppliers, changes and cancels bookings, keeps the days in the panel |
| `staff@kidas.test` | warehouse: the dock schedule, arrivals, handling, no-shows |
| `nordfracht@kidas.test` | supplier, approved |
| `aalbert@kidas.test` | supplier, approved |
| `neuhaus@kidas.test` | supplier, still waiting for approval — cannot sign in yet |

Seeding again replaces the demo bookings instead of adding a second set, so the data stays current without a fresh database. It only touches what it created itself.

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

### API reference

The endpoints are described in [`docs/openapi.yaml`](docs/openapi.yaml) and served as a Swagger page at **http://localhost:8080/docs**. The file is written by hand rather than generated from annotations, so the controllers stay free of documentation blocks — and a test holds it to the code: every route under `/api/v1` has to appear in the specification with its methods, and nothing may appear there that the application does not serve. Try-it-out is switched off, because the API authenticates with a session cookie and a CSRF header rather than a token.

## Authentication

The SPA authenticates through Sanctum's cookie based session, not through tokens.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/v1/register` | registers a supplier company, status `pending` |
| `POST` | `/api/v1/login` | signs a user in, answers with the user and the role |
| `POST` | `/api/v1/logout` | ends the session |
| `GET` | `/api/v1/me` | the signed in user, `401` for a guest |
| `GET` | `/api/v1/profile` | the supplier's own profile |
| `PATCH` | `/api/v1/profile` | changes company, company phone, contact name and the default driver phone |
| `GET` | `/api/v1/slots` | the days and start times a booking fits into |
| `GET` | `/api/v1/bookings` | the supplier's own bookings, page by page |
| `POST` | `/api/v1/bookings` | books one of those start times |
| `GET` | `/api/v1/bookings/{id}` | one booking with its history, `404` for a foreign one |
| `PATCH` | `/api/v1/bookings/{id}` | moves a booking to another time or pallet count |
| `POST` | `/api/v1/bookings/{id}/cancel` | cancels a booking, reason optional |
| `GET` | `/api/v1/bookings/{id}/status` | whether the dock is clear, shortly before the slot |
| `GET` | `/api/v1/suppliers` | the approved suppliers, for the admin |
| `GET` | `/api/v1/suppliers/{id}/vehicles` | the plates one supplier has saved |
| `POST` | `/api/v1/admin/bookings` | the admin books for a supplier |
| `PATCH` | `/api/v1/admin/bookings/{id}` | the admin changes a booking of any supplier |
| `POST` | `/api/v1/admin/bookings/{id}/cancel` | the admin cancels a booking of any supplier |
| `GET` | `/api/v1/schedule?date=` | the warehouse timeline of one day |
| `POST` | `/api/v1/bookings/{id}/arrival` | the warehouse registers an arrival |
| `PATCH` | `/api/v1/bookings/{id}/arrival` | the warehouse corrects the plate that was typed |
| `POST` | `/api/v1/bookings/{id}/handling` | the warehouse starts the handling |
| `POST` | `/api/v1/bookings/{id}/completion` | the warehouse reports the dock free |
| `POST` | `/api/v1/bookings/{id}/no-show` | the warehouse marks that nobody turned up |
| `POST` | `/api/v1/bookings/{id}/plate-verification` | the warehouse confirms a plate that differed |
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

The admin books on the same page a supplier books on, with one field in front: whom the slot is for. The rest of the form is the one the supplier sees, so a plate can be picked from the ones that supplier has saved or typed anew, and a new one is remembered for them as usual — a vehicle belonging to somebody else is refused. The booking then belongs to that supplier: it stands in their list, they can move or cancel it, and the admin is only recorded as the one who made it.

What the admin is free of is the three hours of lead time; everything else still holds, so a day that was never released stays shut and a delivery still needs an inbound dock. The lead time lives in one place, `LeadTime::getEarliestStartForUser()`, which is also what the slot search asks, so the admin is offered start times a supplier is not. One consequence is worth knowing: a slot the admin books for the next hour is already past the supplier's own deadline, so that supplier can no longer move or cancel it themselves. For those cases the admin can change the booking afterwards: a new start time, another pallet count, or both, with an optional reason that goes into the history next to the old and the new time. It is the same planning as everywhere else — the pallet count sets the duration, so changing only the pallets moves the end of the slot and can land the lorry on another dock. Which is why the whole thing is called a change rather than a move, on both sides. A booking whose lorry has already arrived is not changed by anyone. The same dialog cancels a booking, with the same optional reason, which is kept on the booking and in its history; the slot is free again right away and the booking leaves the timeline.

Both of those reach the supplier by email: one mail names the old and the new time, the dock, the pallets and the reason, the other the slot that is gone and where to book a new one. The old time is read before the booking is replanned, which is the whole point of sending it, and a reason the admin typed is escaped before it goes into the mail. A supplier who changes or cancels their own booking is told nothing — they already know. The mails go out directly rather than through the queue, because no worker runs here yet; making them queued later is one interface on the mailable.

A supplier may keep a default driver phone in their profile. The booking form starts with that number and the supplier
can type another one over it when a different driver comes; the booking then carries the number it was made with, and a
later change to the profile leaves it alone.

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

### The supplier's side in the app

`/book` walks a supplier through it: say whether you deliver or pick up and how many pallets you carry, look at the suggested days with their start times, pick one, add order number, driver phone and a vehicle, and the confirmation names the dock to drive to. If somebody took the slot in the meantime, the answer is the message from the API and the free slots are reloaded — the supplier picks again rather than being moved somewhere automatically.

The detail view of a booking carries the two changes a supplier may make: moving it, which opens the same slot picker and allows a new pallet count, and cancelling it with an optional reason. Both buttons only appear while the booking is merely `booked`, and when the API refuses — too late, or the slot taken — its message is what the page shows.

### The warehouse side in the app

A quarter of an hour before a slot the booking opens up: the detail view starts asking every thirty seconds whether the dock is clear, and answers either that another lorry is still there and roughly until when, or "Your dock is free – you may arrive now". Free means the lorry before has been handled — a booking that is finished lets the next one in although its slot still runs to the quarter hour, while one that is merely booked, arrived or being handled holds the dock. Before that window nothing is asked at all, and once the lorry has arrived the box says where it stands instead of inviting it in. Window, interval and what counts as occupied live in `config/booking.php` and in `Dock::getBookingThatHoldsTheDockNow()`.

`/schedule` is the day of the warehouse: the ten docks as columns, the opening hours of that day running down the left in quarter-hour rows, and every booking as a block in its dock's column. Blocks are tinted by what they are — a delivery or a pickup — and marked by where they stand: being handled, done, or nobody showed up. Cancelled bookings are left out, because the timeline shows what is coming rather than what was called off. Docks that are switched off keep their column, greyed out, so it stays visible that dock 7 is out of service.

Arrows move one day back and forth, and a day that was never released is shown with the opening hours of its weekday and empty columns rather than an error. The arithmetic that turns an instant into a row lives in `resources/js/lib/timeline.js` and is covered by unit tests. Staff and admins may open it; a supplier gets `403`.

Clicking a block opens the booking in a dialog: who is expected, with which plate, and a field for the plate that actually arrived. Registering it sets the booking to `arrived` and keeps the plate that came. If it differs from the booking — compared in normalized form, so `hh-ab 1234` and `HHAB1234` are the same lorry — the booking is flagged, the timeline marks it, and the warehouse has to confirm the plate after checking with the company before anything else happens. The flag stays on the booking afterwards as a record; what was said goes into the history. A plate can also simply have been mistyped, so it stays editable while the lorry waits: correcting it recomputes the flag, and a confirmation given for the earlier plate is withdrawn, because it was about a different lorry. The time of arrival is not touched by a correction.

From there the dialog carries the lorry through: handling starts, and when the dock is free again the booking is done. Handling cannot start while a plate is still in question — that is the point of the flag — and the two steps only exist in the order they happen in the yard. Which step follows which is the enum's business: `BookingStatus::canTransitionTo()` holds the whole map, and anything that is not on it is refused with `422`, so a booking can never be completed without having been handled, nor handled twice. Both steps keep their own time, and both are written into the history. Releasing the dock is asked once more before it is sent, because it ends the booking and a misplaced click should not do that.

When nobody turns up at all, the booking is marked as a no-show — but only once the slot is over, because until then the lorry may still be on its way; the button appears at that moment and is asked back like the release. A no-show frees the slot, so the dock can be given to somebody else, and only a booking that was still awaited can become one: a lorry that has already been at the dock cannot have been absent. The warehouse books nothing: it sees what is coming and records what arrives.

`MAIL_MAILER=log` is the setting for development, so these mails are written to `storage/logs/laravel.log` instead of being delivered. Real delivery needs an SMTP account in `.env`.

### The first admin

`./vendor/bin/sail artisan make:filament-user` creates a user on the role `staff`, the default of the `role` column. To let that user into the panel, set their `role` to `admin` in the database by hand. The role is not mass assignable, so no request and no command can hand it out; the demo seeder in E9-S1 replaces this step.

## Known limitations

KiDas is a demonstration. It is meant to be reachable by anyone, with the demo logins above, so people can click through
it — there is no real company data in it, and that shapes what was built and what was left out.

**What is guarded.** Every writing endpoint carries a rate limit, counted per account and not per address, so one
visitor cannot fill all ten docks for two weeks in a minute or hammer the handling steps; the numbers live in
`config/booking.php`. A test walks the route list and refuses any writing route without a limit, so a new endpoint
cannot quietly slip past it. Signing in and registering keep their own tighter limit. Authorization is pinned down by a
matrix that names every endpoint with the roles that may reach it, together with a test that the matrix and the
registered routes are the same set — a route that nobody assigned a role to fails the build. Mass assignment is limited
to explicit `#[Fillable]` lists, and tests send `status`, `supplier_id`, `dock_id`, `created_by` and `role` along with
honest input to prove none of them arrives. A supplier's approval is checked when they sign in, and the Filament panel
is open to admins alone.

**What is deliberately not done.** There is no password policy beyond Laravel's default of eight characters, no check
against leaked passwords, no two-factor authentication and no audit log of sign-ins: the accounts are shared and
published. The live status tells a supplier until roughly when another lorry occupies their dock — no name, no order
number, but it is information about somebody else's booking. The API reference at `/docs` is public, which is the point
of it. Mails are written to the log rather than sent, since no mail service is configured.

**Before it goes live** (E9-S5): `APP_DEBUG=false` and `APP_ENV=production`, so a stack trace never reaches a visitor;
`SESSION_SECURE_COOKIE=true` behind TLS; `SANCTUM_STATEFUL_DOMAINS` set to the real host; and a mail driver if the
notifications should actually arrive.

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

The project is built story by story along the plan in [`docs/kidas.md`](docs/kidas.md). Currently finished: **E0-S1** – Laravel scaffold with Sail and PostgreSQL, **E0-S2** – Pest, Pint, Larastan and the CI pipeline, **E0-S3** – the React scaffold, **E0-S4** – the Filament panel, **E0-S5** – the frontend tests, **E1-S1** – login and logout over the API, **E1-S2** – the supplier registration, **E1-S3** – the approval in the admin panel, **E1-S4** – roles and policies, **E2-S1** – the docks in the panel, **E2-S2** – the default opening hours, **E2-S3** – the default shift times, **E2-S4** – the day editor, **E2-S5** – the pallet durations, **E4-S1** – the supplier profile, **E4-S2** – the supplier's vehicles, **E5-S1** – the slot calculation, **E5-S2** – the slot suggestion API, **E5-S3** – creating a booking, **E5-S4** – the supplier's own bookings, **E5-S5** – moving a booking, **E5-S6** – cancelling one, **E5-S7** – the booking flow in the app, **E6-S1** – the warehouse timeline, **E6-S2** – registering an arrival, **E6-S3** – handling a lorry and releasing the dock, **E6-S4** – the no-show, **E7-S1** – the admin booking for a supplier, **E7-S2** – the admin changing a booking, **E7-S3** – the admin cancelling one, **E7-S4** – the mails that tell the supplier, **E8-S1** – the live dock status, **E9-S1** – the demo data, **E9-S3** – the API reference, and **E9-S4** – the security review. E8-S2 (real time over WebSockets) was skipped on purpose: polling is enough at this size, and Reverb would add a second process to run. The full README described in `docs/kidas.md` section 7 follows in E9-S2.
