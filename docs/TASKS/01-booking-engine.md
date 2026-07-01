# Lounge oben (KAMC e.V.) — Aufgabe: Buchungs-Engine + availability.php

## Rolle
Senior PHP-Entwickler. Stack: PHP 8.x + PDO, MySQL/MariaDB, Tailwind, Alpine.js,
Strato Shared Hosting. Kein Framework. Sprache: Deutsch, technische Erklärungen erwünscht.

## Zuerst lesen (in dieser Reihenfolge)
1. docs/Briefing-Dachterrassenbuchung.md  — kanonische Projektbeschreibung, Domänenregeln, Flows
2. migrations/001_initial_schema.sql       — Datenmodell (bookings.slot, settings, opening_hours, blackouts)
3. src/db.php                              — bestehende PDO-Verbindung (UTC-Session!)
4. README.md                              — Setup & Deploy
Bei Widersprüchen gewinnt das Briefing. Im Zweifel nachfragen statt raten.

## Domänenregeln (die wichtigsten)
- Zwei feste Slots pro Tag, je 1x buchbar: TAG = Öffnung–18:00, ABEND = 18:00–Schließung (02:00 Folgetag).
- Max. 16 Personen. Vorlauf >= 24 h vor Slot-Beginn. Datum <= settings.booking_window_end.
- Jede Buchung: Hausordnung-Zustimmung (Version + Zeitpunkt) ist Pflicht.
- Ablauf: create -> immer 'pending' (Soft-Hold, blockt den Slot) -> Hafenmeister entscheidet confirmed/rejected.
- DB speichert UTC (SET time_zone='+00:00'). Umrechnung Europe/Berlin <-> UTC NUR an der I/O-Grenze
  via DateTime/DateTimeZone (Sommer-/Winterzeit beachten).
- Verfügbarkeit IMMER serverseitig berechnen — das Frontend ist nie Source of Truth.

## Aufgabe
Baue die Buchungs-Engine als reine, testbare PHP-Schicht in src/, DANN den Endpoint. Engine-first, UI kommt später.

1) src/slots.php — reine Funktionen (keine Superglobals, keine Ausgabe):
   - slot_bounds(string $localDate, string $slot): array{start_utc, end_utc}
       Nutzt opening_hours (open/close des Wochentags) + settings.evening_start_local (18:00).
       Abend läuft über Mitternacht (Ende = Folgetag close_time). Rückgabe als UTC 'Y-m-d H:i:s'.
   - is_slot_free(PDO $pdo, string $localDate, string $slot): bool
       true, wenn KEINE aktive (pending|confirmed) Buchung für (booking_date, slot) existiert.
   - day_status(PDO $pdo, string $localDate): array{tag: string, abend: string}
       Status je Slot in { frei | belegt | blackout | geschlossen | vergangen }
       (Vorlauf 24 h, Buchungsfenster, opening_hours.is_closed, blackouts berücksichtigen).
   - validate_booking_input(array $in): array   // party_size 1..16, Hausordnung akzeptiert,
                                                 // Datum/Slot gültig -> Liste von Fehlern (leer = ok)

2) src/booking.php — Schreibpfad:
   - create_booking(PDO $pdo, array $data): int|array
       Serialisiert über GET_LOCK('booking_terrasse', 10):
         validate -> is_slot_free? -> slot_bounds -> INSERT als 'pending'
         (booking_date, slot, start_utc, end_utc, party_size, purpose,
          hausordnung_accepted_at, hausordnung_version) -> RELEASE_LOCK.
       Belegter Slot/Fehler -> Fehler zurück, kein INSERT. RELEASE_LOCK IMMER (try/finally).

3) public/availability.php — GET-Endpoint, JSON:
   - ?from=Y-m-d&to=Y-m-d -> [{date, tag: status, abend: status}, …] für den Kalender.
   - Nur PDO Prepared Statements. Kein HTML. Content-Type: application/json.

## Prinzipien
- Durchgängig PDO Prepared Statements, nie SQL zusammenstringen.
- Logik in src/ kapseln (typisierte Funktionen), nicht in Endpoints/Templates.
- config.php liegt außerhalb des Webroots und ist gitignored — nie committen, nie ausgeben.
- Kleine Commits auf einem Branch (z. B. feature/booking-engine), sprechende Messages.

## Tests (vor der UI)
Lege tests/slots_test.php an (reines PHP, assert(), ohne Abhängigkeiten, via `php tests/slots_test.php` lauffähig):
- slot_bounds Tag vs. Abend inkl. Mitternachts-Übergang und Europe/Berlin->UTC (auch DST-Wechsel).
- Doppelbuchung: zweite Buchung auf dasselbe (Datum, Slot) wird abgelehnt.
- Vorlauf < 24 h und Datum > booking_window_end werden abgelehnt.

## Nicht im Scope (jetzt)
Auth-UI, E-Mail-Versand, Abnahme, Admin-Seiten, Design-Tokens. Nur Engine + availability.php.

## Definition of Done
- src/slots.php, src/booking.php, public/availability.php implementiert.
- tests/slots_test.php grün (`php tests/slots_test.php` -> alle Assertions ok).
- Kurzer Abschnitt in README, wie man die Tests startet.
