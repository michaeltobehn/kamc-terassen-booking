<?php
/**
 * Vorlage. Kopiere diese Datei zu  config.php  und trage echte Werte ein.
 * config.php steht in .gitignore und wird EINMALIG manuell per FTP auf den
 * Server gelegt (liegt bewusst AUSSERHALB des Webroots /public).
 */
return [
    'db' => [
        'host'    => 'localhost',        // Strato: meist localhost oder rdbms.strato.de
        'name'    => 'DEIN_DB_NAME',
        'user'    => 'DEIN_DB_USER',
        'pass'    => 'DEIN_DB_PASSWORT',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url'            => 'https://lounge.kamc.koeln',
        'timezone_display'    => 'Europe/Berlin',
        'hausordnung_version' => '2026-1',
    ],
    'mail' => [
        'transport' => 'smtp',       // 'smtp' (PHPMailer, empfohlen) | 'mail' | 'log' (Dev-Outbox)
        'smtp_host' => 'smtp.strato.de',
        'smtp_port' => 587,
        'smtp_user' => 'lounge@kamc.koeln',
        'smtp_pass' => 'SMTP_PASSWORT',
        'from_name' => 'KAMC · Lounge oben',
        'from_addr' => 'lounge@kamc.koeln',
    ],
];
