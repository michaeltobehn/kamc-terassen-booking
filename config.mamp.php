<?php
/**
 * Lokale Dev-Konfiguration für MAMP (Standardwerte).
 * Das Setup-Skript kopiert diese Datei nach config.php (gitignored).
 * MAMP-MySQL läuft standardmäßig auf 127.0.0.1:8889, User/Passwort root/root.
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 8889,
        'name'    => 'kamc_lounge',
        'user'    => 'root',
        'pass'    => 'root',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url'            => 'http://localhost:8010',
        'timezone_display'    => 'Europe/Berlin',
        'hausordnung_version' => '2026-1',
    ],
    'mail' => [
        'transport' => 'log',   // Dev-Outbox unter logs/mail/ (kein SMTP nötig)
        'from_name' => 'KAMC · Lounge oben (Dev)',
        'from_addr' => 'lounge@localhost',
    ],
];
