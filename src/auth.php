<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Session-basierte Authentifizierung + CSRF (kein Framework).
 * Autorisierung komplett in PHP: require_login() / require_role().
 */

function auth_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,      // in Dev (http) automatisch aus
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Eingeloggtes Mitglied oder null. */
function current_user(): ?array
{
    auth_boot();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null && (int) $cache['id'] === (int) $_SESSION['uid']) {
        return $cache;
    }
    $stmt = db()->prepare('SELECT id, email, name, role, status FROM members WHERE id = :id AND status = "active"');
    $stmt->execute([':id' => (int) $_SESSION['uid']]);
    $row = $stmt->fetch();
    $cache = $row ?: null;
    return $cache;
}

/** Login-Versuch. true bei Erfolg. */
function attempt_login(string $email, string $password): bool
{
    auth_boot();
    $stmt = db()->prepare('SELECT id, password_hash, status FROM members WHERE email = :e');
    $stmt->execute([':e' => strtolower(trim($email))]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'active' || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $row['id'];
    return true;
}

function logout(): void
{
    auth_boot();
    $_SESSION = [];
    session_destroy();
}

function require_login(): array
{
    $u = current_user();
    if ($u === null) {
        header('Location: /login.php');
        exit;
    }
    return $u;
}

/** Verlangt eine der angegebenen Rollen. */
function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        flash_set('error', 'Dafür fehlt dir die Berechtigung.');
        header('Location: /index.php');
        exit;
    }
    return $u;
}

function has_role(?array $u, string ...$roles): bool
{
    return $u !== null && in_array($u['role'], $roles, true);
}

/* ---- CSRF ---- */

function csrf_token(): string
{
    auth_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/** Prüft das CSRF-Token eines POST. Bricht bei Fehler ab (419). */
function csrf_check(): void
{
    auth_boot();
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Sitzung abgelaufen — bitte lade die Seite neu.');
    }
}

/* ---- Flash-Messages ---- */

function flash_set(string $type, string $msg): void
{
    auth_boot();
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/** @return array<int,array{type:string,msg:string}> */
function flash_take(): array
{
    auth_boot();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
