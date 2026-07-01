<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/repo.php';

$user = require_role('hafenmeister', 'admin');
$pdo  = db();

$filter = [
    'status' => in_array($_GET['status'] ?? '', ['pending', 'confirmed', 'rejected', 'cancelled'], true) ? $_GET['status'] : '',
    'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '',
    'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '',
];
$rows = bookings_list($pdo, $filter + ['limit' => 300]);

page_start('Belegung', $user, 'hm-belegung');
page_header('Belegungsübersicht', 'Alle Buchungen, filterbar. Farbcodiert nach Status.');
?>
<div class="container-page">
    <form method="get" class="card p-4 mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="status">Status</label>
            <select class="field" id="status" name="status">
                <option value="">Alle</option>
                <?php foreach (['pending' => 'wartet', 'confirmed' => 'bestätigt', 'rejected' => 'abgelehnt', 'cancelled' => 'storniert'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filter['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><label class="label" for="from">Von</label><input class="field" type="date" id="from" name="from" value="<?= e($filter['from']) ?>"></div>
        <div><label class="label" for="to">Bis</label><input class="field" type="date" id="to" name="to" value="<?= e($filter['to']) ?>"></div>
        <button class="btn-primary" type="submit">Filtern</button>
        <a class="btn-ghost" href="/hafenmeister/belegung.php">Zurücksetzen</a>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-nebel/60 text-left text-schiefer">
                <tr>
                    <th class="px-4 py-3 font-ui font-semibold">Datum</th>
                    <th class="px-4 py-3 font-ui font-semibold">Slot</th>
                    <th class="px-4 py-3 font-ui font-semibold">Mitglied</th>
                    <th class="px-4 py-3 font-ui font-semibold">Pers.</th>
                    <th class="px-4 py-3 font-ui font-semibold">Status</th>
                    <th class="px-4 py-3 font-ui font-semibold">Abnahme</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-nebel">
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-schiefer">Keine Buchungen für diesen Filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $b): ?>
                    <tr class="hover:bg-sand/60">
                        <td class="px-4 py-3 whitespace-nowrap"><?= e(fmt_date($b['booking_date'])) ?></td>
                        <td class="px-4 py-3 whitespace-nowrap"><?= $b['slot'] === 'tag' ? 'Tag' : 'Abend' ?></td>
                        <td class="px-4 py-3"><?= e($b['member_name']) ?><div class="text-xs text-schiefer"><?= e($b['member_email']) ?></div></td>
                        <td class="px-4 py-3"><?= (int) $b['party_size'] ?></td>
                        <td class="px-4 py-3"><?= status_badge($b['status']) ?></td>
                        <td class="px-4 py-3"><?= $b['inspection_result'] ? status_badge($b['inspection_result']) : '<span class="text-schiefer/60">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
page_end();
