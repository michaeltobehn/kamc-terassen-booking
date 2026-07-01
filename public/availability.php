<?php
declare(strict_types=1);

/**
 * GET /availability.php?from=Y-m-d&to=Y-m-d
 *
 * Liefert die serverseitig berechnete Verfügbarkeit je Tag/Slot als JSON —
 * die einzige Wahrheit für den Kalender (das Frontend ist nie Source of Truth).
 *
 *   [ { "date": "2026-07-05", "tag": "frei", "abend": "belegt" }, ... ]
 *
 * Status je Slot ∈ frei | belegt | blackout | geschlossen | vergangen.
 * Nur Lesezugriff, nur Prepared Statements (in der Engine), kein HTML.
 */

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/slots.php';

header('Content-Type: application/json; charset=utf-8');

const AVAILABILITY_MAX_DAYS = 366; // Schutz gegen unbeabsichtigt riesige Bereiche.

try {
    $from = (string) ($_GET['from'] ?? '');
    $to   = (string) ($_GET['to'] ?? '');

    if (!is_valid_date($from) || !is_valid_date($to)) {
        json_error(400, 'Parameter "from" und "to" müssen gültige Daten (JJJJ-MM-TT) sein.');
    }

    $tz    = new DateTimeZone(LOUNGE_TZ_LOCAL);
    $start = new DateTimeImmutable($from, $tz);
    $end   = new DateTimeImmutable($to, $tz);

    if ($end < $start) {
        json_error(400, '"to" darf nicht vor "from" liegen.');
    }

    // Spanne inkl. Endtag; Obergrenze prüfen.
    $days = (int) $start->diff($end)->format('%a') + 1;
    if ($days > AVAILABILITY_MAX_DAYS) {
        json_error(400, 'Der angefragte Zeitraum ist zu groß (max. ' . AVAILABILITY_MAX_DAYS . ' Tage).');
    }

    $pdo = db();
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    $result = [];
    for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
        $date   = $d->format('Y-m-d');
        $status = day_status($pdo, $date, $now);
        $result[] = [
            'date'  => $date,
            'tag'   => $status['tag'],
            'abend' => $status['abend'],
        ];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // Keine internen Details nach außen geben.
    json_error(500, 'Verfügbarkeit konnte nicht ermittelt werden.');
}

/** Gibt einen JSON-Fehler aus und beendet den Request. */
function json_error(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
