<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/repo.php';

$user = require_login();
$pdo  = db();

$myBookings = bookings_for_member($pdo, (int) $user['id']);
$myUpcoming = array_filter($myBookings, fn($b) => $b['end_utc'] >= now_utc() && in_array($b['status'], ['pending', 'confirmed'], true));

$isStaff = has_role($user, 'hafenmeister', 'admin');
if ($isStaff) {
    $pending     = pending_bookings($pdo);
    $inspections = open_inspections($pdo);
    $upcoming    = upcoming_confirmed($pdo, 6);
}

page_start('Übersicht', $user, '');
page_header('Ahoi, ' . explode(' ', $user['name'])[0] . '! 👋', 'Willkommen in der Buchung der Lounge oben.');
?>
<div class="container-page grid gap-6 md:grid-cols-3">
    <a href="/kalender.php" class="card p-6 hover:shadow-lg transition group">
        <div class="text-3xl">🗓️</div>
        <h3 class="mt-3 text-lg font-semibold">Belegungskalender</h3>
        <p class="mt-1 text-sm text-schiefer">Freie Tage & Slots auf einen Blick.</p>
        <span class="mt-3 inline-block text-akzent font-ui text-sm group-hover:underline">Öffnen →</span>
    </a>
    <a href="/buchen.php" class="card p-6 hover:shadow-lg transition group">
        <div class="text-3xl">✍️</div>
        <h3 class="mt-3 text-lg font-semibold">Neue Buchung</h3>
        <p class="mt-1 text-sm text-schiefer">Tag- oder Abend-Slot anfragen.</p>
        <span class="mt-3 inline-block text-akzent font-ui text-sm group-hover:underline">Buchen →</span>
    </a>
    <a href="/ausstattung.php" class="card p-6 hover:shadow-lg transition group">
        <div class="text-3xl">🍹</div>
        <h3 class="mt-3 text-lg font-semibold">Ausstattung</h3>
        <p class="mt-1 text-sm text-schiefer">Was du buchst — und worauf zu achten ist.</p>
        <span class="mt-3 inline-block text-akzent font-ui text-sm group-hover:underline">Ansehen →</span>
    </a>
</div>

<div class="container-page mt-8">
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">Deine nächsten Termine</h2>
            <a href="/meine-buchungen.php" class="text-sm text-akzent hover:underline">Alle ansehen</a>
        </div>
        <?php if (!$myUpcoming): ?>
            <p class="text-schiefer text-sm">Noch keine anstehenden Buchungen. <a class="text-akzent hover:underline" href="/buchen.php">Jetzt buchen</a>.</p>
        <?php else: ?>
            <ul class="divide-y divide-nebel">
                <?php foreach ($myUpcoming as $b): ?>
                    <li class="py-3 flex items-center gap-3 flex-wrap">
                        <span class="font-ui font-medium"><?= e(fmt_date($b['booking_date'])) ?></span>
                        <span class="text-schiefer text-sm"><?= e(slot_label($b['slot'])) ?></span>
                        <span class="text-schiefer text-sm">· <?= (int) $b['party_size'] ?> Pers.</span>
                        <span class="ml-auto"><?= status_badge($b['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php if ($isStaff): ?>
<div class="container-page mt-8">
    <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">Hafenmeisterei <span class="badge badge-blackout"><?= e(role_label($user['role'])) ?></span></h2>
    <div class="grid gap-6 md:grid-cols-3">
        <div class="card p-6">
            <div class="flex items-baseline justify-between">
                <h3 class="font-semibold">Offene Freigaben</h3>
                <span class="text-3xl font-display text-akzent"><?= count($pending) ?></span>
            </div>
            <a href="/hafenmeister/offene-buchungen.php" class="mt-3 inline-block text-sm text-akzent hover:underline">Bearbeiten →</a>
        </div>
        <div class="card p-6">
            <div class="flex items-baseline justify-between">
                <h3 class="font-semibold">Offene Abnahmen</h3>
                <span class="text-3xl font-display text-navy"><?= count($inspections) ?></span>
            </div>
            <a href="/hafenmeister/abnahmen.php" class="mt-3 inline-block text-sm text-akzent hover:underline">Bearbeiten →</a>
        </div>
        <div class="card p-6">
            <div class="flex items-baseline justify-between">
                <h3 class="font-semibold">Bestätigt (kommend)</h3>
                <span class="text-3xl font-display text-navy"><?= count($upcoming) ?></span>
            </div>
            <a href="/hafenmeister/belegung.php" class="mt-3 inline-block text-sm text-akzent hover:underline">Belegung →</a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
page_end();
