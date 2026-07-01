<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/actions.php';
require __DIR__ . '/../../src/mail.php';

$user = require_role('hafenmeister', 'admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'inspect');
    $notes  = (string) ($_POST['notes'] ?? '');

    if ($action === 'reinspect') {
        $res = reinspect_pass($pdo, $id, (int) $user['id'], $notes);
        $okMsg = 'Nacharbeit abgenommen — Fall geschlossen.';
    } elseif ($action === 'escalate') {
        $res = escalate_case($pdo, $id, (int) $user['id'], $notes);
        $okMsg = 'An den Vorstand eskaliert.';
    } else {
        $res = record_inspection($pdo, $id, (int) $user['id'], (string) ($_POST['result'] ?? ''), $notes, (string) ($_POST['rework_due'] ?? '') ?: null);
        $okMsg = 'Abnahme gespeichert.';
    }

    if ($res === true) {
        $msg = $okMsg;
        if (!empty($_FILES['fotos']['name'][0])) {
            $up = store_inspection_photos($pdo, $id, $_FILES['fotos'], (int) $user['id']);
            if ($up['saved'] > 0) { $msg .= ' ' . $up['saved'] . ' Foto(s) dokumentiert.'; }
            if ($up['errors']) { flash_set('error', implode(' ', array_unique($up['errors']))); }
        }
        try {
            if ($action === 'escalate') { notify_staff_case($pdo, $id, 'Eskalation an Vorstand'); }
            else { notify_inspection($pdo, $id); }
        } catch (Throwable $e) {}
        flash_set('success', $msg);
    } else {
        flash_set('error', implode(' ', (array) $res));
    }
    header('Location: /hafenmeister/abnahmen.php');
    exit;
}

$open       = open_inspections($pdo);
$reworkOpen = rework_bookings($pdo);
$checklist  = inspection_checklist($pdo);
$done       = recent_inspections($pdo, 8);

page_start('Abnahmen', $user, 'hm-abnahmen');
page_header('Abnahmen', 'Prüfen, Fotos zur Doku anhängen, abnehmen. Bei Mängeln: Nacharbeit mit Frist oder an den Vorstand eskalieren.');
?>
<div class="container-page" x-data="lightbox()">
    <?php if ($checklist): ?>
        <div class="card p-4 mb-6 bg-himmel-light/40">
            <span class="text-sm font-semibold text-navy">Checkliste:</span>
            <span class="text-sm text-schiefer"><?= e(implode(' · ', array_column($checklist, 'name'))) ?></span>
        </div>
    <?php endif; ?>

    <!-- 1) Abnahme fällig -->
    <h2 class="text-lg font-semibold text-navy mb-3">Abnahme fällig</h2>
    <?php if (!$open): ?>
        <div class="card p-6 text-center text-schiefer text-sm mb-8">Keine fälligen Abnahmen.</div>
    <?php else: ?>
        <div class="space-y-4 mb-8">
            <?php foreach ($open as $b): ?>
                <div class="card p-5">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-3">
                        <div class="font-ui font-semibold text-navy"><?= e(fmt_date($b['booking_date'])) ?> · <?= e(slot_label($b['slot'])) ?></div>
                        <?= member_name($b['member_name']) ?>
                        <div class="text-sm text-schiefer"><?= (int) $b['party_size'] ?> Pers.</div>
                        <div class="ml-auto text-xs text-schiefer">Endete <?= e(fmt_dt($b['end_utc'])) ?></div>
                    </div>
                    <a href="/hafenmeister/abnahme.php?id=<?= (int) $b['id'] ?>" class="btn-primary"><?= icon('clipboard','h-4 w-4') ?> Abnahme durchführen →</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- 2) Nacharbeit offen -->
    <?php if ($reworkOpen): ?>
        <h2 class="text-lg font-semibold text-navy mb-3">Nacharbeit offen <span class="badge badge-rework"><?= count($reworkOpen) ?></span></h2>
        <div class="space-y-4 mb-8">
            <?php foreach ($reworkOpen as $b): ?>
                <div class="card p-5">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <div class="font-ui font-semibold text-navy"><?= e(fmt_date($b['booking_date'])) ?> · <?= e(slot_label($b['slot'])) ?></div>
                        <?= member_name($b['member_name']) ?>
                        <?php if ($b['rework_due']): ?><span class="ml-auto badge badge-pending">Frist <?= e(fmt_date($b['rework_due'])) ?></span><?php endif; ?>
                    </div>
                    <?php if ($b['inspection_notes']): ?><p class="mt-2 text-sm text-schiefer">Beanstandung: <?= e($b['inspection_notes']) ?></p><?php endif; ?>
                    <?php if ($b['photos']): ?>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <?php foreach ($b['photos'] as $p): ?><img src="<?= e($p) ?>" @click="open('<?= e($p) ?>')" class="h-16 w-16 rounded-lg object-cover ring-1 ring-black/10 cursor-pointer" alt="Foto" loading="lazy"><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data" class="mt-3 flex flex-col sm:flex-row gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <input class="field flex-1" type="text" name="notes" placeholder="Notiz (bei Eskalation: Grund)">
                        <div class="flex gap-2">
                            <button class="btn-primary btn-sm" type="submit" name="action" value="reinspect">Erneut abnehmen (ok)</button>
                            <button class="btn-ghost btn-sm text-akzent" type="submit" name="action" value="escalate">An Vorstand</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- 3) Doku-Archiv -->
    <?php if ($done): ?>
        <h2 class="text-lg font-semibold text-navy mb-3">Dokumentierte Abnahmen</h2>
        <div class="space-y-3">
            <?php foreach ($done as $b): ?>
                <div class="card p-5">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <div class="font-ui font-medium text-navy"><?= e(fmt_date($b['booking_date'])) ?> · <?= e(slot_label($b['slot'])) ?></div>
                        <?= member_name($b['member_name']) ?>
                        <span class="ml-auto"><?= status_badge($b['inspection_result']) ?></span>
                    </div>
                    <?php if ($b['inspection_notes']): ?><p class="mt-2 text-sm text-schiefer"><?= e($b['inspection_notes']) ?></p><?php endif; ?>
                    <?php if ($b['photos']): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <?php foreach ($b['photos'] as $p): ?><img src="<?= e($p) ?>" @click="open('<?= e($p) ?>')" class="h-20 w-20 rounded-lg object-cover ring-1 ring-black/10 cursor-pointer hover:opacity-90" alt="Abnahme-Foto" loading="lazy"><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-2 text-xs text-schiefer/70">abgenommen <?= e(fmt_dt($b['inspected_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div x-show="url" x-cloak @keydown.escape.window="url=null" class="fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4" @click="url=null" role="dialog" aria-modal="true">
        <img :src="url" alt="Foto groß" class="max-h-[85vh] max-w-full rounded-lg object-contain">
    </div>
</div>
<script>function lightbox(){ return { url:null, open(u){ this.url=u; } }; }</script>
<?php
page_end();
