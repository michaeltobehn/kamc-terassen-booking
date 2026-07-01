<?php
declare(strict_types=1);

/**
 * PDO-Verbindung. KRITISCH: Session-Zeitzone auf numerischen UTC-Offset setzen.
 * Benannte Zonen ('UTC') brauchen die mysql-tz-Tabellen, die auf Strato-
 * Shared-Hosting oft fehlen -> daher '+00:00'.
 * Alle _utc-Spalten speichern UTC; Anzeige/Eingabe wird in Europe/Berlin
 * umgerechnet (nur an der I/O-Grenze).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = require __DIR__ . '/../config.php';
    $db  = $cfg['db'];

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Session-Zeitzone: numerischer Offset, NICHT 'UTC'.
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}
