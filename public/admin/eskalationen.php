<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/actions.php';

$user = require_role('admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = resolve_case(
        $pdo,
        (int) ($_POST['id'] ?? 0),
        (int) $user['id'],
        (string) ($_POST['resolution'] ?? ''),
        (string) ($_POST['note'] ?? ''),
        !empty($_POST['block'])
    );
    flash_set($res === true ? 'success' : 'error', $res === true ? 'Fall abgeschlossen.' : implode(' ', (array) $res));
    header('Location: /admin/eskalationen.php');
    exit;
}

$cases = escalations($pdo);
$evLabel = [
    'passed' => 'Abnahme ok', 'rework' => 'Nacharbeit gesetzt', 'member_done' => 'Mitglied: erledigt gemeldet',
    'dispute' => 'Mitglied: Widerspruch', 'escalate' => 'an Vorstand eskaliert',
    'reinspect_pass' => 'Re-Abnahme ok', 'resolve' => 'Vorstand: aufgelöst',
];

page_start('Eskalationen', $user, 'admin-esc');
page_header('Eskalationen & Streitfälle', 'Fälle, die Hafenmeister nicht selbst abschließen können — Beschluss durch den Vorstand.');
?>
<div class="container-page" x-data="lightbox()">
    <?php if (!$cases): ?>
        <div class="card p-8 text-center text-schiefer">Keine offenen Eskalationen. 🎉</div>
    <?php else: ?>
        <div class="space-y-5">
            <?php foreach ($cases as $b): ?>
                <div class="card p-5">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <div class="font-ui font-semibold text-navy"><?= e(fmt_date($b['booking_date'])) ?> · <?= e(slot_label($b['slot'])) ?></div>
                        <?= member_name($b['member_name']) ?>
                        <span class="ml-auto flex items-center gap-2">
                            <?php if ((int) $b['booking_blocked'] === 1): ?><span class="badge badge-rejected">gesperrt</span><?php endif; ?>
                            <?= status_badge($b['case_status']) ?>
                        </span>
                    </div>

                    <?php if ($b['inspection_notes']): ?>
                        <p class="mt-3 text-sm"><span class="font-semibold text-navy">Beanstandung:</span> <span class="text-schiefer"><?= e($b['inspection_notes']) ?></span></p>
                    <?php endif; ?>

                    <?php if ($b['photos']): ?>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <?php foreach ($b['photos'] as $p): ?><img src="<?= e($p) ?>" @click="open('<?= e($p) ?>')" class="h-16 w-16 rounded-lg object-cover ring-1 ring-black/10 cursor-pointer" alt="Foto" loading="lazy"><?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Verlauf -->
                    <?php if ($b['events']): ?>
                        <div class="mt-3 rounded-lg bg-black/[0.02] ring-1 ring-black/[0.06] p-3">
                            <div class="text-[11px] font-ui font-semibold uppercase tracking-wide text-schiefer mb-1.5">Verlauf</div>
                            <ul class="space-y-1 text-sm">
                                <?php foreach ($b['events'] as $ev): ?>
                                    <li class="flex gap-2">
                                        <span class="text-schiefer whitespace-nowrap"><?= e(fmt_dt($ev['created_at'], 'd.m., H:i')) ?></span>
                                        <span class="font-medium text-navy"><?= e($evLabel[$ev['event_type']] ?? $ev['event_type']) ?></span>
                                        <?php if ($ev['actor_name']): ?><span class="text-schiefer/70">· <?= e($ev['actor_name']) ?></span><?php endif; ?>
                                        <?php if ($ev['note']): ?><span class="text-schiefer">— <?= e($ev['note']) ?></span><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Beschluss -->
                    <form method="post" class="mt-4 flex flex-wrap items-end gap-3 border-t border-black/[0.06] pt-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <div>
                            <label class="label" for="res<?= (int) $b['id'] ?>">Beschluss</label>
                            <select class="field" id="res<?= (int) $b['id'] ?>" name="resolution" required>
                                <option value="ok">Erledigt / in Ordnung</option>
                                <option value="kulanz">Kulanz (keine Folgen)</option>
                                <option value="kosten">Kostenbeteiligung</option>
                                <option value="sperre">Sperre + Kosten</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[14rem]">
                            <label class="label" for="note<?= (int) $b['id'] ?>">Notiz / Beschluss</label>
                            <input class="field" id="note<?= (int) $b['id'] ?>" type="text" name="note" placeholder="z. B. Reinigungskosten 40 €, mit Mitglied geklärt">
                        </div>
                        <label class="flex items-center gap-2 text-sm text-navy-950 mb-2.5">
                            <input type="checkbox" name="block" value="1" class="h-4 w-4 rounded border-navy/30 text-akzent"> Mitglied für Buchungen sperren
                        </label>
                        <button class="btn-primary mb-0.5" type="submit">Fall abschließen</button>
                    </form>
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
