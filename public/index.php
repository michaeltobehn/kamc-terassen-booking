<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

// Minimaler Smoke-Test der DB-Verbindung. Spaeter: Router / Seiten.
try {
    db()->query('SELECT 1');
    echo 'KAMC Lounge oben — Setup OK. DB-Verbindung steht.';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'DB-Fehler: config.php vorhanden und korrekt?';
}
