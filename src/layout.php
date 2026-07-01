<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * View-Schicht: Layout-Hülle (Header/Footer/Nav), Flash, Badges, Formatter.
 * Reine Präsentation — keine Businesslogik.
 */

const APP_NAME = 'Lounge oben';

/** HTML-escape. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** UTC-'Y-m-d H:i:s' -> Europe/Berlin Anzeige. */
function fmt_dt(?string $utc, string $fmt = 'd.m.Y, H:i'): string
{
    if (!$utc) {
        return '—';
    }
    $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $d->setTimezone(new DateTimeZone('Europe/Berlin'))->format($fmt) . ' Uhr';
}

/** Lokales Datum 'Y-m-d' -> deutsche Anzeige. */
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

/** Status-Badge (Buchung/Slot/Abnahme). */
function status_badge(string $status): string
{
    $labels = [
        'frei' => 'frei', 'belegt' => 'belegt', 'blackout' => 'gesperrt',
        'geschlossen' => 'geschlossen', 'vergangen' => 'vergangen',
        'pending' => 'wartet auf Freigabe', 'confirmed' => 'bestätigt',
        'rejected' => 'abgelehnt', 'cancelled' => 'storniert',
        'passed' => 'Abnahme ok', 'rework' => 'Nacharbeit',
    ];
    $label = $labels[$status] ?? $status;
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

/** @return array<int,array{href:string,label:string,key:string}> Nav je Rolle. */
function nav_for(array $user): array
{
    $items = [
        ['href' => '/kalender.php',        'label' => 'Kalender',        'key' => 'kalender'],
        ['href' => '/buchen.php',          'label' => 'Buchen',          'key' => 'buchen'],
        ['href' => '/meine-buchungen.php', 'label' => 'Meine Buchungen', 'key' => 'meine'],
        ['href' => '/ausstattung.php',     'label' => 'Ausstattung',     'key' => 'ausstattung'],
        ['href' => '/hausordnung.php',     'label' => 'Hausordnung',     'key' => 'hausordnung'],
    ];
    if (has_role($user, 'hafenmeister', 'admin')) {
        $items[] = ['href' => '/hafenmeister/offene-buchungen.php', 'label' => 'Freigaben', 'key' => 'hm-offen'];
        $items[] = ['href' => '/hafenmeister/belegung.php',         'label' => 'Belegung',  'key' => 'hm-belegung'];
        $items[] = ['href' => '/hafenmeister/abnahmen.php',         'label' => 'Abnahmen',  'key' => 'hm-abnahmen'];
    }
    if (has_role($user, 'admin')) {
        $items[] = ['href' => '/admin/einstellungen.php', 'label' => 'Einstellungen', 'key' => 'admin-settings'];
        $items[] = ['href' => '/admin/mitglieder.php',    'label' => 'Mitglieder',    'key' => 'admin-members'];
        $items[] = ['href' => '/admin/ausstattung.php',   'label' => 'Ausstattung ⚙', 'key' => 'admin-amenities'];
        $items[] = ['href' => '/admin/reports.php',       'label' => 'Reports',       'key' => 'admin-reports'];
    }
    return $items;
}

/**
 * Öffnet die Seite (doctype..<main>). $active = nav key für Highlight.
 * $user null -> schlanke Hülle (z. B. Login).
 */
function page_start(string $title, ?array $user = null, string $active = ''): void
{
    $csrf = $user ? '' : '';
    ?><!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · KAMC <?= APP_NAME ?></title>
    <link rel="icon" href="/assets/img/kamc-logo.png">
    <link rel="stylesheet" href="/assets/style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-full flex flex-col">
<?php if ($user): ?>
    <header class="bg-navy text-white sticky top-0 z-40 shadow-lg" x-data="{open:false}">
        <div class="container-page flex items-center gap-3 h-16">
            <a href="/index.php" class="flex items-center gap-2.5 shrink-0">
                <img src="/assets/img/kamc-logo.png" alt="KAMC" class="h-9 w-9 rounded-full ring-2 ring-white/20">
                <span class="font-display text-lg leading-none">KAMC<span class="text-himmel"> · </span><span class="font-ui font-light text-white/80 text-base"><?= APP_NAME ?></span></span>
            </a>
            <nav class="hidden lg:flex items-center gap-0.5 ml-4">
                <?php foreach (nav_for($user) as $it): ?>
                    <a href="<?= e($it['href']) ?>" class="nav-link <?= $active === $it['key'] ? 'nav-link-active' : '' ?>"><?= e($it['label']) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="ml-auto flex items-center gap-3">
                <span class="hidden sm:block text-sm text-white/70"><?= e($user['name']) ?> · <?= e(role_label($user['role'])) ?></span>
                <a href="/logout.php" class="btn-ghost btn-sm">Abmelden</a>
                <button @click="open=!open" class="lg:hidden btn-ghost btn-sm" aria-label="Menü">☰</button>
            </div>
        </div>
        <nav x-show="open" x-cloak class="lg:hidden border-t border-white/10 px-4 pb-3 pt-1 flex flex-col gap-0.5">
            <?php foreach (nav_for($user) as $it): ?>
                <a href="<?= e($it['href']) ?>" class="nav-link <?= $active === $it['key'] ? 'nav-link-active' : '' ?>"><?= e($it['label']) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>
<?php endif; ?>
    <main class="flex-1 <?= $user ? 'py-8' : '' ?>">
        <?php render_flash(); ?>
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
    <footer class="mt-auto bg-navy-950 text-white/70">
        <div class="container-page py-8 flex flex-col sm:flex-row gap-4 items-center justify-between text-sm">
            <div class="flex items-center gap-2">
                <img src="/assets/img/kamc-logo.png" alt="" class="h-7 w-7 rounded-full opacity-90">
                <span>KAMC e.V. · Kölner Autbord- und Motoryachtclub · Rheinauhafen</span>
            </div>
            <nav class="flex flex-wrap gap-x-5 gap-y-1">
                <a class="hover:text-white" href="https://kamc.koeln" target="_blank" rel="noopener">kamc.koeln</a>
                <a class="hover:text-white" href="https://kamc.kurabu.com" target="_blank" rel="noopener">Mitglieder-Portal</a>
                <a class="hover:text-white" href="https://kamc.koeln/impressum" target="_blank" rel="noopener">Impressum</a>
                <a class="hover:text-white" href="https://kamc.koeln/datenschutz" target="_blank" rel="noopener">Datenschutz</a>
            </nav>
        </div>
    </footer>
    <style>[x-cloak]{display:none!important}</style>
</body>
</html>
<?php
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Vorstand',
        'hafenmeister' => 'Hafenmeister',
        default => 'Mitglied',
    };
}

/** Kleiner Seitentitel-Block. */
function page_header(string $title, string $sub = ''): void
{
    echo '<div class="container-page mb-6">';
    echo '<h1 class="text-2xl sm:text-3xl font-display font-semibold text-navy">' . e($title) . '</h1>';
    if ($sub !== '') {
        echo '<p class="mt-1 text-schiefer">' . e($sub) . '</p>';
    }
    echo '</div>';
}
