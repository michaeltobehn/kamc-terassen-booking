<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/actions.php';

$user = require_role('hafenmeister', 'admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id  = (int) ($_POST['id'] ?? 0);
    $res = record_inspection($pdo, $id, (int) $user['id'], (string) ($_POST['result'] ?? ''), (string) ($_POST['notes'] ?? ''));
    if ($res === true) {
        $msg = 'Abnahme gespeichert.';
        if (!empty($_FILES['fotos']['name'][0])) {
            $up = store_inspection_photos($pdo, $id, $_FILES['fotos'], (int) $user['id']);
            if ($up['saved'] > 0) {
                $msg .= ' ' . $up['saved'] . ' Foto(s) dokumentiert.';
            }
            if ($up['errors']) {
                flash_set('error', implode(' ', array_unique($up['errors'])));
            }
        }
        flash_set('success', $msg);
    } else {
        flash_set('error', implode(' ', (array) $res));
    }
    header('Location: /hafenmeister/abnahmen.php');
    exit;
}

$open      = open_inspections($pdo);
$checklist = inspection_checklist($pdo);
$done      = recent_inspections($pdo, 8);

page_start('Abnahmen', $user, 'hm-abnahmen');
page_header('Offene Abnahmen', 'Termin vorbei → prüfen, Fotos zur Doku anhängen, abnehmen.');
?>
<div class="container-page" x-data="lightbox()">
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
                        <?= member_name($b['member_name']) ?>
                        <div class="text-sm text-schiefer"><?= (int) $b['party_size'] ?> Pers.</div>
                        <div class="ml-auto text-xs text-schiefer">Endete <?= e(fmt_dt($b['end_utc'])) ?></div>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="space-y-3"
                          x-data="{names:''}">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <input class="field w-full" type="text" name="notes" placeholder="Notiz (Grill ok / Brandfleck / gekehrt …)">
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="btn-ghost btn-sm cursor-pointer">
                                <?= icon('clipboard','h-4 w-4') ?> Fotos anhängen
                                <input type="file" name="fotos[]" accept="image/*" multiple class="hidden"
                                       @change="names = Array.from($event.target.files).map(f=>f.name).join(', ')">
                            </label>
                            <span class="text-xs text-schiefer truncate" x-text="names || 'JPG/PNG/WebP · für die Doku'"></span>
                            <div class="ml-auto flex gap-2">
                                <button class="btn-primary btn-sm" type="submit" name="result" value="passed">Abnahme ok</button>
                                <button class="btn-akzent btn-sm" type="submit" name="result" value="rework">Nacharbeit</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Doku-Archiv -->
    <?php if ($done): ?>
        <h2 class="text-lg font-semibold text-navy mt-10 mb-4">Dokumentierte Abnahmen</h2>
        <div class="space-y-3">
            <?php foreach ($done as $b): ?>
                <div class="card p-5">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <div class="font-ui font-medium text-navy"><?= e(fmt_date($b['booking_date'])) ?> · <?= e(slot_label($b['slot'])) ?></div>
                        <?= member_name($b['member_name']) ?>
                        <span class="ml-auto"><?= status_badge($b['inspection_result']) ?></span>
                    </div>
                    <?php if ($b['inspection_notes']): ?>
                        <p class="mt-2 text-sm text-schiefer"><?= e($b['inspection_notes']) ?></p>
                    <?php endif; ?>
                    <?php if ($b['photos']): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <?php foreach ($b['photos'] as $p): ?>
                                <img src="<?= e($p) ?>" alt="Abnahme-Foto" loading="lazy"
                                     @click="open('<?= e($p) ?>')"
                                     class="h-20 w-20 rounded-lg object-cover ring-1 ring-black/10 cursor-pointer hover:opacity-90">
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="mt-2 text-xs text-schiefer/70">Keine Fotos hinterlegt.</p>
                    <?php endif; ?>
                    <div class="mt-2 text-xs text-schiefer/70">abgenommen <?= e(fmt_dt($b['inspected_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Foto-Lightbox -->
    <div x-show="url" x-cloak @keydown.escape.window="url=null"
         class="fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4" @click="url=null" role="dialog" aria-modal="true">
        <img :src="url" alt="Abnahme-Foto groß" class="max-h-[85vh] max-w-full rounded-lg object-contain">
    </div>
</div>
<script>
function lightbox(){ return { url:null, open(u){ this.url=u; } }; }
</script>
<?php
page_end();
