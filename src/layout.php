<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * View-Schicht: Layout-Hülle (Header/Footer/Nav), Flash, Badges, Formatter, Icons.
 * Reine Präsentation — keine Businesslogik.
 */

const APP_NAME = 'Lounge oben';

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function fmt_dt(?string $utc, string $fmt = 'd.m.Y, H:i'): string
{
    if (!$utc) {
        return '—';
    }
    $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $d->setTimezone(new DateTimeZone('Europe/Berlin'))->format($fmt) . ' Uhr';
}

function fmt_date(string $ymd): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
    if (!$d) {
        return $ymd;
    }
    $woche = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    return $woche[(int) $d->format('N') - 1] . '., ' . $d->format('d.m.Y');
}

function slot_label(string $slot): string
{
    return $slot === 'tag' ? 'Tag (bis 18:00)' : 'Abend (ab 18:00)';
}

function status_badge(string $status): string
{
    $labels = [
        'frei' => 'frei', 'belegt' => 'belegt', 'blackout' => 'gesperrt',
        'geschlossen' => 'geschlossen', 'vergangen' => 'vergangen',
        'pending' => 'wartet auf Freigabe', 'confirmed' => 'bestätigt',
        'rejected' => 'abgelehnt', 'cancelled' => 'storniert',
        'passed' => 'Abnahme ok', 'rework' => 'Nacharbeit',
        'disputed' => 'Widerspruch', 'escalated' => 'beim Vorstand', 'resolved' => 'geklärt',
    ];
    return '<span class="badge badge-' . e($status) . '">' . e($labels[$status] ?? $status) . '</span>';
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Vorstand',
        'hafenmeister' => 'Hafenmeister',
        default => 'Mitglied',
    };
}

/**
 * Einheitliche, gut erfassbare Mitglieds-Anzeige: Personen-Icon + Name (Navy,
 * halbfett), optional E-Mail als kleine Zeile darunter. Überall verwenden, wo
 * ein Mitglied in Listen/Tabellen erscheint.
 */
function member_name(string $name, ?string $email = null): string
{
    $out = '<span class="inline-flex items-center gap-1.5 font-ui font-semibold text-navy">'
         . icon('users', 'h-4 w-4 text-schiefer/50 shrink-0') . e($name) . '</span>';
    if ($email !== null && $email !== '') {
        $out .= '<span class="block text-xs text-schiefer font-normal">' . e($email) . '</span>';
    }
    return $out;
}

/** Inline-Line-Icons (stroke). 20×20, currentColor. */
function icon(string $name, string $cls = 'h-5 w-5'): string
{
    $p = [
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
        'cog'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.17V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15H4.5a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 6 9.4l-.33-.06A2 2 0 1 1 8.5 6.51l.06.06A1.65 1.65 0 0 0 10.38 6h.02"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'chart'    => '<path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/>',
        'clipboard'=> '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 14l2 2 4-4"/>',
        'key'      => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.5 12.5 20 3M17 6l2 2M15 8l1.5 1.5"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'anchor'   => '<circle cx="12" cy="5" r="2"/><path d="M12 7v14M5 12H3a9 9 0 0 0 18 0h-2"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'sparkle'  => '<path d="M12 3v18M3 12h18M5 5l14 14M19 5 5 19"/>',
        'mute'     => '<path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="m23 9-6 6M17 9l6 6"/>',
    ];
    $inner = $p[$name] ?? '';
    return '<svg class="' . e($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/**
 * Navigationsgruppen (Information Architecture):
 *   primary    = schlanke Top-Level-Links (Mitglied)
 *   info       = Info-Dropdown
 *   hafen      = Hafenmeisterei (staff/admin)
 *   admin      = Admin (admin)
 */
function nav_groups(array $user): array
{
    $isStaff = has_role($user, 'hafenmeister', 'admin');

    if ($isStaff) {
        // Operative Perspektive: Freigaben/Belegung/Abnahmen sind die Hauptaufgaben.
        $g = [
            'is_staff' => true,
            'primary' => [
                ['href' => '/hafenmeister/offene-buchungen.php', 'label' => 'Freigaben', 'key' => 'hm-offen',    'icon' => 'check'],
                ['href' => '/hafenmeister/belegung.php',         'label' => 'Belegung',  'key' => 'hm-belegung', 'icon' => 'calendar'],
                ['href' => '/hafenmeister/abnahmen.php',         'label' => 'Abnahmen',  'key' => 'hm-abnahmen', 'icon' => 'clipboard'],
            ],
            'info' => [
                ['href' => '/kalender.php',    'label' => 'Kalender',    'key' => 'kalender',    'icon' => 'calendar'],
                ['href' => '/ausstattung.php', 'label' => 'Ausstattung', 'key' => 'ausstattung', 'icon' => 'sparkle'],
                ['href' => '/hausordnung.php', 'label' => 'Hausordnung', 'key' => 'hausordnung', 'icon' => 'info'],
            ],
            'admin' => [],
        ];
    } else {
        // Mitglieder-Perspektive: eigene Buchungen.
        $g = [
            'is_staff' => false,
            'primary' => [
                ['href' => '/kalender.php',        'label' => 'Kalender',        'key' => 'kalender', 'icon' => 'calendar'],
                ['href' => '/meine-buchungen.php', 'label' => 'Meine Buchungen', 'key' => 'meine',    'icon' => 'list'],
            ],
            'info' => [
                ['href' => '/ausstattung.php', 'label' => 'Ausstattung', 'key' => 'ausstattung', 'icon' => 'sparkle'],
                ['href' => '/hausordnung.php', 'label' => 'Hausordnung', 'key' => 'hausordnung', 'icon' => 'info'],
            ],
            'admin' => [],
        ];
    }

    if (has_role($user, 'admin')) {
        $g['admin'] = [
            ['href' => '/admin/eskalationen.php',  'label' => 'Eskalationen',  'key' => 'admin-esc',       'icon' => 'shield'],
            ['href' => '/admin/einstellungen.php', 'label' => 'Einstellungen', 'key' => 'admin-settings',  'icon' => 'cog'],
            ['href' => '/admin/mitglieder.php',    'label' => 'Mitglieder',    'key' => 'admin-members',   'icon' => 'users'],
            ['href' => '/admin/ausstattung.php',   'label' => 'Ausstattung',   'key' => 'admin-amenities', 'icon' => 'sparkle'],
            ['href' => '/admin/reports.php',       'label' => 'Reports',       'key' => 'admin-reports',   'icon' => 'chart'],
        ];
    }
    return $g;
}

/**
 * Öffnet die Seite. $variant: 'auto' (app wenn $user, sonst bare),
 * 'public' (öffentlicher Header für Landing), 'bare' (kein Header, z. B. Login).
 */
function page_start(string $title, ?array $user = null, string $active = '', string $variant = 'auto'): void
{
    if ($variant === 'auto') {
        $variant = $user ? 'app' : 'bare';
    }
    ?><!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · KAMC <?= APP_NAME ?></title>
    <meta name="description" content="Lounge oben — Buchung der Clubterrasse des KAMC e.V. am Rheinauhafen Köln.">
    <link rel="icon" href="/assets/img/kamc-logo.png">
    <link rel="stylesheet" href="/assets/style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-full flex flex-col">
<?php
    if ($variant === 'app' && $user) {
        render_app_header($user, $active);
    } elseif ($variant === 'public') {
        render_public_header($user);
    }
?>
    <main class="flex-1 <?= $variant === 'app' ? 'py-8' : '' ?>">
        <?php render_flash(); ?>
<?php
}

/** Voller App-Header für eingeloggte Nutzer — schlanke IA mit Dropdowns. */
function render_app_header(array $user, string $active): void
{
    $g = nav_groups($user);
    $isStaff = $g['is_staff'];
    $link = function (array $it, string $active) {
        $on = $active === $it['key'] ? 'nav-link-active' : '';
        return '<a href="' . e($it['href']) . '" class="nav-link ' . $on . '">' . e($it['label']) . '</a>';
    };
    ?>
    <header class="bg-white/90 backdrop-blur border-b border-black/[0.06] text-navy sticky top-0 z-40" x-data="{mobile:false}">
        <div class="container-page flex items-center gap-2 h-16">
            <a href="/dashboard.php" class="flex items-center gap-2.5 shrink-0">
                <img src="/assets/img/kamc-logo.png" alt="KAMC" class="h-9 w-9 rounded-full ring-1 ring-black/10">
                <span class="font-display text-lg leading-none text-navy">KAMC<span class="text-schiefer"> · </span><span class="font-ui font-light text-schiefer text-base"><?= APP_NAME ?></span></span>
            </a>

            <nav class="hidden lg:flex items-center gap-0.5 ml-4">
                <?php foreach ($g['primary'] as $it) echo $link($it, $active); ?>

                <!-- Info-Dropdown -->
                <div class="relative" x-data="{o:false}" @click.outside="o=false">
                    <button @click="o=!o" class="nav-link flex items-center gap-1">Info
                        <svg class="h-3.5 w-3.5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg></button>
                    <div x-show="o" x-cloak x-transition class="absolute left-0 mt-2 w-56 card p-1.5 z-50">
                        <?php foreach ($g['info'] as $it): ?>
                            <a href="<?= e($it['href']) ?>" class="menu-item <?= $active === $it['key'] ? 'menu-item-active' : '' ?>"><?= icon($it['icon'], 'h-4 w-4 text-schiefer') ?><?= e($it['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($g['admin']): ?>
                <!-- Admin-Dropdown (nur Vorstand) -->
                <div class="relative" x-data="{o:false}" @click.outside="o=false">
                    <button @click="o=!o" class="nav-link flex items-center gap-1">Admin
                        <svg class="h-3.5 w-3.5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg></button>
                    <div x-show="o" x-cloak x-transition class="absolute left-0 mt-2 w-64 card p-1.5 z-50">
                        <?php foreach ($g['admin'] as $it): ?>
                            <a href="<?= e($it['href']) ?>" class="menu-item <?= $active === $it['key'] ? 'menu-item-active' : '' ?>"><?= icon($it['icon'], 'h-4 w-4 text-schiefer') ?><?= e($it['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </nav>

            <div class="ml-auto flex items-center gap-2 sm:gap-3">
                <?php if (!$isStaff): ?><a href="/lounge.php" class="btn-akzent btn-sm hidden sm:inline-flex"><?= icon('plus', 'h-4 w-4') ?> Buchen</a><?php endif; ?>
                <!-- User-Menü -->
                <div class="relative hidden lg:block" x-data="{o:false}" @click.outside="o=false">
                    <button @click="o=!o" class="nav-link flex items-center gap-2">
                        <span class="text-navy/70"><?= e(explode(' ', $user['name'])[0]) ?></span>
                        <span class="text-[10px] rounded-full bg-navy/[0.06] text-navy px-2 py-0.5"><?= e(role_label($user['role'])) ?></span>
                    </button>
                    <div x-show="o" x-cloak x-transition class="absolute right-0 mt-2 w-48 card p-1.5 z-50">
                        <a href="/dashboard.php" class="menu-item">Übersicht</a>
                        <a href="/logout.php" class="menu-item text-akzent">Abmelden</a>
                    </div>
                </div>
                <button @click="mobile=!mobile" class="lg:hidden btn-ghost btn-sm" aria-label="Menü">☰</button>
            </div>
        </div>

        <!-- Mobile -->
        <nav x-show="mobile" x-cloak class="lg:hidden border-t border-black/[0.06] px-4 pb-4 pt-2 space-y-0.5">
            <?php if (!$isStaff): ?><a href="/lounge.php" class="btn-akzent w-full mb-2"><?= icon('plus', 'h-4 w-4') ?> Buchen</a><?php endif; ?>
            <?php foreach (array_merge($g['primary'], $g['info']) as $it) echo $link($it, $active); ?>
            <?php if ($g['admin']): ?><div class="pt-2 mt-1 border-t border-black/[0.06] text-[11px] uppercase tracking-wide text-schiefer px-3 py-1">Admin</div>
                <?php foreach ($g['admin'] as $it) echo $link($it, $active); endif; ?>
            <a href="/logout.php" class="nav-link text-akzent mt-2">Abmelden</a>
        </nav>
    </header>
<?php
}

/** Schlanker öffentlicher Header für die Landing-Page. */
function render_public_header(?array $user): void
{
    ?>
    <header class="absolute inset-x-0 top-0 z-40">
        <div class="container-page flex items-center gap-3 h-20">
            <a href="/" class="flex items-center gap-2.5">
                <img src="/assets/img/kamc-logo.png" alt="KAMC" class="h-11 w-11 rounded-full ring-1 ring-black/10">
                <span class="font-display text-xl text-navy leading-none">KAMC<span class="text-schiefer"> · </span><span class="font-ui font-light text-schiefer text-lg"><?= APP_NAME ?></span></span>
            </a>
            <nav class="hidden md:flex items-center gap-1 ml-auto">
                <a href="#angebot" class="nav-link">Angebot</a>
                <a href="#ablauf" class="nav-link">Ablauf</a>
                <a href="#ausstattung" class="nav-link">Ausstattung</a>
            </nav>
            <div class="ml-auto md:ml-4 flex items-center gap-2">
                <?php if ($user): ?>
                    <a href="/dashboard.php" class="btn-ghost btn-sm">Zur Übersicht</a>
                <?php else: ?>
                    <a href="/login.php" class="nav-link">Anmelden</a>
                <?php endif; ?>
                <a href="/lounge.php" class="btn-akzent btn-sm">Jetzt buchen</a>
            </div>
        </div>
    </header>
<?php
}

function render_flash(): void
{
    $flash = flash_take();
    if (!$flash) {
        return;
    }
    echo '<div class="container-page mb-6 space-y-2">';
    foreach ($flash as $f) {
        $tone = match ($f['type']) {
            'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
            'error'   => 'bg-akzent/10 text-akzent-700 ring-akzent/20',
            default   => 'bg-himmel-light text-navy ring-navy/15',
        };
        echo '<div class="rounded-lg px-4 py-3 text-sm ring-1 ' . $tone . '">' . e($f['msg']) . '</div>';
    }
    echo '</div>';
}

function page_end(): void
{
    ?>
    </main>
    <footer class="mt-auto bg-white border-t border-black/[0.06] text-schiefer">
        <div class="container-page py-8 flex flex-col sm:flex-row gap-4 items-center justify-between text-sm">
            <div class="flex items-center gap-2">
                <img src="/assets/img/kamc-logo.png" alt="" class="h-7 w-7 rounded-full ring-1 ring-black/10">
                <span>KAMC e.V. · Kölner Autbord- und Motoryachtclub · Rheinauhafen</span>
            </div>
            <nav class="flex flex-wrap gap-x-5 gap-y-1">
                <a class="hover:text-navy" href="https://kamc.koeln" target="_blank" rel="noopener">kamc.koeln</a>
                <a class="hover:text-navy" href="https://kamc.kurabu.com" target="_blank" rel="noopener">Mitglieder-Portal</a>
                <a class="hover:text-navy" href="https://kamc.koeln/impressum" target="_blank" rel="noopener">Impressum</a>
                <a class="hover:text-navy" href="https://kamc.koeln/datenschutz" target="_blank" rel="noopener">Datenschutz</a>
            </nav>
        </div>
    </footer>
    <style>[x-cloak]{display:none!important}</style>
</body>
</html>
<?php
}

function page_header(string $title, string $sub = ''): void
{
    echo '<div class="container-page mb-6">';
    echo '<h1 class="text-2xl sm:text-3xl font-display font-semibold text-navy">' . e($title) . '</h1>';
    if ($sub !== '') {
        echo '<p class="mt-1 text-schiefer">' . e($sub) . '</p>';
    }
    echo '</div>';
}
