# Projekt: KiDas – Dock Appointment System
Zeitfenster-Buchung für ein Lager mit 10 Rampen. Projektplan: docs/kidas.md

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
- Keine Kommentare im code