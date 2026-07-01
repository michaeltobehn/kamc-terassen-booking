<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Lesezugriffe (nur Prepared Statements). Reine Datenbeschaffung für die Views.
 */

function now_utc(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

/** Buchungen mit Filter (status, from, to, member_id) inkl. Mitgliedsname. */
function bookings_list(PDO $pdo, array $f = []): array
{
    $sql = 'SELECT b.*, m.name AS member_name, m.email AS member_email
              FROM bookings b JOIN members m ON m.id = b.member_id
             WHERE 1=1';
    $args = [];
    if (!empty($f['status'])) { $sql .= ' AND b.status = :st'; $args[':st'] = $f['status']; }
    if (!empty($f['from']))   { $sql .= ' AND b.booking_date >= :from'; $args[':from'] = $f['from']; }
    if (!empty($f['to']))     { $sql .= ' AND b.booking_date <= :to'; $args[':to'] = $f['to']; }
    if (!empty($f['member_id'])) { $sql .= ' AND b.member_id = :mid'; $args[':mid'] = (int) $f['member_id']; }
    $sql .= ' ORDER BY b.booking_date DESC, b.slot ASC';
    if (!empty($f['limit'])) { $sql .= ' LIMIT ' . (int) $f['limit']; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function booking_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT b.*, m.name AS member_name, m.email AS member_email,
                d.name AS decided_by_name, i.name AS inspected_by_name
           FROM bookings b
           JOIN members m ON m.id = b.member_id
      LEFT JOIN members d ON d.id = b.decided_by
      LEFT JOIN members i ON i.id = b.inspected_by
          WHERE b.id = :id'
    );
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function bookings_for_member(PDO $pdo, int $memberId): array
{
    return bookings_list($pdo, ['member_id' => $memberId]);
}

function pending_bookings(PDO $pdo): array
{
    return bookings_list($pdo, ['status' => 'pending']);
}

/** Offene Abnahmen: bestätigt, Termin vorbei, noch keine Abnahme. */
function open_inspections(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT b.*, m.name AS member_name
           FROM bookings b JOIN members m ON m.id = b.member_id
          WHERE b.status = 'confirmed' AND b.end_utc < :now AND b.inspection_result IS NULL
          ORDER BY b.end_utc ASC"
    );
    $stmt->execute([':now' => now_utc()]);
    return $stmt->fetchAll();
}

/** Kommende bestätigte Termine (für Dashboard). */
function upcoming_confirmed(PDO $pdo, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        "SELECT b.*, m.name AS member_name
           FROM bookings b JOIN members m ON m.id = b.member_id
          WHERE b.status = 'confirmed' AND b.end_utc >= :now
          ORDER BY b.start_utc ASC LIMIT " . (int) $limit
    );
    $stmt->execute([':now' => now_utc()]);
    return $stmt->fetchAll();
}

/** Nacharbeit / Beanstandung: Abnahmen mit Ergebnis 'rework' (Schäden/Streitfälle). */
function rework_bookings(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT b.*, m.name AS member_name
           FROM bookings b JOIN members m ON m.id = b.member_id
          WHERE b.inspection_result = 'rework'
          ORDER BY b.inspected_at DESC"
    )->fetchAll();
    foreach ($rows as &$r) {
        $r['photos'] = inspection_photos($pdo, (int) $r['id']);
    }
    return $rows;
}

/** Historie: abgeschlossene/terminale Buchungen (passed | rejected | cancelled). */
function finished_bookings(PDO $pdo, int $limit = 15): array
{
    return $pdo->query(
        "SELECT b.*, m.name AS member_name
           FROM bookings b JOIN members m ON m.id = b.member_id
          WHERE b.inspection_result = 'passed' OR b.status IN ('rejected','cancelled')
          ORDER BY b.updated_at DESC LIMIT " . (int) $limit
    )->fetchAll();
}

function members_all(PDO $pdo): array
{
    return $pdo->query('SELECT id, email, name, role, status, created_at FROM members ORDER BY name')->fetchAll();
}

function member_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM members WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function amenities_all(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM amenities';
    if ($activeOnly) { $sql .= ' WHERE is_active = 1'; }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return $pdo->query($sql)->fetchAll();
}

function amenity_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM amenities WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

/** Abnahme-relevante Ausstattung (Checkliste). */
function inspection_checklist(PDO $pdo): array
{
    return $pdo->query('SELECT name FROM amenities WHERE inspection_relevant = 1 AND is_active = 1 ORDER BY sort_order')->fetchAll();
}

/** Foto-Pfade der Abnahme-Dokumentation einer Buchung. @return string[] */
function inspection_photos(PDO $pdo, int $bookingId): array
{
    $stmt = $pdo->prepare('SELECT file_path FROM inspection_photos WHERE booking_id = :b ORDER BY id');
    $stmt->execute([':b' => $bookingId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Zuletzt abgenommene Buchungen inkl. Fotos (Doku-Archiv). */
function recent_inspections(PDO $pdo, int $limit = 8): array
{
    $rows = $pdo->query(
        "SELECT b.*, m.name AS member_name
           FROM bookings b JOIN members m ON m.id = b.member_id
          WHERE b.inspection_result IS NOT NULL
          ORDER BY b.inspected_at DESC LIMIT " . (int) $limit
    )->fetchAll();
    foreach ($rows as &$r) {
        $r['photos'] = inspection_photos($pdo, (int) $r['id']);
    }
    return $rows;
}

function settings_raw(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch() ?: [];
}

function opening_hours_all(PDO $pdo): array
{
    $rows = $pdo->query('SELECT * FROM opening_hours ORDER BY weekday')->fetchAll();
    $out = [];
    foreach ($rows as $r) { $out[(int) $r['weekday']] = $r; }
    return $out;
}

function blackouts_all(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM blackouts ORDER BY blackout_date DESC')->fetchAll();
}

/** Nutzungs-Report: Buchungen je Mitglied + Statusverteilung. */
function report_usage(PDO $pdo): array
{
    $perMember = $pdo->query(
        "SELECT m.name, m.email,
                COUNT(b.id) AS total,
                SUM(b.status='confirmed') AS confirmed,
                SUM(b.status='pending')   AS pending,
                SUM(b.status='rejected')  AS rejected,
                SUM(b.status='cancelled') AS cancelled
           FROM members m LEFT JOIN bookings b ON b.member_id = m.id
          GROUP BY m.id ORDER BY total DESC, m.name"
    )->fetchAll();

    $byStatus = $pdo->query(
        'SELECT status, COUNT(*) AS n FROM bookings GROUP BY status'
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    return ['per_member' => $perMember, 'by_status' => $byStatus];
}
