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

Priorität: **M** = MVP (muss), **K** = Kür. Reihenfolge = empfohlene Bauweise.

### E0 – Projekt-Setup & KI-Workflow
- **E0-S1 (M) Laravel-Grundgerüst** – Laravel + Sail + PostgreSQL läuft lokal; `.env.example` vollständig.
- **E0-S2 (M) Qualitäts-Pipeline** – Pest, Pint, Larastan eingerichtet; GitHub Actions führt alle drei bei jedem PR aus (mit Postgres-Service).
- **E0-S3 (M) KI-Leitplanken** – `CLAUDE.md` (Abschnitt 6), `docs/stories/`, `docs/ai-findings.md`, `docs/adr/` angelegt.
- **E0-S4 (M) React-Grundgerüst** – React + Tailwind via Vite unter `resources/js`, React Router, Layout mit Navigation je Rolle, API-Client mit Sanctum-CSRF.
- **E0-S5 (M) Filament-Grundgerüst** – Panel unter `/admin`, nur für Rolle admin erreichbar.

### E1 – Authentifizierung & Rollen
- **E1-S1 (M) Login/Logout über API** – Sanctum-SPA-Auth; `GET /api/v1/me` liefert Nutzer + Rolle. *AK:* falsche Daten → 422; nicht eingeloggt → 401.
- **E1-S2 (M) Lieferanten-Registrierung** – Formular (Firma, Firmentelefon, Name, E-Mail, Passwort) → Status `pending`. *AK:* pending/rejected-Lieferanten können sich einloggen, aber nicht buchen (403 mit verständlicher Meldung).
- **E1-S3 (M) Freischaltung durch Admin** – Filament-Liste offener Registrierungen; freischalten/ablehnen; Lieferant erhält E-Mail.
- **E1-S4 (M) Rollen & Policies** – Middleware/Policies für admin, staff, supplier. *AK:* Feature-Tests decken jede Route mit jeder Rolle ab (Matrix-Test).

### E2 – Stammdaten (Admin)
- **E2-S1 (M) Rampen verwalten** – 10 Rampen per Seeder; aktiv/inaktiv schalten.
- **E2-S2 (M) Standard-Öffnungszeiten** – pro Wochentag offen/geschlossen + von/bis.
- **E2-S3 (M) Standard-Schichtzeiten** – Früh/Spät mit von/bis. *AK:* Schichten überschneiden sich nicht.
- **E2-S4 (M) Wochenvorlage Rampennutzung** – Matrix Wochentag × Schicht × Rampe → Anlieferung/Abholung, schnell klickbar.
- **E2-S5 (M) Palettenregeln** – Stufen „bis X Paletten → Y Minuten". *AK:* Y ist Vielfaches von 15; Stufen lückenlos von 1 bis 70; Validierungsfehler verständlich.

### E3 – Kalender, Freigabe & Ausnahmen (Admin)
- **E3-S1 (M) Tage freigeben** – Admin gibt Tage bis max. 4 Wochen im Voraus frei → Snapshot aus Vorlagen (ADR-2). *AK:* Vorlagenänderung danach ändert freigegebene Tage nicht.
- **E3-S2 (M) Tagesausnahme Zeiten** – Öffnungs-/Schichtzeiten eines Tages ändern oder Tag schließen (z. B. Feiertag). *AK:* Änderung, die bestehende Buchungen aus dem Fenster wirft, wird abgelehnt und nennt die betroffenen Buchungen.
- **E3-S3 (M) Rampennutzung pro Tag/Schicht ändern** – *AK:* gesperrt, sobald auf dieser Rampe in dieser Schicht eine aktive Buchung existiert; andere Rampen bleiben änderbar.

### E4 – Lieferanten-Profil
- **E4-S1 (M) Profil bearbeiten** – Firma, Firmentelefon, Kontakt.
- **E4-S2 (M) Fahrzeuge verwalten** – Kennzeichen anlegen/löschen; Normalisierung; keine Duplikate pro Lieferant.

### E5 – Buchung durch den Lieferanten (Kern)
- **E5-S1 (M) Slot-Berechnung (Service)** – Eingabe: Datum, Typ, Paletten → Dauer aus Palettenregeln → freie Startzeiten im 15-Min-Raster über alle passenden Rampen. *AK (Unit-Tests!):* respektiert Öffnungs- ∩ Schichtzeit, kein Fenster über Schichtwechsel, nur freigegebene Zukunftstage, Grenze 9:30/9:30 erlaubt, Sommerzeit-Umstellung korrekt.
- **E5-S2 (M) Slot-Vorschlag API** – `GET /api/v1/slots?date=&pallets=` liefert freie Startzeiten (ohne Rampennummer).
- **E5-S3 (M) Buchung anlegen** – Bestellnummer, Paletten, Fahrzeug (Auswahl oder neu), Fahrertelefon, Startzeit → Rampenzuweisung unter Sperre (ADR-3/5). *AK:* paralleler Test „zwei Buchungen auf den letzten freien Slot" → genau eine gewinnt, die andere erhält 409.
- **E5-S4 (M) Meine Buchungen** – Liste + Detail mit Status; ausschließlich eigene (fremde ID → 404).
- **E5-S5 (M) Umbuchen** – neue Zeit/Paletten; gleiche Regeln wie Neubuchung; nur bis 3 h vorher.
- **E5-S6 (M) Stornieren** – nur bis 3 h vorher; Grund optional.
- **E5-S7 (M) Buchungsstrecke im Frontend** – React: Datum + Paletten → Slots wählen → Daten bestätigen → Bestätigungsseite.

### E6 – Lager-Ansicht & Abfertigung (Mitarbeiter)
- **E6-S1 (M) Tagesansicht Rampen-Zeitstrahl** – Rampen als Zeilen, Uhrzeit als Spalten, Farbe nach Typ und Status; Tageswechsel.
- **E6-S2 (M) Abholung buchen** – Spedition, Kennzeichen, Telefon, Paletten → Slot auf Abholrampe.
- **E6-S3 (M) Ankunft erfassen** – Kennzeichen eingeben; Abweichung → Status bleibt „angekommen" mit Markierung „Kennzeichen abweichend"; Mitarbeiter bestätigt nach Klärung mit der Firma.
- **E6-S4 (M) Abfertigung starten / Rampe freigeben** – Start nur nach geklärtem Kennzeichen; „Rampe frei" setzt `abgeschlossen`. *AK:* unerlaubte Übergänge → 422.
- **E6-S5 (M) Nicht erschienen** – nach Ablauf des Zeitfensters markierbar.

### E7 – Admin-Eingriffe & Benachrichtigungen
- **E7-S1 (M) Admin bucht** – Anlieferung oder Abholung für beliebige Lieferanten, ohne 3-h-Frist.
- **E7-S2 (M) Admin bucht um** – andere Zeit/Rampe; Pflichtfeld Grund; Rampentyp muss passen.
- **E7-S3 (M) Admin storniert** – Pflichtfeld Grund.
- **E7-S4 (M) Benachrichtigungen** – bei E7-S2/S3 Datenbank-Benachrichtigung + E-Mail an Lieferanten (Queue). *AK:* Inhalt enthält alte/neue Zeit und Grund.
- **E7-S5 (M) Meldungen im Lieferanten-Konto** – Liste, gelesen/ungelesen.

### E8 – Live-Status für den Lieferanten
- **E8-S1 (M) Status-Anzeige** – Buchungsdetail zeigt aktuellen Status und „Ihre Rampe ist frei – Sie können anfahren", sobald die vorherige Buchung auf dieser Rampe abgeschlossen ist (Polling, z. B. alle 30 s).
- **E8-S2 (K) Echtzeit** – Laravel Reverb/WebSockets statt Polling.

### E9 – Abschluss & Dokumentation
- **E9-S1 (M) Demo-Daten** – Seeder: Admin, Mitarbeiter, 3 Lieferanten (einer pending), Buchungen über 2 Wochen; Demo-Logins im README.
- **E9-S2 (M) README vollständig** – Struktur aus Abschnitt 7.
- **E9-S3 (K) API-Doku mit Scribe**
- **E9-S4 (M) Security-Durchsicht** – Rate Limiting auf Login/Registrierung/Buchen, Autorisierungs-Matrix-Test, Mass-Assignment prüfen; Ergebnis in „Bekannte Einschränkungen".
- **E9-S5 (K) Deployment** – öffentliche Demo (z. B. kleiner VPS oder Laravel Cloud).

### Kür-Ideen danach
Rampen-Eigenschaften (Kühlung) · Auslastungsstatistik pro Rampe/Wochentag · iCal-Export für Lieferanten · Mehrere Benutzer pro Lieferantenfirma.

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