<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repo.php';

/**
 * Schreib-Aktionen (Statuswechsel, Abnahme, Admin-CRUD). Nur Prepared Statements.
 * Jede Funktion liefert true|string[] (Fehlerliste) bzw. wirft bei Programmierfehlern.
 */

/** Hafenmeister/Admin entscheidet über eine pending-Buchung. */
function decide_booking(PDO $pdo, int $id, int $deciderId, string $decision, string $note = ''): array|bool
{
    if (!in_array($decision, ['confirmed', 'rejected'], true)) {
        return ['Ungültige Entscheidung.'];
    }
    $b = booking_by_id($pdo, $id);
    if (!$b) { return ['Buchung nicht gefunden.']; }
    if ($b['status'] !== 'pending') { return ['Diese Buchung wurde bereits entschieden.']; }

    $stmt = $pdo->prepare(
        "UPDATE bookings
            SET status = :st, decided_by = :by, decided_at = :at, decision_note = :note
          WHERE id = :id AND status = 'pending'"
    );
    $stmt->execute([
        ':st' => $decision, ':by' => $deciderId, ':at' => now_utc(),
        ':note' => $note !== '' ? $note : null, ':id' => $id,
    ]);
    return $stmt->rowCount() === 1 ? true : ['Konnte nicht gespeichert werden.'];
}

/** Mitglied storniert eigene Buchung (pending|confirmed -> cancelled). */
function cancel_booking(PDO $pdo, int $id, int $memberId): array|bool
{
    $stmt = $pdo->prepare(
        "UPDATE bookings SET status = 'cancelled'
          WHERE id = :id AND member_id = :mid AND status IN ('pending','confirmed')"
    );
    $stmt->execute([':id' => $id, ':mid' => $memberId]);
    return $stmt->rowCount() === 1 ? true : ['Diese Buchung kann nicht (mehr) storniert werden.'];
}

/** Abnahme erfassen (passed|rework). */
function record_inspection(PDO $pdo, int $id, int $inspectorId, string $result, string $notes = ''): array|bool
{
    if (!in_array($result, ['passed', 'rework'], true)) {
        return ['Ungültiges Abnahme-Ergebnis.'];
    }
    $stmt = $pdo->prepare(
        "UPDATE bookings
            SET inspection_result = :r, inspected_by = :by, inspected_at = :at, inspection_notes = :n
          WHERE id = :id AND status = 'confirmed'"
    );
    $stmt->execute([
        ':r' => $result, ':by' => $inspectorId, ':at' => now_utc(),
        ':n' => $notes !== '' ? $notes : null, ':id' => $id,
    ]);
    return $stmt->rowCount() === 1 ? true : ['Abnahme konnte nicht gespeichert werden.'];
}

/* ---- Admin: Settings / Öffnungszeiten / Blackouts ---- */

function save_settings(PDO $pdo, array $in): array|bool
{
    $errors = [];
    $evening = trim((string) ($in['evening_start_local'] ?? ''));
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $evening)) { $errors[] = 'Slot-Grenze: ungültige Uhrzeit.'; }
    $lead = (int) ($in['lead_time_hours'] ?? 0);
    $expiry = (int) ($in['pending_expiry_hours'] ?? 0);
    $maxParty = (int) ($in['max_party_size'] ?? 0);
    if ($maxParty < 1 || $maxParty > 200) { $errors[] = 'Max. Personenzahl unplausibel.'; }
    $window = trim((string) ($in['booking_window_end'] ?? ''));
    if ($window !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $window)) { $errors[] = 'Buchungsfenster: ungültiges Datum.'; }
    if ($errors) { return $errors; }

    $stmt = $pdo->prepare(
        'UPDATE settings SET evening_start_local = :ev, lead_time_hours = :lead,
                pending_expiry_hours = :exp, max_party_size = :max, booking_window_end = :win
          WHERE id = 1'
    );
    $stmt->execute([
        ':ev' => strlen($evening) === 5 ? $evening . ':00' : $evening,
        ':lead' => $lead, ':exp' => $expiry, ':max' => $maxParty,
        ':win' => $window !== '' ? $window : null,
    ]);
    return true;
}

function save_opening_hours(PDO $pdo, array $in): array|bool
{
    // $in['open'][weekday], $in['close'][weekday], $in['closed'][weekday]
    $stmt = $pdo->prepare(
        'UPDATE opening_hours SET open_time = :o, close_time = :c, is_closed = :x WHERE weekday = :w'
    );
    for ($w = 1; $w <= 7; $w++) {
        $open = (string) ($in['open'][$w] ?? '08:00');
        $close = (string) ($in['close'][$w] ?? '02:00');
        $closed = !empty($in['closed'][$w]) ? 1 : 0;
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $open) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $close)) {
            return ['Öffnungszeiten: ungültige Uhrzeit bei Wochentag ' . $w . '.'];
        }
        $stmt->execute([
            ':o' => strlen($open) === 5 ? $open . ':00' : $open,
            ':c' => strlen($close) === 5 ? $close . ':00' : $close,
            ':x' => $closed, ':w' => $w,
        ]);
    }
    return true;
}

function add_blackout(PDO $pdo, string $date, string $reason = ''): array|bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return ['Ungültiges Datum.']; }
    $stmt = $pdo->prepare('INSERT INTO blackouts (blackout_date, reason) VALUES (:d, :r)
                           ON DUPLICATE KEY UPDATE reason = VALUES(reason)');
    $stmt->execute([':d' => $date, ':r' => $reason !== '' ? $reason : null]);
    return true;
}

function delete_blackout(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM blackouts WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return true;
}

/* ---- Admin: Mitglieder ---- */

function upsert_member(PDO $pdo, array $in): array|bool
{
    $email = strtolower(trim((string) ($in['email'] ?? '')));
    $name = trim((string) ($in['name'] ?? ''));
    $role = (string) ($in['role'] ?? 'member');
    $status = (string) ($in['status'] ?? 'active');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return ['Ungültige E-Mail.']; }
    if ($name === '') { return ['Name fehlt.']; }
    if (!in_array($role, ['member', 'hafenmeister', 'admin'], true)) { return ['Ungültige Rolle.']; }
    if (!in_array($status, ['active', 'pending'], true)) { return ['Ungültiger Status.']; }

    $id = (int) ($in['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE members SET email=:e, name=:n, role=:r, status=:s WHERE id=:id');
        $stmt->execute([':e' => $email, ':n' => $name, ':r' => $role, ':s' => $status, ':id' => $id]);
        if (!empty($in['password'])) {
            $p = $pdo->prepare('UPDATE members SET password_hash=:h WHERE id=:id');
            $p->execute([':h' => password_hash((string) $in['password'], PASSWORD_DEFAULT), ':id' => $id]);
        }
        return true;
    }

    $pw = (string) ($in['password'] ?? '');
    if (strlen($pw) < 4) { return ['Bitte ein Startpasswort (min. 4 Zeichen) setzen.']; }
    try {
        $stmt = $pdo->prepare('INSERT INTO members (email, password_hash, name, role, status)
                               VALUES (:e, :h, :n, :r, :s)');
        $stmt->execute([
            ':e' => $email, ':h' => password_hash($pw, PASSWORD_DEFAULT),
            ':n' => $name, ':r' => $role, ':s' => $status,
        ]);
    } catch (PDOException $ex) {
        return ['E-Mail ist bereits vergeben.'];
    }
    return true;
}

/* ---- Admin: Ausstattung (amenities) ---- */

function save_amenity(PDO $pdo, array $in): array|bool
{
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '') { return ['Name fehlt.']; }
    $data = [
        ':name' => $name,
        ':desc' => trim((string) ($in['description'] ?? '')) ?: null,
        ':img'  => trim((string) ($in['image_path'] ?? '')) ?: null,
        ':notes' => trim((string) ($in['notes'] ?? '')) ?: null,
        ':sort' => (int) ($in['sort_order'] ?? 0),
        ':insp' => !empty($in['inspection_relevant']) ? 1 : 0,
        ':act'  => !empty($in['is_active']) ? 1 : 0,
    ];
    $id = (int) ($in['id'] ?? 0);
    if ($id > 0) {
        $data[':id'] = $id;
        $stmt = $pdo->prepare('UPDATE amenities SET name=:name, description=:desc, image_path=:img,
                               notes=:notes, sort_order=:sort, inspection_relevant=:insp, is_active=:act
                               WHERE id=:id');
    } else {
        $stmt = $pdo->prepare('INSERT INTO amenities (name, description, image_path, notes, sort_order, inspection_relevant, is_active)
                               VALUES (:name, :desc, :img, :notes, :sort, :insp, :act)');
    }
    $stmt->execute($data);
    return true;
}

function delete_amenity(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM amenities WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return true;
}

/* ---- Abnahme: Foto-Dokumentation ---- */

const INSPECTION_UPLOAD_DIR = __DIR__ . '/../public/uploads/abnahmen';
const INSPECTION_UPLOAD_URL = '/uploads/abnahmen';
const INSPECTION_MAX_BYTES   = 15728640; // 15 MB pro Datei

/**
 * Speichert hochgeladene Abnahme-Fotos (aus $_FILES['fotos']) resized als WebP
 * und verknüpft sie mit der Buchung. EXIF-Orientierung wird berücksichtigt,
 * Metadaten/GPS fallen durch das Re-Encoding weg.
 *
 * @return array{saved:int,errors:string[]}
 */
function store_inspection_photos(PDO $pdo, int $bookingId, array $files, int $uploaderId): array
{
    $errors = [];
    $saved  = 0;
    if (empty($files['name']) || !is_array($files['name'])) {
        return ['saved' => 0, 'errors' => []];
    }
    if (!is_dir(INSPECTION_UPLOAD_DIR)) {
        @mkdir(INSPECTION_UPLOAD_DIR, 0775, true);
    }
    $stmt = $pdo->prepare('INSERT INTO inspection_photos (booking_id, file_path, uploaded_by) VALUES (:b, :p, :u)');

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = 'Ein Foto konnte nicht hochgeladen werden (evtl. zu groß).';
            continue;
        }
        $tmp = $files['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) {
            $errors[] = 'Ungültiger Upload.';
            continue;
        }
        if (($files['size'][$i] ?? 0) > INSPECTION_MAX_BYTES) {
            $errors[] = 'Foto zu groß (max. 15 MB).';
            continue;
        }
        $img = load_uploaded_image($tmp);
        if (!$img) {
            $errors[] = 'Nur JPG, PNG oder WebP erlaubt.';
            continue;
        }
        $img  = downscale_image($img, 1600);
        $name = bin2hex(random_bytes(8)) . '.webp';
        $abs  = INSPECTION_UPLOAD_DIR . '/' . $name;
        if (!imagewebp($img, $abs, 78)) {
            imagedestroy($img);
            $errors[] = 'Foto konnte nicht gespeichert werden.';
            continue;
        }
        imagedestroy($img);
        $stmt->execute([':b' => $bookingId, ':p' => INSPECTION_UPLOAD_URL . '/' . $name, ':u' => $uploaderId]);
        $saved++;
    }
    return ['saved' => $saved, 'errors' => $errors];
}

/** Lädt ein Upload-Bild als GD-Ressource, korrigiert EXIF-Orientierung (JPEG). */
function load_uploaded_image(string $tmp): mixed
{
    $info = @getimagesize($tmp);
    if (!$info) {
        return null;
    }
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : null,
        default        => null,
    };
    if (!$img) {
        return null;
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $o = (int) ($exif['Orientation'] ?? 0);
        if ($o === 3)      { $img = imagerotate($img, 180, 0); }
        elseif ($o === 6)  { $img = imagerotate($img, -90, 0); }
        elseif ($o === 8)  { $img = imagerotate($img, 90, 0); }
    }
    return $img;
}

/** Skaliert ein GD-Bild auf max. Kantenlänge herunter (gibt neue Ressource zurück). */
function downscale_image(mixed $img, int $max): mixed
{
    $w = imagesx($img);
    $h = imagesy($img);
    if (max($w, $h) <= $max) {
        return $img;
    }
    $r  = $max / max($w, $h);
    $nw = (int) round($w * $r);
    $nh = (int) round($h * $r);
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    return $dst;
}
