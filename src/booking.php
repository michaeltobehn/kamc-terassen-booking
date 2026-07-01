<?php
declare(strict_types=1);

require_once __DIR__ . '/slots.php';

/**
 * Buchungs-Engine — Schreibpfad (KAMC „Lounge oben").
 *
 * create_booking() legt eine Buchung als 'pending' (Soft-Hold) an. Der
 * Doppelbuchungs-Schutz läuft serialisiert über einen Named Lock
 * (GET_LOCK('booking_terrasse', 10)): Check „(Datum, Slot) frei?" -> INSERT ->
 * RELEASE_LOCK. Der RELEASE erfolgt IMMER (try/finally).
 */

const BOOKING_LOCK_NAME    = 'booking_terrasse';
const BOOKING_LOCK_TIMEOUT = 10; // Sekunden

/**
 * Legt eine Buchung an. Bei Erfolg: neue booking-ID (int).
 * Bei Validierungs-/Verfügbarkeitsfehler: Liste von Fehlermeldungen (string[]).
 *
 * Erwartete $data-Schlüssel:
 *   member_id (int), booking_date ('Y-m-d'), slot ('tag'|'abend'),
 *   party_size (int), purpose (?string),
 *   hausordnung_accepted (bool/truthy), hausordnung_version (string)
 *
 * @param DateTimeImmutable|null $now Referenzzeit (UTC) — injizierbar für Tests.
 * @return int|string[]
 */
function create_booking(PDO $pdo, array $data, ?DateTimeImmutable $now = null): int|array
{
    $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $now   = $now->setTimezone(new DateTimeZone('UTC'));

    // 1) Reine Eingabe-Validierung (ohne DB/Zeit).
    $errors = validate_booking_input($data);
    if ($errors !== []) {
        return $errors;
    }

    $date = (string) $data['booking_date'];
    $slot = (string) $data['slot'];

    $settings = settings_row($pdo);

    // 2) Kontext-Validierung (DB/Zeit), präzise Meldungen — VOR dem Lock (billig).
    $errors = [];

    // Personenzahl gegen die (ggf. strengere) Obergrenze aus settings.
    $party = (int) $data['party_size'];
    if ($party > (int) $settings['max_party_size']) {
        $errors[] = 'Die Personenzahl überschreitet das erlaubte Maximum von ' . (int) $settings['max_party_size'] . '.';
    }

    // Buchungsfenster: kein Slot-Beginn nach booking_window_end.
    if ($settings['booking_window_end'] !== null && $date > $settings['booking_window_end']) {
        $errors[] = 'Dieser Tag liegt außerhalb des Buchungsfensters (bis ' . $settings['booking_window_end'] . ').';
    }

    // Slot-Grenzen (UTC) für Vorlauf-Prüfung.
    $bounds   = slot_bounds($pdo, $date, $slot);
    $startUtc = new DateTimeImmutable($bounds['start_utc'], new DateTimeZone('UTC'));

    // Vorlauf: frühestens lead_time_hours vor Slot-Beginn.
    $leadThreshold = $now->add(new DateInterval('PT' . (int) $settings['lead_time_hours'] . 'H'));
    if ($startUtc < $leadThreshold) {
        if ($startUtc <= $now) {
            $errors[] = 'Dieser Slot liegt in der Vergangenheit.';
        } else {
            $errors[] = 'Zu kurzfristig — Buchungen sind bis spätestens ' . (int) $settings['lead_time_hours'] . ' h vor Beginn möglich.';
        }
    }

    // Blackout / geschlossener Wochentag.
    $day     = new DateTimeImmutable($date, new DateTimeZone(LOUNGE_TZ_LOCAL));
    $oh      = opening_hours_for($pdo, (int) $day->format('N'));
    if ((int) $oh['is_closed'] === 1) {
        $errors[] = 'An diesem Wochentag ist die Lounge geschlossen.';
    }
    if (is_blackout($pdo, $date)) {
        $errors[] = 'Dieser Tag ist gesperrt (z. B. Vereinsveranstaltung) — bitte wähle einen anderen Tag.';
    }

    if ($errors !== []) {
        return $errors;
    }

    // 3) Serialisierter Abschnitt: Frei-Check + INSERT unter Named Lock.
    if (!acquire_booking_lock($pdo)) {
        return ['Das System ist gerade ausgelastet — bitte versuche es in einem Moment erneut.'];
    }

    try {
        if (!is_slot_free($pdo, $date, $slot)) {
            return ['Dieser Slot ist bereits vergeben — bitte wähle einen anderen Tag.'];
        }

        $hausAt = to_utc_datetime($data['hausordnung_accepted_at'] ?? $now);

        $stmt = $pdo->prepare(
            "INSERT INTO bookings
                (member_id, booking_date, slot, start_utc, end_utc, status,
                 party_size, purpose, hausordnung_accepted_at, hausordnung_version)
             VALUES
                (:member_id, :booking_date, :slot, :start_utc, :end_utc, 'pending',
                 :party_size, :purpose, :haus_at, :haus_ver)"
        );
        $stmt->execute([
            ':member_id'    => (int) ($data['member_id'] ?? 0),
            ':booking_date' => $date,
            ':slot'         => $slot,
            ':start_utc'    => $bounds['start_utc'],
            ':end_utc'      => $bounds['end_utc'],
            ':party_size'   => $party,
            ':purpose'      => isset($data['purpose']) && $data['purpose'] !== '' ? (string) $data['purpose'] : null,
            ':haus_at'      => $hausAt,
            ':haus_ver'     => (string) $data['hausordnung_version'],
        ]);

        return (int) $pdo->lastInsertId();
    } finally {
        // RELEASE IMMER — auch bei früher Rückkehr oder Exception.
        release_booking_lock($pdo);
    }
}

/**
 * Named Lock holen. Auf MySQL/MariaDB via GET_LOCK (serialisiert konkurrierende
 * Requests). Auf anderen Treibern (z. B. SQLite im Test) ein No-Op: dort gibt es
 * nur eine Verbindung, konkurrierende Writer entstehen nicht.
 */
function acquire_booking_lock(PDO $pdo, string $name = BOOKING_LOCK_NAME, int $timeout = BOOKING_LOCK_TIMEOUT): bool
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        return true;
    }
    $stmt = $pdo->prepare('SELECT GET_LOCK(:name, :timeout)');
    $stmt->execute([':name' => $name, ':timeout' => $timeout]);
    return (int) $stmt->fetchColumn() === 1;
}

function release_booking_lock(PDO $pdo, string $name = BOOKING_LOCK_NAME): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        return;
    }
    $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
    $stmt->execute([':name' => $name]);
}

/**
 * Normalisiert einen Zeitpunkt zu UTC 'Y-m-d H:i:s' für die Speicherung.
 * Akzeptiert DateTimeInterface oder String (interpretiert Strings als UTC,
 * sofern keine Zone angegeben).
 */
function to_utc_datetime(DateTimeInterface|string $value): string
{
    $utc = new DateTimeZone('UTC');
    if ($value instanceof DateTimeInterface) {
        return (new DateTimeImmutable('@' . $value->getTimestamp()))->setTimezone($utc)->format('Y-m-d H:i:s');
    }
    return (new DateTimeImmutable($value, $utc))->setTimezone($utc)->format('Y-m-d H:i:s');
}
