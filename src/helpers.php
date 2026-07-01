<?php
declare(strict_types=1);

/**
 * Platzhalter fuer Querschnitts-Helfer. Hier kommen u.a. rein:
 *   - require_login() / require_role(string ...$roles)
 *   - csrf_token() / csrf_check()
 *   - slot_bounds(string $date, string $slot): array{start_utc,end_utc}
 *       Tag   = Oeffnung .. 18:00 (evening_start_local)
 *       Abend = 18:00 .. Schliessung (02:00 Folgetag)
 *   - is_slot_free(string $date, string $slot): bool  (fuer availability.php)
 * Logik gehoert in /src, NICHT in die Templates.
 */
