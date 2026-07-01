<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/repo.php';

$user    = require_login();
$pdo     = db();
$isStaff = has_role($user, 'hafenmeister', 'admin');
$first   = explode(' ', $user['name'])[0];

if ($isStaff) {
    $pending  = pending_bookings($pdo);        // warten auf Freigabe
    $dueInsp  = open_inspections($pdo);         // Abnahme fällig
    $rework   = rework_bookings($pdo);          // Nacharbeit / Beanstandung
    $upcoming = upcoming_confirmed($pdo, 25);   // anstehend (bestätigt)
    $history  = finished_bookings($pdo, 15);    // Historie
    $need     = count($pending) + count($dueInsp) + count($rework);
    $defaultTab = count($pending) ? 'freigaben' : (count($dueInsp) ? 'abnahmen' : (count($rework) ? 'nacharbeit' : 'anstehend'));

    // kleine Helfer für die Tabellen
    $slotCell = fn(array $b) => icon($b['slot'] === 'tag' ? 'sun' : 'moon', 'inline h-4 w-4 -mt-0.5 text-schiefer') . ' ' . ($b['slot'] === 'tag' ? 'Tag' : 'Abend');
} else {
    $myBookings = bookings_for_member($pdo, (int) $user['id']);
    $myUpcoming = array_values(array_filter($myBookings, fn($b) => $b['end_utc'] >= now_utc() && in_array($b['status'], ['pending', 'confirmed'], true)));
}

page_start('Übersicht', $user, '');
?>

<?php if ($isStaff): ?>
<!-- ===================== HAFENMEISTER-COCKPIT ===================== -->
<div class="container-page" x-data="{ tab: '<?= $defaultTab ?>' }">
    <div class="mb-6">
        <p class="eyebrow"><?= e(role_label($user['role'])) ?> · Cockpit</p>
        <h1 class="mt-1 text-3xl font-display font-semibold text-navy">Ahoi, <?= e($first) ?></h1>
        <p class="mt-1 text-schiefer">
            <?php if ($need > 0): ?>
                <span class="font-medium text-akzent-700"><?= $need ?> Vorgang(e) mit Handlungsbedarf.</span> Wo als Nächstes?
            <?php else: ?>
                Alles im grünen Bereich — kein akuter Handlungsbedarf.
            <?php endif; ?>
        </p>
    </div>

    <!-- Handlungsbedarf-Leiste -->
    <?php
    $tiles = [
        ['key' => 'freigaben',  'label' => 'Warten auf Freigabe', 'n' => count($pending),  'act' => true],
        ['key' => 'abnahmen',   'label' => 'Abnahme fällig',      'n' => count($dueInsp),  'act' => true],
        ['key' => 'nacharbeit', 'label' => 'Nacharbeit',          'n' => count($rework),   'act' => true],
        ['key' => 'anstehend',  'label' => 'Anstehend',           'n' => count($upcoming), 'act' => false],
    ];
    ?>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <?php foreach ($tiles as $t): $hot = $t['act'] && $t['n'] > 0; ?>
            <button type="button" @click="tab='<?= $t['key'] ?>'"
                    class="card p-4 text-left transition hover:shadow-sm <?= $hot ? 'ring-akzent/30' : '' ?>"
                    :class="tab==='<?= $t['key'] ?>' ? 'ring-2 ring-navy/40' : ''">
                <div class="flex items-baseline justify-between">
                    <span class="text-3xl font-display leading-none <?= $hot ? 'text-akzent-700' : 'text-navy' ?>"><?= $t['n'] ?></span>
                    <?php if ($hot): ?><span class="badge badge-belegt">zu tun</span><?php endif; ?>
                </div>
                <div class="mt-1 text-sm text-schiefer"><?= e($t['label']) ?></div>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Tabs + Tabellen -->
    <div class="card overflow-hidden">
        <?php
        $tabs = [
            ['key' => 'freigaben',  'label' => 'Freigaben',   'n' => count($pending), 'act' => true],
            ['key' => 'abnahmen',   'label' => 'Abnahme fällig','n' => count($dueInsp), 'act' => true],
            ['key' => 'nacharbeit', 'label' => 'Nacharbeit',   'n' => count($rework),  'act' => true],
            ['key' => 'anstehend',  'label' => 'Anstehend',    'n' => count($upcoming),'act' => false],
            ['key' => 'historie',   'label' => 'Historie',     'n' => count($history), 'act' => false],
        ];
        ?>
        <div class="flex flex-wrap gap-1 border-b border-black/[0.06] px-2 pt-2">
            <?php foreach ($tabs as $t): $badge = $t['act'] && $t['n'] > 0 ? 'bg-akzent/10 text-akzent-700 ring-1 ring-akzent/20' : 'bg-black/[0.05] text-schiefer'; ?>
                <button type="button" @click="tab='<?= $t['key'] ?>'"
                        class="flex items-center gap-1.5 rounded-t-lg px-3 py-2 text-sm font-ui font-medium transition"
                        :class="tab==='<?= $t['key'] ?>' ? 'bg-navy/[0.06] text-navy' : 'text-schiefer hover:text-navy hover:bg-black/[0.03]'">
                    <?= e($t['label']) ?>
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full text-[11px] font-semibold <?= $badge ?>"><?= $t['n'] ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="p-1 sm:p-3">
            <!-- FREIGABEN -->
            <div x-show="tab==='freigaben'" x-cloak class="overflow-x-auto">
                <?php if (!$pending): ?><p class="p-6 text-center text-sm text-schiefer">Keine offenen Anfragen. 🎉</p><?php else: ?>
                <table class="w-full text-sm">
                    <thead class="text-left text-schiefer border-b border-black/[0.06]"><tr>
                        <th class="px-3 py-2 font-ui font-medium">Termin</th><th class="px-3 py-2 font-ui font-medium">Mitglied</th>
                        <th class="px-3 py-2 font-ui font-medium text-right">Pers.</th><th class="px-3 py-2 font-ui font-medium">Eingegangen</th><th class="px-3 py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-nebel">
                    <?php foreach ($pending as $b): ?>
                        <tr class="hover:bg-sand/60">
                            <td class="px-3 py-2.5 whitespace-nowrap"><span class="font-medium text-navy"><?= e(fmt_date($b['booking_date'])) ?></span> · <?= $slotCell($b) ?></td>
                            <td class="px-3 py-2.5"><?= member_name($b['member_name']) ?></td>
                            <td class="px-3 py-2.5 text-right"><?= (int) $b['party_size'] ?></td>
                            <td class="px-3 py-2.5 text-schiefer whitespace-nowrap"><?= e(fmt_dt($b['created_at'], 'd.m., H:i')) ?></td>
                            <td class="px-3 py-2.5 text-right"><a href="/hafenmeister/offene-buchungen.php" class="btn-primary btn-sm">Prüfen</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- ABNAHME FÄLLIG -->
            <div x-show="tab==='abnahmen'" x-cloak class="overflow-x-auto">
                <?php if (!$dueInsp): ?><p class="p-6 text-center text-sm text-schiefer">Keine fälligen Abnahmen.</p><?php else: ?>
                <table class="w-full text-sm">
                    <thead class="text-left text-schiefer border-b border-black/[0.06]"><tr>
                        <th class="px-3 py-2 font-ui font-medium">Termin</th><th class="px-3 py-2 font-ui font-medium">Mitglied</th>
                        <th class="px-3 py-2 font-ui font-medium text-right">Pers.</th><th class="px-3 py-2 font-ui font-medium">Endete</th><th class="px-3 py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-nebel">
                    <?php foreach ($dueInsp as $b): ?>
                        <tr class="hover:bg-sand/60">
                            <td class="px-3 py-2.5 whitespace-nowrap"><span class="font-medium text-navy"><?= e(fmt_date($b['booking_date'])) ?></span> · <?= $slotCell($b) ?></td>
                            <td class="px-3 py-2.5"><?= member_name($b['member_name']) ?></td>
                            <td class="px-3 py-2.5 text-right"><?= (int) $b['party_size'] ?></td>
                            <td class="px-3 py-2.5 text-schiefer whitespace-nowrap"><?= e(fmt_dt($b['end_utc'], 'd.m., H:i')) ?></td>
                            <td class="px-3 py-2.5 text-right"><a href="/hafenmeister/abnahmen.php" class="btn-primary btn-sm">Abnehmen</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- NACHARBEIT / BEANSTANDUNG -->
            <div x-show="tab==='nacharbeit'" x-cloak class="overflow-x-auto">
                <?php if (!$rework): ?><p class="p-6 text-center text-sm text-schiefer">Keine offenen Nacharbeiten oder Beanstandungen.</p><?php else: ?>
                <table class="w-full text-sm">
                    <thead class="text-left text-schiefer border-b border-black/[0.06]"><tr>
                        <th class="px-3 py-2 font-ui font-medium">Termin</th><th class="px-3 py-2 font-ui font-medium">Mitglied</th>
                        <th class="px-3 py-2 font-ui font-medium">Beanstandung</th><th class="px-3 py-2 font-ui font-medium text-right">Fotos</th><th class="px-3 py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-nebel">
                    <?php foreach ($rework as $b): ?>
                        <tr class="hover:bg-sand/60 align-top">
                            <td class="px-3 py-2.5 whitespace-nowrap"><span class="font-medium text-navy"><?= e(fmt_date($b['booking_date'])) ?></span> · <?= $slotCell($b) ?></td>
                            <td class="px-3 py-2.5"><?= member_name($b['member_name']) ?></td>
                            <td class="px-3 py-2.5 text-schiefer max-w-xs"><?= e($b['inspection_notes'] ?: '—') ?></td>
                            <td class="px-3 py-2.5 text-right"><?= count($b['photos']) ?: '–' ?></td>
                            <td class="px-3 py-2.5 text-right"><a href="/hafenmeister/abnahmen.php" class="btn-ghost btn-sm">Details</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- ANSTEHEND -->
            <div x-show="tab==='anstehend'" x-cloak class="overflow-x-auto">
                <?php if (!$upcoming): ?><p class="p-6 text-center text-sm text-schiefer">Keine bestätigten Termine in nächster Zeit.</p><?php else: ?>
                <table class="w-full text-sm">
                    <thead class="text-left text-schiefer border-b border-black/[0.06]"><tr>
                        <th class="px-3 py-2 font-ui font-medium">Termin</th><th class="px-3 py-2 font-ui font-medium">Mitglied</th>
                        <th class="px-3 py-2 font-ui font-medium text-right">Pers.</th><th class="px-3 py-2 font-ui font-medium">Beginnt</th><th class="px-3 py-2 font-ui font-medium">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-nebel">
                    <?php foreach ($upcoming as $b): ?>
                        <tr class="hover:bg-sand/60">
                            <td class="px-3 py-2.5 whitespace-nowrap"><span class="font-medium text-navy"><?= e(fmt_date($b['booking_date'])) ?></span> · <?= $slotCell($b) ?></td>
                            <td class="px-3 py-2.5"><?= member_name($b['member_name']) ?></td>
                            <td class="px-3 py-2.5 text-right"><?= (int) $b['party_size'] ?></td>
                            <td class="px-3 py-2.5 text-schiefer whitespace-nowrap"><?= e(fmt_dt($b['start_utc'], 'd.m., H:i')) ?></td>
                            <td class="px-3 py-2.5"><?= status_badge('confirmed') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- HISTORIE -->
            <div x-show="tab==='historie'" x-cloak class="overflow-x-auto">
                <?php if (!$history): ?><p class="p-6 text-center text-sm text-schiefer">Noch keine abgeschlossenen Vorgänge.</p><?php else: ?>
                <table class="w-full text-sm">
                    <thead class="text-left text-schiefer border-b border-black/[0.06]"><tr>
                        <th class="px-3 py-2 font-ui font-medium">Termin</th><th class="px-3 py-2 font-ui font-medium">Mitglied</th>
                        <th class="px-3 py-2 font-ui font-medium">Buchung</th><th class="px-3 py-2 font-ui font-medium">Abnahme</th>
                    </tr></thead>
                    <tbody class="divide-y divide-nebel">
                    <?php foreach ($history as $b): ?>
                        <tr class="hover:bg-sand/60">
                            <td class="px-3 py-2.5 whitespace-nowrap"><span class="font-medium text-navy"><?= e(fmt_date($b['booking_date'])) ?></span> · <?= $slotCell($b) ?></td>
                            <td class="px-3 py-2.5"><?= member_name($b['member_name']) ?></td>
                            <td class="px-3 py-2.5"><?= status_badge($b['status']) ?></td>
                            <td class="px-3 py-2.5"><?= $b['inspection_result'] ? status_badge($b['inspection_result']) : '<span class="text-schiefer/60">–</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===================== MITGLIEDER-SICHT ===================== -->
<div class="container-page">
    <div class="mb-8">
        <p class="eyebrow">Übersicht</p>
        <h1 class="mt-1 text-3xl font-display font-semibold text-navy">Ahoi, <?= e($first) ?></h1>
        <p class="mt-1 text-schiefer">Deine Buchungen und schnelle Aktionen auf einen Blick.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
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
                            <span class="ico"><?= icon($b['slot'] === 'tag' ? 'sun' : 'moon', 'h-5 w-5') ?></span>
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

        <div class="space-y-4">
            <a href="/kalender.php" class="card p-5 flex items-center gap-4 hover:shadow-sm transition group">
                <span class="ico"><?= icon('calendar') ?></span>
                <div class="flex-1"><div class="font-semibold">Kalender</div><div class="text-sm text-schiefer">Freie Slots sehen</div></div>
                <span class="text-akzent group-hover:translate-x-0.5 transition">→</span>
            </a>
            <a href="/lounge.php" class="card p-5 flex items-center gap-4 hover:shadow-sm transition group ring-1 ring-akzent/20">
                <span class="ico-akzent"><?= icon('plus') ?></span>
                <div class="flex-1"><div class="font-semibold">Neue Buchung</div><div class="text-sm text-schiefer">Tag oder Abend anfragen</div></div>
                <span class="text-akzent group-hover:translate-x-0.5 transition">→</span>
            </a>
            <a href="/ausstattung.php" class="card p-5 flex items-center gap-4 hover:shadow-sm transition group">
                <span class="ico"><?= icon('sparkle') ?></span>
                <div class="flex-1"><div class="font-semibold">Ausstattung</div><div class="text-sm text-schiefer">Was du buchst</div></div>
                <span class="text-akzent group-hover:translate-x-0.5 transition">→</span>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
page_end();
