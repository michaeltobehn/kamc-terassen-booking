<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/actions.php';

$user = require_role('hafenmeister', 'admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = record_inspection($pdo, (int) ($_POST['id'] ?? 0), (int) $user['id'], (string) ($_POST['result'] ?? ''), (string) ($_POST['notes'] ?? ''));
    flash_set($res === true ? 'success' : 'error', $res === true ? 'Abnahme gespeichert.' : implode(' ', (array) $res));
    header('Location: /hafenmeister/abnahmen.php');
    exit;
}

$open      = open_inspections($pdo);
$checklist = inspection_checklist($pdo);

page_start('Abnahmen', $user, 'hm-abnahmen');
page_header('Offene Abnahmen', 'Termin vorbei → prüfen und abnehmen. Checkliste aus der Ausstattung.');
?>
<div class="container-page">
    <?php if ($checklist): ?>
        <div class="card p-4 mb-6 bg-himmel-light/40">
            <span class="text-sm font-semibold text-navy">Checkliste:</span>
            <span class="text-sm text-schiefer"><?= e(implode(' · ', array_column($checklist, 'name'))) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$open): ?>
        <div class="card p-8 text-center text-schiefer">Keine offenen Abnahmen. 🎉</div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($open as $b): ?>
                <div class="card p-5">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-3">
                        <div class="font-ui font-semibold text-navy"><?= e(fmt_date($b['booking_date'])) ?> · <?= e(slot_label($b['slot'])) ?></div>
                        <div class="text-sm text-schiefer"><?= e($b['member_name']) ?> · <?= (int) $b['party_size'] ?> Pers.</div>
                        <div class="ml-auto text-xs text-schiefer">Endete <?= e(fmt_dt($b['end_utc'])) ?></div>
                    </div>
                    <form method="post" class="flex flex-col sm:flex-row gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <input class="field flex-1" type="text" name="notes" placeholder="Notiz (Grill ok / Brandfleck / gekehrt …)">
                        <div class="flex gap-2">
                            <button class="btn-primary btn-sm" type="submit" name="result" value="passed">Abnahme ok</button>
                            <button class="btn-akzent btn-sm" type="submit" name="result" value="rework">Nacharbeit</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
page_end();
