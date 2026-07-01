<?php
declare(strict_types=1);

/**
 * Engine-Tests für die Buchungslogik (KAMC „Lounge oben").
 *
 * Reines PHP, keine Abhängigkeiten (kein PHPUnit/Composer). Start:
 *
 *     php tests/slots_test.php
 *
 * Die DB-gebundenen Tests laufen gegen eine In-Memory-SQLite — kein laufendes
 * MySQL nötig. Die Engine nutzt portable Prepared Statements; die einzige
 * MySQL-Spezialität (GET_LOCK) ist in create_booking() hinter einem Adapter
 * gekapselt und auf SQLite ein No-Op. Produktiv läuft alles auf MySQL/MariaDB.
 *
 * Referenzzeit ("now") wird überall injiziert -> deterministisch, kein Bezug
 * auf die Systemuhr.
 */

// Assertions scharf schalten (CLI hat sie oft aus).
ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');
error_reporting(E_ALL);

require __DIR__ . '/../src/slots.php';
require __DIR__ . '/../src/booking.php';

/* ---------------------------------------------------------------- *
 * Mini-Test-Harness (assert()-basiert)                             *
 * ---------------------------------------------------------------- */
$GLOBALS['__tests'] = 0;
$GLOBALS['__fails'] = 0;

function eq(mixed $actual, mixed $expected, string $msg): void
{
    $GLOBALS['__tests']++;
    try {
        assert(
            $actual === $expected,
            $msg . ' | erwartet: ' . var_export($expected, true) . ' | bekommen: ' . var_export($actual, true)
        );
        echo "  ok  {$msg}\n";
    } catch (AssertionError $e) {
        $GLOBALS['__fails']++;
        echo "  FAIL {$e->getMessage()}\n";
    }
}

function truthy(mixed $cond, string $msg): void { eq((bool) $cond, true, $msg); }
function section(string $title): void { echo "\n== {$title} ==\n"; }

/** Frische In-Memory-SQLite mit portablem Schema + Seed. */
function make_db(array $settings = []): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(<<<SQL
        CREATE TABLE bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id INTEGER NOT NULL,
            booking_date TEXT NOT NULL,
            slot TEXT NOT NULL,
            start_utc TEXT NOT NULL,
            end_utc TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            party_size INTEGER NOT NULL,
            purpose TEXT,
            hausordnung_accepted_at TEXT NOT NULL,
            hausordnung_version TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT '1970-01-01 00:00:00'
        )
        SQL);
    $pdo->exec(<<<SQL
        CREATE TABLE settings (
            id INTEGER PRIMARY KEY,
            evening_start_local TEXT NOT NULL,
            lead_time_hours INTEGER NOT NULL,
            pending_expiry_hours INTEGER NOT NULL,
            max_party_size INTEGER NOT NULL,
            booking_window_end TEXT
        )
        SQL);
    $pdo->exec(<<<SQL
        CREATE TABLE opening_hours (
            weekday INTEGER PRIMARY KEY,
            open_time TEXT NOT NULL,
            close_time TEXT NOT NULL,
            is_closed INTEGER NOT NULL DEFAULT 0
        )
        SQL);
    $pdo->exec(<<<SQL
        CREATE TABLE blackouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            blackout_date TEXT NOT NULL UNIQUE,
            reason TEXT
        )
        SQL);

    $s = array_merge([
        'evening_start_local'  => '18:00:00',
        'lead_time_hours'      => 24,
        'pending_expiry_hours' => 48,
        'max_party_size'       => 16,
        'booking_window_end'   => '2026-10-31',
    ], $settings);

    $stmt = $pdo->prepare(
        'INSERT INTO settings (id, evening_start_local, lead_time_hours, pending_expiry_hours, max_party_size, booking_window_end)
         VALUES (1, :ev, :lead, :exp, :max, :win)'
    );
    $stmt->execute([
        ':ev'   => $s['evening_start_local'],
        ':lead' => $s['lead_time_hours'],
        ':exp'  => $s['pending_expiry_hours'],
        ':max'  => $s['max_party_size'],
        ':win'  => $s['booking_window_end'],
    ]);

    // Alle Wochentage 08:00 -> 02:00 (Folgetag), offen.
    $oh = $pdo->prepare('INSERT INTO opening_hours (weekday, open_time, close_time, is_closed) VALUES (:w, "08:00:00", "02:00:00", 0)');
    for ($w = 1; $w <= 7; $w++) {
        $oh->execute([':w' => $w]);
    }

    return $pdo;
}

/** Gültiger Basis-Eingabesatz für create_booking(). */
function valid_input(array $override = []): array
{
    return array_merge([
        'member_id'            => 1,
        'booking_date'         => '2026-08-01',
        'slot'                 => 'tag',
        'party_size'           => 8,
        'purpose'              => 'Geburtstag',
        'hausordnung_accepted' => true,
        'hausordnung_version'  => '2026-1',
    ], $override);
}

$utc = new DateTimeZone('UTC');


/* ================================================================ *
 * 1) slot_bounds / compute_slot_bounds — Zeitzonen, DST, Mitternacht
 * ================================================================ */
section('compute_slot_bounds — Sommerzeit (CEST, UTC+2)');
// 2026-07-15: Europe/Berlin = UTC+2
$sTag = compute_slot_bounds('2026-07-15', 'tag', '08:00:00', '02:00:00', '18:00:00');
eq($sTag['start_utc'], '2026-07-15 06:00:00', 'Tag-Start 08:00 CEST -> 06:00 UTC');
eq($sTag['end_utc'],   '2026-07-15 16:00:00', 'Tag-Ende 18:00 CEST -> 16:00 UTC');

$sAbend = compute_slot_bounds('2026-07-15', 'abend', '08:00:00', '02:00:00', '18:00:00');
eq($sAbend['start_utc'], '2026-07-15 16:00:00', 'Abend-Start 18:00 CEST -> 16:00 UTC');
eq($sAbend['end_utc'],   '2026-07-16 00:00:00', 'Abend-Ende 02:00 Folgetag CEST -> 00:00 UTC (Mitternachts-Übergang)');

section('compute_slot_bounds — Winterzeit (CET, UTC+1)');
// 2026-01-15: Europe/Berlin = UTC+1
$wTag = compute_slot_bounds('2026-01-15', 'tag', '08:00:00', '02:00:00', '18:00:00');
eq($wTag['start_utc'], '2026-01-15 07:00:00', 'Tag-Start 08:00 CET -> 07:00 UTC');
eq($wTag['end_utc'],   '2026-01-15 17:00:00', 'Tag-Ende 18:00 CET -> 17:00 UTC');

$wAbend = compute_slot_bounds('2026-01-15', 'abend', '08:00:00', '02:00:00', '18:00:00');
eq($wAbend['start_utc'], '2026-01-15 17:00:00', 'Abend-Start 18:00 CET -> 17:00 UTC');
eq($wAbend['end_utc'],   '2026-01-16 01:00:00', 'Abend-Ende 02:00 Folgetag CET -> 01:00 UTC (Mitternachts-Übergang)');

section('DST-Nachweis: gleicher Wandzeit-Slot, unterschiedlicher UTC-Offset');
// Sommer-Abend endet 00:00 UTC, Winter-Abend endet 01:00 UTC -> Offset differiert um 1h.
truthy($sAbend['start_utc'] !== $wAbend['start_utc'], 'Abend-Start Sommer != Winter (DST greift)');

section('slot_bounds über DB (SQLite, geseedete Öffnungszeiten)');
$db = make_db();
$b  = slot_bounds($db, '2026-07-15', 'abend');
eq($b['start_utc'], '2026-07-15 16:00:00', 'slot_bounds liest Öffnungszeiten aus DB (Start)');
eq($b['end_utc'],   '2026-07-16 00:00:00', 'slot_bounds liest Öffnungszeiten aus DB (Ende, Mitternacht)');


/* ================================================================ *
 * 2) validate_booking_input — reine Eingabeprüfung
 * ================================================================ */
section('validate_booking_input');
eq(validate_booking_input(valid_input()), [], 'gültige Eingabe -> keine Fehler');
truthy(validate_booking_input(valid_input(['party_size' => 0])) !== [],  'party_size 0 -> Fehler');
truthy(validate_booking_input(valid_input(['party_size' => 17])) !== [], 'party_size 17 (>16) -> Fehler');
truthy(validate_booking_input(valid_input(['hausordnung_accepted' => false])) !== [], 'Hausordnung nicht bestätigt -> Fehler');
truthy(validate_booking_input(valid_input(['booking_date' => '2026-13-40'])) !== [], 'unmögliches Datum -> Fehler');
truthy(validate_booking_input(valid_input(['slot' => 'mittag'])) !== [], 'ungültiger Slot -> Fehler');


/* ================================================================ *
 * 3) Doppelbuchung — zweite Buchung auf (Datum, Slot) wird abgelehnt
 * ================================================================ */
section('Doppelbuchung wird abgelehnt');
$db  = make_db();
$now = new DateTimeImmutable('2026-07-01 00:00:00', $utc); // weit vor dem Termin -> Vorlauf ok

$first = create_booking($db, valid_input(['booking_date' => '2026-08-01', 'slot' => 'tag']), $now);
truthy(is_int($first), 'erste Buchung erfolgreich (liefert ID)');

$second = create_booking($db, valid_input(['booking_date' => '2026-08-01', 'slot' => 'tag']), $now);
truthy(is_array($second), 'zweite Buchung auf denselben Slot -> Fehler-Array (kein INSERT)');

// Nur EINE aktive Buchung in der DB.
$cnt = (int) $db->query("SELECT COUNT(*) FROM bookings WHERE booking_date='2026-08-01' AND slot='tag'")->fetchColumn();
eq($cnt, 1, 'genau eine Buchung wurde angelegt');

// Anderer Slot am selben Tag bleibt buchbar.
$abend = create_booking($db, valid_input(['booking_date' => '2026-08-01', 'slot' => 'abend']), $now);
truthy(is_int($abend), 'Abend-Slot am selben Tag ist unabhängig buchbar');


/* ================================================================ *
 * 4) Vorlauf < 24 h wird abgelehnt
 * ================================================================ */
section('Vorlauf < 24 h wird abgelehnt');
$db  = make_db();
// now = Termintag 00:00 UTC; Tag-Slot beginnt 06:00 UTC desselben Tages -> nur ~6h Vorlauf.
$now = new DateTimeImmutable('2026-07-11 00:00:00', $utc);
$res = create_booking($db, valid_input(['booking_date' => '2026-07-11', 'slot' => 'tag']), $now);
truthy(is_array($res), 'Buchung < 24 h vor Beginn -> Fehler-Array');
eq((int) $db->query('SELECT COUNT(*) FROM bookings')->fetchColumn(), 0, 'kein INSERT bei Vorlauf-Verletzung');

// Gegenprobe: knapp > 24 h Vorlauf -> ok.
$db   = make_db();
$now  = new DateTimeImmutable('2026-07-10 05:00:00', $utc); // Tag-Slot 2026-07-11 beginnt 06:00 UTC -> 25h Vorlauf
$okLead = create_booking($db, valid_input(['booking_date' => '2026-07-11', 'slot' => 'tag']), $now);
truthy(is_int($okLead), 'Gegenprobe: > 24 h Vorlauf -> Buchung erfolgreich');


/* ================================================================ *
 * 5) Datum > booking_window_end wird abgelehnt
 * ================================================================ */
section('Datum außerhalb Buchungsfenster wird abgelehnt');
$db  = make_db(); // window_end = 2026-10-31
$now = new DateTimeImmutable('2026-07-01 00:00:00', $utc);
$res = create_booking($db, valid_input(['booking_date' => '2026-11-05', 'slot' => 'tag']), $now);
truthy(is_array($res), 'Datum nach booking_window_end -> Fehler-Array');
eq((int) $db->query('SELECT COUNT(*) FROM bookings')->fetchColumn(), 0, 'kein INSERT außerhalb des Fensters');


/* ================================================================ *
 * 6) Happy Path — korrekte Anlage als 'pending' inkl. UTC-Grenzen
 * ================================================================ */
section('Happy Path: Anlage als pending mit korrekten UTC-Grenzen');
$db  = make_db();
$now = new DateTimeImmutable('2026-07-01 00:00:00', $utc);
$id  = create_booking($db, valid_input(['booking_date' => '2026-08-01', 'slot' => 'abend', 'party_size' => 12]), $now);
truthy(is_int($id), 'Buchung erfolgreich (ID)');

$row = $db->query('SELECT * FROM bookings WHERE id = ' . (int) $id)->fetch();
eq($row['status'], 'pending', 'Status ist pending (Soft-Hold)');
eq($row['party_size'], 12, 'Personenzahl gespeichert');
eq($row['start_utc'], '2026-08-01 16:00:00', 'start_utc korrekt (Abend 18:00 CEST -> 16:00 UTC)');
eq($row['end_utc'],   '2026-08-02 00:00:00', 'end_utc korrekt (02:00 Folgetag -> 00:00 UTC)');
truthy(!empty($row['hausordnung_accepted_at']), 'Hausordnung-Zeitpunkt gesetzt');
eq($row['hausordnung_version'], '2026-1', 'Hausordnung-Version gespeichert');


/* ================================================================ *
 * 7) day_status — Statusabbildung für den Kalender
 * ================================================================ */
section('day_status');
$db  = make_db();
$now = new DateTimeImmutable('2026-07-01 00:00:00', $utc);

// Freier Zukunftstag.
$st = day_status($db, '2026-08-01', $now);
eq($st, ['tag' => 'frei', 'abend' => 'frei'], 'freier Zukunftstag -> beide Slots frei');

// Nach Buchung des Tag-Slots -> belegt.
create_booking($db, valid_input(['booking_date' => '2026-08-01', 'slot' => 'tag']), $now);
$st = day_status($db, '2026-08-01', $now);
eq($st['tag'], 'belegt', 'gebuchter Tag-Slot -> belegt');
eq($st['abend'], 'frei', 'ungebuchter Abend-Slot -> frei');

// Vergangener Tag.
$st = day_status($db, '2026-06-01', $now);
eq($st, ['tag' => 'vergangen', 'abend' => 'vergangen'], 'vergangener Tag -> beide vergangen');

// Außerhalb Buchungsfenster.
$st = day_status($db, '2026-12-01', $now);
eq($st, ['tag' => 'geschlossen', 'abend' => 'geschlossen'], 'nach Buchungsfenster -> geschlossen');

// Blackout.
$db->exec("INSERT INTO blackouts (blackout_date, reason) VALUES ('2026-08-15', 'Sommerfest')");
$st = day_status($db, '2026-08-15', $now);
eq($st, ['tag' => 'blackout', 'abend' => 'blackout'], 'Blackout-Tag -> beide blackout');

// Geschlossener Wochentag.
$db2 = make_db();
$db2->exec('UPDATE opening_hours SET is_closed = 1 WHERE weekday = 2'); // Dienstag
$st = day_status($db2, '2026-08-04', $now); // 2026-08-04 ist ein Dienstag
eq($st, ['tag' => 'geschlossen', 'abend' => 'geschlossen'], 'geschlossener Wochentag -> geschlossen');


/* ---------------------------------------------------------------- *
 * Zusammenfassung                                                  *
 * ---------------------------------------------------------------- */
$tests = $GLOBALS['__tests'];
$fails = $GLOBALS['__fails'];
echo "\n----------------------------------------\n";
echo "Tests: {$tests}, Fehlgeschlagen: {$fails}\n";
if ($fails > 0) {
    echo "ERGEBNIS: ROT\n";
    exit(1);
}
echo "ERGEBNIS: GRÜN — alle Assertions ok.\n";
exit(0);
