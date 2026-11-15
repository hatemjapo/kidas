# KiDas – Dock Appointment System

**KiDas** = **Ki** (Label *kiyeog*) + **D**ock **A**ppointment **S**ystem – Zeitfenster-Buchung für Laderampen.
Repo: `kidas`

**Zweck:** Laravel-Anwendung, komplett mit KI-Coding-Agenten gebaut und auf GitHub veröffentlicht. Der KI-gestützte Entwicklungsprozess wird im Repo sichtbar dokumentiert.

> Hinweis fürs README (ganz oben): *„KiDas – Dock Appointment System by kiyeog"* – „Ki" steht für das Label kiyeog, nicht für eine KI-Funktion der Anwendung.

---

## 1. Die Idee (README-Fassung)

> Diesen Abschnitt 1:1 als „Idee" ins README übernehmen.

Ein Lager mit **10 Rampen** erhält täglich Anlieferungen und wird von Speditionen zur Abholung angefahren. Ohne Planung stehen Lkw gleichzeitig vor dem Tor, während Rampen zu anderen Zeiten leer bleiben. **KiDas** lässt Lieferanten ein Zeitfenster buchen, gibt dem Lager eine Tagesübersicht pro Rampe und macht den Status jeder Abfertigung für alle Beteiligten sichtbar.

### Rollen

**Admin** (Admin-Panel)
- Legt Standard-Öffnungszeiten und Standard-Schichtzeiten (Früh-/Spätschicht) fest.
- Überschreibt diese pro Tag, z. B. Feiertag oder Rampe nur bis 12 Uhr verfügbar.
- Legt pro Wochentag, Schicht und Rampe die Nutzung fest (Anlieferung oder Abholung) und kann sie pro Tag ändern. Beispiel: Montag-Frühschicht 7 Rampen Anlieferung / 3 Abholung, Dienstag-Frühschicht 3 Anlieferung / 7 Abholung.
- Gibt Tage zur Buchung frei (höchstens 4 Wochen im Voraus).
- Definiert die Blockzeit nach Palettenanzahl in 15-Minuten-Schritten, z. B. bis 10 Paletten = 15 Min., bis 20 Paletten = 30 Min. (maximal 70 Paletten).
- Schaltet registrierte Lieferanten frei.
- Bucht Anlieferungen und Abholungen, bucht um und storniert jederzeit – der Lieferant wird mit Angabe des Grundes benachrichtigt.

**Lagermitarbeiter**
- Sieht die Kalenderansicht aller Rampen, um die Arbeit vorzubereiten.
- Bucht Abholungen.
- Erfasst die Abfertigung: Ankunft mit Kennzeichen-Prüfung, Beginn, Rampe frei.

**Lieferant**
- Registriert sich, wird vom Admin freigeschaltet und pflegt sein Profil (Firma, Telefon, gespeicherte Kennzeichen).
- Bucht eine Anlieferung mit Bestellnummer, Palettenanzahl, Fahrzeug und Fahrertelefon; das System schlägt passende freie Zeitfenster vor und weist die Rampe zu.
- Sieht ausschließlich eigene Buchungen und deren Live-Status.
- Kann bis 3 Stunden vor dem Termin umbuchen oder stornieren.

### Ablauf einer Abfertigung

`gebucht → angekommen → in Abfertigung → abgeschlossen (Rampe frei)` · Sonderfälle: `storniert`, `nicht erschienen`

- Bei Ankunft trägt der Mitarbeiter das Kennzeichen ein. Weicht es von der Buchung ab, klärt er dies mit der Firma, bevor entladen wird.
- Sobald der Lkw die Rampe verlässt, gibt der Mitarbeiter die Rampe frei. Der Lieferant mit dem nächsten Zeitfenster auf dieser Rampe sieht das und darf früher anfahren; bei Verspätungen wartet er in der Nähe statt am Tor.

### Regeln

- Keine Buchung außerhalb der Öffnungs- und Schichtzeiten; ein Zeitfenster darf nicht über einen Schichtwechsel gehen.
- Keine Überschneidungen auf derselben Rampe; direkt anschließende Zeitfenster (Ende 9:30 / Beginn 9:30) sind erlaubt.
- Nur zukünftige, freigegebene Tage sind buchbar (höchstens 4 Wochen im Voraus).
- Umbuchen und Stornieren durch den Lieferanten nur bis 3 Stunden vor Termin; der Admin darf jederzeit.
- Die Nutzung einer Rampe (Anlieferung/Abholung) ist für eine Schicht nicht mehr änderbar, sobald auf dieser Rampe in dieser Schicht eine Buchung besteht.
- Maximal 70 Paletten pro Buchung.

### Bewusst nicht im Umfang (MVP)

Echtzeit-Push-Benachrichtigungen, Rampen-Eigenschaften (z. B. Kühlung), automatische Kennzeichenerkennung, Anbindung an ERP/WMS, mehrere Standorte, Mehrsprachigkeit.

---

## 2. Architektur & Stack

| Baustein | Wahl | Begründung (fürs README) |
|---|---|---|
| Backend | **Laravel** (aktuelle Version), API-first | Buchungen und Status über eine versionierte API (`/api/v1`) – später nutzbar für Apps oder Integrationen in ERP/WMS |
| Auth | **Sanctum** (SPA-Cookie-Auth; Tokens für Fremdsysteme möglich) | Standard, sicher, kein eigenes Token-Handling im Browser |
| Frontend | **React + Tailwind** (Vite, im selben Repo unter `resources/js`) | eine Codebasis, ein Deployment; Frontend spricht ausschließlich mit der API |
| Admin-Panel | **Filament** | internes Tool → Entwicklungszeit sparen; bewusst *nicht* über die API |
| Datenbank | **PostgreSQL** (via Laravel Sail) | Exclusion Constraint gegen überlappende Buchungen als zweite Sicherheitsebene (s. ADR-3) |
| Tests | **Pest** (Feature + Unit), Postgres auch in CI | Fachregeln sind das Herz → hohe Abdeckung im Domain-Code |
| Qualität | **Pint**, **Larastan** (PHPStan), **GitHub Actions** | jede PR läuft durch Lint + Static Analysis + Tests |
| API-Doku | **Scribe** (Kür) | Headless-Ansatz greifbar machen |
| Zeit | Speicherung in UTC, Anzeige in `Europe/Berlin` | Sommerzeit-Fallen vermeiden |

### Architektur-Entscheidungen (ADRs, kurz)

- **ADR-1 – API-first + Filament:** Externe Nutzer (Lieferant) und Lager arbeiten über die API/React-App. Das Admin-Panel ist intern und nutzt Filament direkt auf den Domain-Services. Beide Wege rufen **dieselben Service-Klassen** auf – Regeln existieren nur einmal.
- **ADR-2 – Freigabe = Snapshot:** Beim Freigeben eines Tages werden Öffnungszeiten, Schichtzeiten und Rampennutzung aus der Wochenvorlage in konkrete Tagesdatensätze kopiert. Spätere Änderungen an der Vorlage betreffen nur noch nicht freigegebene Tage. Dadurch kann eine Vorlagenänderung nie unbemerkt bestehende Buchungen ungültig machen.
- **ADR-3 – Doppelbuchungen:** Buchen läuft in einer DB-Transaktion mit Sperre auf die Rampe (`lockForUpdate`) plus Überlappungsprüfung. Zusätzlich verhindert ein Postgres-Exclusion-Constraint (`EXCLUDE USING gist (ramp_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&)` für aktive Buchungen) Überschneidungen auf DB-Ebene. Halboffene Intervalle `[start, ende)` → direkt anschließende Fenster sind erlaubt.
- **ADR-4 – Status als Enum + Übergangstabelle:** Erlaubte Statusübergänge zentral definiert; jeder Übergang wird in `booking_events` protokolliert (wer, wann, von → nach, Notiz).
- **ADR-5 – Rampe wird vom System zugewiesen:** Lieferant wählt nur die Zeit; die Rampe wird beim Speichern unter Sperre vergeben (niedrigste freie Rampennummer des passenden Typs). Grund: einfachere Bedienung, kein Wettlauf um „Lieblingsrampen".
- **ADR-6 – Konfigurierbare Fristen:** Stornofrist (3 h), Buchungshorizont (4 Wochen) und Max-Paletten (70) liegen in `config/booking.php` – zentral und leicht änderbar.

---

## 3. Datenmodell

| Tabelle | Wichtige Felder |
|---|---|
| `users` | name, email, password, `role` (admin / staff / supplier) |
| `suppliers` | user_id, company_name, company_phone, `status` (pending / approved / rejected), approved_at, approved_by |
| `vehicles` | supplier_id, license_plate (normalisiert: Großbuchstaben, ohne Leer-/Bindestriche) |
| `ramps` | number (1–10), name, is_active |
| `shift_defaults` | shift (early / late), starts_at, ends_at (Uhrzeit) |
| `opening_defaults` | weekday, opens_at, closes_at, is_closed |
| `ramp_usage_templates` | weekday, shift, ramp_id, usage (inbound / outbound) |
| `pallet_rules` | max_pallets, duration_minutes (Vielfaches von 15; Stufen lückenlos bis 70) |
| `calendar_days` | date, is_released, released_at, is_closed, opens_at, closes_at, early_start/end, late_start/end, note |
| `ramp_day_usages` | calendar_day_id, shift, ramp_id, usage – entsteht bei Freigabe (Snapshot) |
| `bookings` | type (inbound / outbound), ramp_id, supplier_id (nullable bei Abholung), carrier_name, order_number, pallets, license_plate, driver_phone, starts_at, ends_at, `status`, created_by, arrived_at, arrived_plate, plate_mismatch, plate_verified_by, started_at, completed_at, cancelled_at, cancel_reason |
| `booking_events` | booking_id, user_id, from_status, to_status, note, created_at |
| `notifications` | Laravel-Standard (Datenbank-Benachrichtigungen) |

**Abgeleitet:** Buchbares Fenster einer Schicht = Schnittmenge aus Öffnungszeit und Schichtzeit des Tages. Slots im 15-Minuten-Raster.

---

## 4. Epics & Stories

Priority: **M** = MVP (must have), **N** = Nice to have. Order = recommended build sequence.

### E0 – Project Setup & AI Workflow
- **E0-S1 (M) Laravel scaffold** – Laravel + Sail + PostgreSQL running locally; `.env.example` complete.
- **E0-S2 (M) Quality pipeline** – Pest, Pint and Larastan set up; GitHub Actions runs all three on every PR (with a Postgres service).
- **E0-S3 (M) React scaffold** – React + Tailwind via Vite in `resources/js`, React Router, role-based navigation layout, API client with Sanctum CSRF handling.
- **E0-S4 (M) Filament scaffold** – Panel at `/admin`, accessible to the admin role only.
- **E0-S5 (N) Frontend tests** – Vitest and Testing Library for `resources/js`; covers the role to navigation mapping, the session context and the API client defaults. *AC:* an unknown role and a guest both produce an empty navigation; CI runs `npm test` on every PR.

### E1 – Authentication & Roles
- **E1-S1 (M) Login/logout via API** – Sanctum SPA authentication; `GET /api/v1/me` returns the user and role. *AC:* invalid credentials → 422; unauthenticated → 401.
- **E1-S2 (M) Supplier registration** – Form (company, company phone, name, email, password) → status `pending`. *AC:* pending/rejected suppliers can log in but cannot book (403 with a clear message).
- **E1-S3 (M) Admin approval** – Filament list of pending registrations; approve/reject; supplier receives an email.
- **E1-S4 (M) Roles & policies** – Middleware/policies for admin, staff and supplier. *AC:* feature tests cover every route with every role (matrix test).

### E2 – Master Data (Admin)
- **E2-S1 (M) Manage docks** – 10 docks via seeder; toggle active/inactive.
- **E2-S2 (M) Default opening hours** – Per weekday: open/closed + from/to.
- **E2-S3 (M) Default shift times** – Early/late shift with from/to. *AC:* shifts must not overlap.
- **E2-S4 (M) Weekly dock usage template** – Matrix weekday × shift × dock → inbound/outbound, quick to toggle.
- **E2-S5 (M) Pallet duration rules** – Tiers "up to X pallets → Y minutes". *AC:* Y is a multiple of 15; tiers cover 1 to 70 without gaps; clear validation errors.

### E3 – Calendar, Release & Exceptions (Admin)
- **E3-S1 (M) Release days** – Admin releases days up to 4 weeks in advance → snapshot of the templates (ADR-2). *AC:* later template changes do not affect released days.
- **E3-S2 (M) Day-specific time overrides** – Change opening/shift times for a single day or close the day (e.g. public holiday). *AC:* a change that would push existing bookings outside the window is rejected and lists the affected bookings.
- **E3-S3 (M) Change dock usage per day/shift** – *AC:* locked as soon as an active booking exists on that dock in that shift; other docks remain editable.

### E4 – Supplier Profile
- **E4-S1 (M) Edit profile** – Company, company phone, contact details.
- **E4-S2 (M) Manage vehicles** – Add/remove license plates; normalization; no duplicates per supplier.

### E5 – Supplier Booking (Core)
- **E5-S1 (M) Slot calculation (service)** – Input: date, type, pallets → duration from pallet rules → available start times on a 15-minute grid across all matching docks. *AC (unit tests!):* respects opening hours ∩ shift times, no slot spanning a shift change, only released future days, back-to-back 9:30/9:30 allowed, DST transitions handled correctly.
- **E5-S2 (M) Slot suggestion API** – `GET /api/v1/slots?date=&pallets=` returns available start times (without dock number).
- **E5-S3 (M) Create booking** – Order number, pallets, vehicle (select or new), driver phone, start time → dock assignment under lock (ADR-3/5). *AC:* concurrency test "two bookings for the last free slot" → exactly one succeeds, the other gets 409.
- **E5-S4 (M) My bookings** – List + detail with status; own bookings only (foreign ID → 404).
- **E5-S5 (M) Reschedule** – New time/pallets; same rules as a new booking; only up to 3 h before the slot.
- **E5-S6 (M) Cancel** – Only up to 3 h before the slot; reason optional.
- **E5-S7 (M) Booking flow in the frontend** – React: date + pallets → pick slot → confirm details → confirmation page.

### E6 – Warehouse View & Handling (Staff)
- **E6-S1 (M) Daily dock timeline** – Docks as rows, time as columns, colored by type and status; switch between days.
- **E6-S2 (M) Book pickup** – Carrier, license plate, phone, pallets → slot on an outbound dock.
- **E6-S3 (M) Register arrival** – Enter license plate; on mismatch the status stays `arrived` and is flagged "plate mismatch"; staff confirms after verifying with the company.
- **E6-S4 (M) Start handling / release dock** – Handling can only start once the plate is verified; "dock free" sets the status to `completed`. *AC:* invalid transitions → 422.
- **E6-S5 (M) No-show** – Can be marked once the slot has passed.

### E7 – Admin Actions & Notifications
- **E7-S1 (M) Admin books** – Inbound or outbound for any supplier, without the 3 h restriction.
- **E7-S2 (M) Admin reschedules** – Different time/dock; reason required; dock type must match.
- **E7-S3 (M) Admin cancels** – Reason required.
- **E7-S4 (M) Notifications** – On E7-S2/S3: database notification + email to the supplier (queued). *AC:* content includes old/new time and the reason.
- **E7-S5 (M) Notifications in the supplier account** – List, read/unread.

### E8 – Live Status for Suppliers
- **E8-S1 (M) Status display** – Booking detail shows the current status and "Your dock is free – you may arrive now" as soon as the previous booking on that dock is completed (polling, e.g. every 30 s).
- **E8-S2 (N) Real-time** – Laravel Reverb/WebSockets instead of polling.

### E9 – Wrap-up & Documentation
- **E9-S1 (M) Demo data** – Seeder: admin, staff, 3 suppliers (one pending), bookings across 2 weeks; demo logins in the README.
- **E9-S2 (M) Complete README**
- **E9-S3 (N) API docs with Scribe**
- **E9-S4 (M) Security review** – Rate limiting on login/registration/booking, authorization matrix test, mass assignment check; results go into "Known limitations".
- **E9-S5 (N) Deployment** – Public demo (e.g. small VPS or Laravel Cloud).

### Future ideas
Dock attributes (e.g. refrigeration) · Utilization statistics per dock/weekday · iCal export for suppliers · Multiple users per supplier company.

### Booking status flow
`booked → arrived → in_progress → completed` · Special cases: `cancelled`, `no_show`

---

## 5. Review-Checkliste für KI-Code (pro PR)

- [ ] Verstehe ich jede Zeile? Könnte ich sie erklären und ändern?
- [ ] Fachregel korrekt? (Grenzen: 9:30/9:30, 3 h genau, 70 Paletten, Schichtwechsel)
- [ ] Autorisierung: Kann ein anderer Lieferant / eine andere Rolle das aufrufen?
- [ ] Validierung über Form Request, keine ungeprüften Eingaben, kein `$request->all()` in `create()`
- [ ] Zeit: UTC gespeichert, Zeitzone bewusst, Carbon nicht mutierend missbraucht
- [ ] Nebenläufigkeit: Transaktion/Sperre wo nötig
- [ ] N+1-Queries? (Eager Loading)
- [ ] Tests testen das Verhalten – nicht nur, dass der Code läuft? Gibt es Negativtests?
- [ ] Hat die KI Pakete, Dateien oder „Verbesserungen" hinzugefügt, die nicht beauftragt waren?
- [ ] Erfundene APIs/Methoden? (Laravel-/Filament-Doku gegenprüfen)

---

## 6. Vorlage `CLAUDE.md` (im Repo-Root)

```markdown
# Projekt: KiDas – Dock Appointment System
Zeitfenster-Buchung für ein Lager mit 10 Rampen. Fachliche Beschreibung: README.md → "Idee".
("Ki" = Label kiyeog, keine KI-Funktion der Anwendung.)

## Stack
Laravel (API-first, /api/v1, Sanctum SPA-Auth) · React + Tailwind (resources/js) ·
Filament (/admin, nur intern) · PostgreSQL · Pest · Pint · Larastan

## Architekturregeln
- Fachlogik ausschließlich in app/Services bzw. app/Actions – nie in Controllern,
  Filament-Resources oder React. Filament und API rufen dieselben Services auf.
- Eingaben nur über Form Requests; Ausgaben nur über API Resources.
- Autorisierung über Policies; jede neue Route bekommt einen Rollen-Test.
- Zeiten in UTC speichern, Anzeige Europe/Berlin. Intervalle sind halboffen [start, end).
- Statusübergänge nur über BookingStatus::canTransitionTo(); jeder Übergang → booking_events.
- Fristen/Limits aus config/booking.php, keine Magic Numbers.

## Arbeitsweise
- Arbeite nur an der Story, die im Auftrag genannt ist (z. B. E5-S3). Keine ungefragten Refactorings,
  keine neuen Composer/NPM-Pakete ohne Rückfrage.
- Erst Plan vorlegen (Dateien, Ansatz, Tests), dann umsetzen.
- Zu jeder Fachregel Tests schreiben, inkl. Grenzfällen und Negativfällen.
- Vor Abschluss: ./vendor/bin/pint, ./vendor/bin/phpstan, ./vendor/bin/pest – alles grün.
- Unsicherheit offen benennen statt raten.
```

---

## 7. README-Struktur

1. **Titel** *„KiDas – Dock Appointment System by kiyeog"* + Kurzbeschreibung + Screenshot/GIF der Tagesansicht
2. **Idee** (Abschnitt 1)
3. **Setup** – Sail-Befehle, Seeder, Demo-Logins
4. **KI-Tools** – z. B. Claude Code als Hauptagent; wofür welches Tool eingesetzt wurde
5. **Vorgehen** – wie die Agenten geführt wurden (Plan → Umsetzung → Review → Tests); Verweis auf `docs/stories/`
6. **Architekturentscheidungen** – ADR-1 bis ADR-6 (Abschnitt 2), je 2–3 Sätze
7. **Qualitätssicherung** – CI-Pipeline, Testabdeckung der Domäne, Review-Checkliste
8. **Wo die KI falsch lag** – 3–5 echte Beispiele: Was hat sie gebaut, warum war es falsch, wie wurde es gefunden, wie behoben (aus `docs/ai-findings.md`)
9. **Bekannte Einschränkungen** – Abschnitt 1 „nicht im Umfang" + Ergebnisse aus E9-S4
10. **Ausblick**

**Wahrscheinliche KI-Fehlerquellen** (gezielt beobachten): Überlappungsprüfung mit `<=` statt `<` · Prüfung ohne Sperre (Race Condition) · Zeitzonen/Sommerzeit · Policy vergessen → fremde Buchung abrufbar · Tests, die nur den Happy Path prüfen · erfundene Filament-Methoden.

---

## 8. Demo-Ablauf (ca. 5 Min.)

Admin gibt Tag frei → Lieferant bucht → Lager-Zeitstrahl → Ankunft mit falschem Kennzeichen → Rampe frei → nächster Lieferant sieht „früher anfahren" → Admin bucht um, Lieferant bekommt Meldung.

---

## 9. Zeitplan (grob)

| Woche | Inhalt |
|---|---|
| 1 | E0, E1, E2 |
| 2 | E3, E4, E5 (Kern + Tests) |
| 3 | E6, E7, E8-S1 |
| 4 | E9, Puffer |
