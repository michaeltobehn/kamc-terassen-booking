<?php
declare(strict_types=1);

/**
 * Auto-Expiry (Strato Cron, z.B. stuendlich):
 * offenes 'pending' aelter als settings.pending_expiry_hours -> 'rejected'.
 * Zusaetzlich Lazy-Expiry beim Lesen im normalen Request-Pfad.
 */
require __DIR__ . '/../src/db.php';

$pdo  = db();
$stmt = $pdo->prepare(
    "UPDATE bookings b
        JOIN settings s ON s.id = 1
        SET b.status = 'rejected',
            b.decision_note = CONCAT_WS(' ', b.decision_note, '[auto-expired]')
      WHERE b.status = 'pending'
        AND b.created_at < (UTC_TIMESTAMP() - INTERVAL s.pending_expiry_hours HOUR)"
);
$stmt->execute();
echo 'expired: ' . $stmt->rowCount() . "\n";
