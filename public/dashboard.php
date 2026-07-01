<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/repo.php';

$user = require_login();
$pdo  = db();

$myBookings = bookings_for_member($pdo, (int) $user['id']);
$myUpcoming = array_values(array_filter($myBookings, fn($b) => $b['end_utc'] >= now_utc() && in_array($b['status'], ['pending', 'confirmed'], true)));

$isStaff = has_role($user, 'hafenmeister', 'admin');
if ($isStaff) {
    $pending     = pending_bookings($pdo);
    $inspections = open_inspections($pdo);
    $upcoming    = upcoming_confirmed($pdo, 6);
}

page_start('Übersicht', $user, '');
?>
<div class="container-page">
    <div class="mb-8">
        <p class="eyebrow text-himmel/90 !text-navy/50">Übersicht</p>
        <h1 class="mt-1 text-3xl font-display font-semibold text-navy">Ahoi, <?= e(explode(' ', $user['name'])[0]) ?></h1>
        <p class="mt-1 text-schiefer">Deine Buchungen und schnelle Aktionen auf einen Blick.</p>
    </div>

    <?php if ($isStaff): ?>
    <!-- KPI-Zeile (Staff) zuerst -->
    <div class="grid gap-4 sm:grid-cols-3 mb-8">
        <a href="/hafenmeister/offene-buchungen.php" class="card p-5 flex items-center gap-4 hover:shadow-lg transition">
            <span class="ico-akzent"><?= icon('check') ?></span>
            <div><div class="text-3xl font-display leading-none text-akzent"><?= count($pending) ?></div>
                 <div class="text-sm text-schiefer mt-1">Offene Freigaben</div></div>
        </a>
        <a href="/hafenmeister/abnahmen.php" class="card p-5 flex items-center gap-4 hover:shadow-lg transition">
            <span class="ico"><?= icon('clipboard') ?></span>
            <div><div class="text-3xl font-display leading-none text-navy"><?= count($inspections) ?></div>
                 <div class="text-sm text-schiefer mt-1">Offene Abnahmen</div></div>
        </a>
        <a href="/hafenmeister/belegung.php" class="card p-5 flex items-center gap-4 hover:shadow-lg transition">
            <span class="ico"><?= icon('calendar') ?></span>
            <div><div class="text-3xl font-display leading-none text-navy"><?= count($upcoming) ?></div>
                 <div class="text-sm text-schiefer mt-1">Bestätigt (kommend)</div></div>
        </a>
    </div>
    <?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Nächste Termine -->
        <div class="card p-6 lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">Deine nächsten Termine</h2>
                <a href="/meine-buchungen.php" class="text-sm text-akzent hover:underline">Alle ansehen</a>
            </div>
            <?php if (!$myUpcoming): ?>
                <div class="text-center py-8">
                    <span class="ico mx-auto mb-3"><?= icon('calendar') ?></span>
                    <p class="text-schiefer text-sm">Noch keine anstehenden Buchungen.</p>
                    <a href="/lounge.php" class="btn-akzent mt-4"><?= icon('plus','h-4 w-4') ?> Jetzt buchen</a>
                </div>
            <?php else: ?>
                <ul class="divide-y divide-nebel">
                    <?php foreach ($myUpcoming as $b): ?>
                        <li class="py-3 flex items-center gap-3 flex-wrap">
                            <span class="ico <?= $b['slot']==='tag' ? '' : '!bg-navy/5' ?>"><?= icon($b['slot']==='tag' ? 'sun' : 'moon', 'h-5 w-5') ?></span>
                            <div>
                                <div class="font-ui font-medium text-navy"><?= e(fmt_date($b['booking_date'])) ?></div>
                                <div class="text-sm text-schiefer"><?= e(slot_label($b['slot'])) ?> · <?= (int) $b['party_size'] ?> Pers.</div>
                            </div>
                            <span class="ml-auto"><?= status_badge($b['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Schnellaktionen -->
        <div class="space-y-4">
            <a href="/kalender.php" class="card p-5 flex items-center gap-4 hover:shadow-lg transition group">
                <span class="ico"><?= icon('calendar') ?></span>
                <div class="flex-1"><div class="font-semibold">Kalender</div><div class="text-sm text-schiefer">Freie Slots sehen</div></div>
                <span class="text-akzent group-hover:translate-x-0.5 transition">→</span>
            </a>
            <a href="/lounge.php" class="card p-5 flex items-center gap-4 hover:shadow-lg transition group ring-1 ring-akzent/20">
                <span class="ico-akzent"><?= icon('plus') ?></span>
                <div class="flex-1"><div class="font-semibold">Neue Buchung</div><div class="text-sm text-schiefer">Tag oder Abend anfragen</div></div>
                <span class="text-akzent group-hover:translate-x-0.5 transition">→</span>
            </a>
            <a href="/ausstattung.php" class="card p-5 flex items-center gap-4 hover:shadow-lg transition group">
                <span class="ico"><?= icon('sparkle') ?></span>
                <div class="flex-1"><div class="font-semibold">Ausstattung</div><div class="text-sm text-schiefer">Was du buchst</div></div>
                <span class="text-akzent group-hover:translate-x-0.5 transition">→</span>
            </a>
        </div>
    </div>
</div>
<?php
page_end();
