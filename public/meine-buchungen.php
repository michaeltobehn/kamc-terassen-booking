<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/actions.php';

$user = require_login();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'cancel') {
        $res = cancel_booking($pdo, (int) ($_POST['id'] ?? 0), (int) $user['id']);
        flash_set($res === true ? 'success' : 'error', $res === true ? 'Buchung storniert — der Slot ist wieder frei.' : implode(' ', (array) $res));
    }
    header('Location: /meine-buchungen.php');
    exit;
}

$bookings = bookings_for_member($pdo, (int) $user['id']);

page_start('Meine Buchungen', $user, 'meine');
page_header('Meine Buchungen', 'Status deiner Anfragen. Eigene Buchungen kannst du bis zum Termin stornieren.');
?>
<div class="container-page">
    <?php if (!$bookings): ?>
        <div class="card p-8 text-center">
            <p class="text-schiefer">Du hast noch keine Buchungen.</p>
            <a href="/buchen.php" class="btn-akzent mt-4">Jetzt buchen</a>
        </div>
    <?php else: ?>
        <div class="card divide-y divide-nebel overflow-hidden">
            <?php foreach ($bookings as $b): ?>
                <?php $isFuture = $b['end_utc'] >= now_utc(); $canCancel = $isFuture && in_array($b['status'], ['pending', 'confirmed'], true); ?>
                <div class="p-5 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-[9rem]">
                        <div class="font-ui font-semibold text-navy"><?= e(fmt_date($b['booking_date'])) ?></div>
                        <div class="text-sm text-schiefer"><?= e(slot_label($b['slot'])) ?></div>
                    </div>
                    <div class="text-sm text-schiefer">
                        <?= (int) $b['party_size'] ?> Personen
                        <?php if ($b['purpose']): ?> · <?= e($b['purpose']) ?><?php endif; ?>
                    </div>
                    <div class="ml-auto flex items-center gap-3">
                        <?= status_badge($b['status']) ?>
                        <?php if ($b['inspection_result']): ?><?= status_badge($b['inspection_result']) ?><?php endif; ?>
                        <?php if ($canCancel): ?>
                            <form method="post" onsubmit="return confirm('Diese Buchung wirklich stornieren?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                <button class="btn-ghost btn-sm text-akzent" type="submit">Stornieren</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if ($b['status'] === 'rejected' && $b['decision_note']): ?>
                        <div class="w-full text-sm text-schiefer bg-nebel/50 rounded-md px-3 py-2">Grund: <?= e($b['decision_note']) ?></div>
                    <?php endif; ?>
                    <?php if ($b['inspection_result'] === 'rework' && $b['inspection_notes']): ?>
                        <div class="w-full text-sm text-amber-800 bg-amber-50 rounded-md px-3 py-2">Nacharbeit: <?= e($b['inspection_notes']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
page_end();
