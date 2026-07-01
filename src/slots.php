<?php
declare(strict_types=1);

/**
 * Buchungs-Engine — Lese-/Rechenpfad (KAMC „Lounge oben").
 *
 * Reine, testbare Logik: keine Superglobals, keine Ausgabe, keine Seiteneffekte
 * außer lesenden DB-Zugriffen über das injizierte PDO.
 *
 * ZEITZONEN (kritisch, siehe Briefing §4/§11):
 *   - Die DB speichert alles in UTC (PDO-Session: SET time_zone = '+00:00').
 *   - open_time/close_time (opening_hours) und evening_start_local (settings)
 *     sind LOKALE Uhrzeiten (Europe/Berlin).
 *   - Umrechnung Europe/Berlin <-> UTC passiert AUSSCHLIESSLICH hier, an der
 *     I/O-Grenze, via DateTimeImmutable/DateTimeZone. DST (Sommer-/Winterzeit)
 *     wird dadurch automatisch korrekt behandelt.
 *
 * SLOTS (2 feste pro Tag):
 *   - 'tag'   = open_time            .. evening_start_local (18:00)
 *   - 'abend' = evening_start_local  .. close_time (läuft über Mitternacht,
 *               wenn close_time <= open_time, z. B. 08:00 -> 02:00 Folgetag)
 */

const LOUNGE_TZ_LOCAL   = 'Europe/Berlin';
const LOUNGE_SLOTS      = ['tag', 'abend'];
const LOUNGE_MAX_PARTY  = 16;   // harte Obergrenze (Vorstandsbeschluss); settings kann strenger sein.

/**
 * Reine Kernfunktion: berechnet die UTC-Grenzen eines Slots aus rein lokalen
 * Eingaben. Kein DB-Zugriff -> deterministisch und ohne Abhängigkeiten testbar
 * (auch die DST-Wechsel lassen sich hier direkt prüfen).
 *
 * @param string $localDate    Lokaler Kalendertag 'Y-m-d' (Europe/Berlin)
 * @param string $slot         'tag' | 'abend'
 * @param string $openTime     Öffnungszeit lokal 'H:i' oder 'H:i:s'
 * @param string $closeTime    Schließzeit  lokal 'H:i' oder 'H:i:s'
 *                              (<= openTime => Folgetag, Abend über Mitternacht)
 * @param string $eveningStart Slot-Grenze Tag/Abend lokal 'H:i' oder 'H:i:s' (18:00)
 * @return array{start_utc:string,end_utc:string}  UTC 'Y-m-d H:i:s'
 */
function compute_slot_bounds(
    string $localDate,
    string $slot,
    string $openTime,
    string $closeTime,
    string $eveningStart
): array {
    if (!in_array($slot, LOUNGE_SLOTS, true)) {
        throw new InvalidArgumentException("Unbekannter Slot: {$slot}");
    }

    $local = new DateTimeZone(LOUNGE_TZ_LOCAL);
    $utc   = new DateTimeZone('UTC');

    // '!' setzt alle nicht angegebenen Felder auf 0 -> sauberer Tagesbeginn 00:00.
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $localDate, $local);
    if ($day === false) {
        throw new InvalidArgumentException("Ungültiges Datum: {$localDate}");
    }

    if ($slot === 'tag') {
        $startLocal = with_time($day, $openTime);
        $endLocal   = with_time($day, $eveningStart);
    } else { // 'abend'
        $startLocal = with_time($day, $eveningStart);
        // Abend endet zur Schließzeit. Liegt die Schließzeit <= Öffnungszeit,
        // ist sie am Folgetag (klassisch 08:00 -> 02:00).
        $endDay   = time_leq($closeTime, $openTime) ? $day->modify('+1 day') : $day;
        $endLocal = with_time($endDay, $closeTime);
    }

    // Umrechnung an der Grenze: lokale Wandzeit -> UTC. DST erledigt DateTime.
    return [
        'start_utc' => $startLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
        'end_utc'   => $endLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
    ];
}

/**
 * DB-gebundene Variante: lädt Öffnungszeiten (Wochentag) + evening_start aus der
 * DB und delegiert an compute_slot_bounds().
 *
 * Anm.: Die Task-Skizze notiert slot_bounds() ohne PDO — für die Slot-Grenzen
 * werden aber zwingend opening_hours + settings gebraucht. Deshalb wird das PDO
 * hier injiziert (statt eines globalen db()-Singletons): testbar mit In-Memory-DB.
 *
 * @return array{start_utc:string,end_utc:string}
 */
function slot_bounds(PDO $pdo, string $localDate, string $slot): array
{
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $localDate, new DateTimeZone(LOUNGE_TZ_LOCAL));
    if ($day === false) {
        throw new InvalidArgumentException("Ungültiges Datum: {$localDate}");
    }
    $weekday = (int) $day->format('N'); // 1=Mo .. 7=So (passt zu opening_hours.weekday)

    $oh       = opening_hours_for($pdo, $weekday);
    $settings = settings_row($pdo);

    return compute_slot_bounds(
        $localDate,
        $slot,
        $oh['open_time'],
        $oh['close_time'],
        $settings['evening_start_local']
    );
}

/**
 * true, wenn KEINE aktive (pending|confirmed) Buchung für (booking_date, slot)
 * existiert. Autoritativer Doppelbuchungs-Check — wird von create_booking()
 * innerhalb des GET_LOCK erneut aufgerufen (race-sicher).
 *
 * Bewusst OHNE Lazy-Expiry: ein 'pending' ist der Soft-Hold und blockt den Slot,
 * bis der Hafenmeister entscheidet oder cron/expire.php es auf 'rejected' setzt.
 */
function is_slot_free(PDO $pdo, string $localDate, string $slot): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1
           FROM bookings
          WHERE booking_date = :d
            AND slot = :s
            AND status IN ('pending','confirmed')
          LIMIT 1"
    );
    $stmt->execute([':d' => $localDate, ':s' => $slot]);
    return $stmt->fetchColumn() === false;
}

/**
 * Status je Slot für einen lokalen Kalendertag — für Kalender/Verfügbarkeit.
 *
 * Rückgabe: ['tag' => <status>, 'abend' => <status>] mit
 *   status ∈ { frei | belegt | blackout | geschlossen | vergangen }
 *
 * Präzedenz (erste zutreffende gewinnt), pro Slot:
 *   1. geschlossen  — Wochentag is_closed ODER Datum > booking_window_end
 *                     (Pilot-Ende: nicht mehr buchbar).
 *   2. blackout     — Sperrtag (blackouts).
 *   3. vergangen    — Slot-Beginn liegt in der Vergangenheit ODER innerhalb der
 *                     Vorlaufzeit (< lead_time_hours bis Slot-Beginn).
 *   4. belegt       — aktive (pending|confirmed) Buchung vorhanden.
 *   5. frei         — buchbar.
 *
 * @param DateTimeImmutable|null $now Referenzzeit (UTC) — injizierbar für Tests.
 * @return array{tag:string,abend:string}
 */
function day_status(PDO $pdo, string $localDate, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $now   = $now->setTimezone(new DateTimeZone('UTC'));

    $settings = settings_row($pdo);
    $day      = DateTimeImmutable::createFromFormat('!Y-m-d', $localDate, new DateTimeZone(LOUNGE_TZ_LOCAL));
    if ($day === false) {
        throw new InvalidArgumentException("Ungültiges Datum: {$localDate}");
    }
    $weekday = (int) $day->format('N');
    $oh      = opening_hours_for($pdo, $weekday);

    $windowEnd     = $settings['booking_window_end']; // 'Y-m-d' oder null
    $afterWindow   = $windowEnd !== null && $localDate > $windowEnd;
    $isClosed      = (int) $oh['is_closed'] === 1;
    $isBlackout    = is_blackout($pdo, $localDate);
    $leadThreshold = $now->add(new DateInterval('PT' . (int) $settings['lead_time_hours'] . 'H'));

    $out = [];
    foreach (LOUNGE_SLOTS as $slot) {
        if ($isClosed || $afterWindow) {
            $out[$slot] = 'geschlossen';
            continue;
        }
        if ($isBlackout) {
            $out[$slot] = 'blackout';
            continue;
        }

        $bounds    = compute_slot_bounds($localDate, $slot, $oh['open_time'], $oh['close_time'], $settings['evening_start_local']);
        $startUtc  = new DateTimeImmutable($bounds['start_utc'], new DateTimeZone('UTC'));

        // Vergangen ODER innerhalb der Vorlaufzeit -> nicht (mehr) buchbar.
        if ($startUtc < $leadThreshold) {
            $out[$slot] = 'vergangen';
            continue;
        }
        $out[$slot] = is_slot_free($pdo, $localDate, $slot) ? 'frei' : 'belegt';
    }

    return ['tag' => $out['tag'], 'abend' => $out['abend']];
}

/**
 * Validiert die reine Formular-Eingabe (ohne DB/Zeit-Kontext): Personenzahl,
 * Hausordnung, Datum-/Slot-Format. Verfügbarkeit, Vorlauf und Buchungsfenster
 * prüft create_booking() (braucht PDO + Referenzzeit).
 *
 * @return string[] Liste von Fehlermeldungen (leer = ok)
 */
function validate_booking_input(array $in): array
{
    $errors = [];

    // Datum
    $date = (string) ($in['booking_date'] ?? '');
    if ($date === '' || !is_valid_date($date)) {
        $errors[] = 'Bitte ein gültiges Datum im Format JJJJ-MM-TT angeben.';
    }

    // Slot
    $slot = (string) ($in['slot'] ?? '');
    if (!in_array($slot, LOUNGE_SLOTS, true)) {
        $errors[] = 'Bitte einen gültigen Slot wählen (Tag oder Abend).';
    }

    // Personenzahl 1..16
    $partyRaw = $in['party_size'] ?? null;
    if (!is_int_like($partyRaw)) {
        $errors[] = 'Bitte eine gültige Personenzahl angeben.';
    } else {
        $party = (int) $partyRaw;
        if ($party < 1 || $party > LOUNGE_MAX_PARTY) {
            $errors[] = 'Die Personenzahl muss zwischen 1 und ' . LOUNGE_MAX_PARTY . ' liegen.';
        }
    }

    // Hausordnung: Zustimmung + Version (revisionssicher, Pflicht)
    if (empty($in['hausordnung_accepted'])) {
        $errors[] = 'Bitte die Hausordnung bestätigen.';
    }
    if (($in['hausordnung_version'] ?? '') === '') {
        $errors[] = 'Es fehlt die Version der Hausordnung.';
    }

    return $errors;
}


/* ------------------------------------------------------------------ *
 * Interne Helfer (privat für die Engine)                             *
 * ------------------------------------------------------------------ */

/** Setzt eine lokale Wandzeit ('H:i' oder 'H:i:s') auf einen Tag. */
function with_time(DateTimeImmutable $day, string $time): DateTimeImmutable
{
    [$h, $m, $s] = array_pad(array_map('intval', explode(':', $time)), 3, 0);
    return $day->setTime($h, $m, $s);
}

/** true, wenn Uhrzeit $a <= $b (rein numerisch, sekundengenau). */
function time_leq(string $a, string $b): bool
{
    return time_to_seconds($a) <= time_to_seconds($b);
}

function time_to_seconds(string $time): int
{
    [$h, $m, $s] = array_pad(array_map('intval', explode(':', $time)), 3, 0);
    return $h * 3600 + $m * 60 + $s;
}

/**
 * opening_hours des Wochentags (1..7). Fällt auf Standardwerte zurück, falls die
 * Zeile fehlt. Pro Request statisch gecacht (7 Zeilen, ändern sich nicht).
 *
 * @return array{open_time:string,close_time:string,is_closed:int}
 */
function opening_hours_for(PDO $pdo, int $weekday): array
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (!isset($cache[$key])) {
        $cache[$key] = [];
        $rows = $pdo->query('SELECT weekday, open_time, close_time, is_closed FROM opening_hours')->fetchAll();
        foreach ($rows as $r) {
            $cache[$key][(int) $r['weekday']] = [
                'open_time'  => (string) $r['open_time'],
                'close_time' => (string) $r['close_time'],
                'is_closed'  => (int) $r['is_closed'],
            ];
        }
    }
    return $cache[$key][$weekday] ?? ['open_time' => '08:00:00', 'close_time' => '02:00:00', 'is_closed' => 0];
}

/**
 * Singleton-settings (id = 1), pro Request statisch gecacht.
 *
 * @return array{evening_start_local:string,lead_time_hours:int,pending_expiry_hours:int,max_party_size:int,booking_window_end:?string}
 */
function settings_row(PDO $pdo): array
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (!isset($cache[$key])) {
        $row = $pdo->query(
            'SELECT evening_start_local, lead_time_hours, pending_expiry_hours, max_party_size, booking_window_end
               FROM settings WHERE id = 1'
        )->fetch();
        $cache[$key] = [
            'evening_start_local'  => (string) ($row['evening_start_local'] ?? '18:00:00'),
            'lead_time_hours'      => (int) ($row['lead_time_hours'] ?? 24),
            'pending_expiry_hours' => (int) ($row['pending_expiry_hours'] ?? 48),
            'max_party_size'       => (int) ($row['max_party_size'] ?? LOUNGE_MAX_PARTY),
            'booking_window_end'   => $row['booking_window_end'] ?? null,
        ];
    }
    return $cache[$key];
}

/** Löscht die Request-Caches (Öffnungszeiten/Settings) — v. a. für Tests. */
function reset_engine_caches(): void
{
    // Statische Caches liegen in den jeweiligen Funktionen; über Neuverbindung
    // (neues PDO -> neue spl_object_id) sind sie ohnehin getrennt. Dieser Hook
    // ist ein expliziter Platzhalter, falls ein Reset im selben PDO nötig wird.
}

function is_blackout(PDO $pdo, string $localDate): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM blackouts WHERE blackout_date = :d LIMIT 1');
    $stmt->execute([':d' => $localDate]);
    return $stmt->fetchColumn() !== false;
}

/** Strenge Datumsprüfung: Format JJJJ-MM-TT UND real existierender Kalendertag. */
function is_valid_date(string $date): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/** true, wenn der Wert eine Ganzzahl ist oder ein rein-numerischer Integer-String. */
function is_int_like(mixed $v): bool
{
    if (is_int($v)) {
        return true;
    }
    if (is_string($v) && preg_match('/^\d+$/', $v) === 1) {
        return true;
    }
    return false;
}
