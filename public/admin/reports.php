<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/repo.php';

$user = require_role('admin');
$pdo  = db();

// CSV-Export
if (($_GET['export'] ?? '') === 'csv') {
    $rep = report_usage($pdo);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kamc-lounge-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'E-Mail', 'Gesamt', 'Bestätigt', 'Pending', 'Abgelehnt', 'Storniert']);
    foreach ($rep['per_member'] as $r) {
        fputcsv($out, [$r['name'], $r['email'], $r['total'], $r['confirmed'], $r['pending'], $r['rejected'], $r['cancelled']]);
    }
    fclose($out);
    exit;
}

$rep = report_usage($pdo);
$by  = $rep['by_status'];

page_start('Reports', $user, 'admin-reports');
page_header('Reports & Tracking', 'Nutzung im Pilot — nur Tracking, keine Erzwingung.');
?>
<div class="container-page space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach (['confirmed' => 'Bestätigt', 'pending' => 'Wartet', 'rejected' => 'Abgelehnt', 'cancelled' => 'Storniert'] as $k => $lbl): ?>
            <div class="card p-5">
                <div class="text-sm text-schiefer"><?= $lbl ?></div>
                <div class="text-3xl font-display text-navy mt-1"><?= (int) ($by[$k] ?? 0) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card overflow-x-auto">
        <div class="flex items-center justify-between p-4 border-b border-nebel">
            <h2 class="text-lg font-semibold">Buchungen je Mitglied</h2>
            <a class="btn-ghost btn-sm" href="?export=csv">CSV-Export</a>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-nebel/60 text-left text-schiefer">
                <tr>
                    <th class="px-4 py-3 font-ui font-semibold">Mitglied</th>
                    <th class="px-4 py-3 font-ui font-semibold text-right">Gesamt</th>
                    <th class="px-4 py-3 font-ui font-semibold text-right">Bestätigt</th>
                    <th class="px-4 py-3 font-ui font-semibold text-right">Pending</th>
                    <th class="px-4 py-3 font-ui font-semibold text-right">Abgelehnt</th>
                    <th class="px-4 py-3 font-ui font-semibold text-right">Storniert</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-nebel">
                <?php foreach ($rep['per_member'] as $r): ?>
                    <tr class="hover:bg-sand/60">
                        <td class="px-4 py-3"><?= e($r['name']) ?><div class="text-xs text-schiefer"><?= e($r['email']) ?></div></td>
                        <td class="px-4 py-3 text-right font-semibold"><?= (int) $r['total'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int) $r['confirmed'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int) $r['pending'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int) $r['rejected'] ?></td>
                        <td class="px-4 py-3 text-right"><?= (int) $r['cancelled'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
page_end();
