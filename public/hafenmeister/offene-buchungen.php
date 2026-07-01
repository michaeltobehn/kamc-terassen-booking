<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/actions.php';
require __DIR__ . '/../../src/mail.php';

$user = require_role('hafenmeister', 'admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = (string) ($_POST['note'] ?? '');
    $res = decide_booking($pdo, $id, (int) $user['id'], $decision, $note);
    if ($res === true) {
        try { notify_booking_decided($pdo, $id); } catch (Throwable $e) {}
        flash_set('success', $decision === 'confirmed' ? 'Buchung bestätigt. Schlüsselübergabe vorbereiten.' : 'Buchung abgelehnt. Das Mitglied wird informiert.');
    } else {
        flash_set('error', implode(' ', (array) $res));
    }
    header('Location: /hafenmeister/offene-buchungen.php');
    exit;
}

$pending = pending_bookings($pdo);

page_start('Freigaben', $user, 'hm-offen');
page_header('Offene Freigaben', 'Anfragen prüfen und bestätigen oder ablehnen. Eine Ablehnung braucht einen Grund.');
?>
<div class="container-page">
    <?php if (!$pending): ?>
        <div class="card p-8 text-center text-schiefer">Keine offenen Anfragen. 🎉</div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($pending as $b): ?>
                <div class="card p-5" x-data="{reject:false}">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <div>
                            <div class="font-ui font-semibold text-navy"><?= e(fmt_date($b['booking_date'])) ?> · <?= e(slot_label($b['slot'])) ?></div>
                            <div class="mt-0.5"><?= member_name($b['member_name'], $b['member_email']) ?></div>
                            <div class="text-sm text-schiefer">
                                <?= (int) $b['party_size'] ?> Personen
                                <?php if ($b['purpose']): ?> · <?= e($b['purpose']) ?><?php endif; ?>
                            </div>
                            <div class="text-xs text-schiefer mt-1">
                                Zeitfenster: <?= e(fmt_dt($b['start_utc'], 'd.m., H:i')) ?> – <?= e(fmt_dt($b['end_utc'], 'H:i')) ?> ·
                                Hausordnung akzeptiert (v<?= e($b['hausordnung_version']) ?>) am <?= e(fmt_dt($b['hausordnung_accepted_at'])) ?>
                            </div>
                        </div>
                        <div class="ml-auto flex items-center gap-2">
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                <input type="hidden" name="decision" value="confirmed">
                                <button class="btn-primary btn-sm" type="submit">Bestätigen</button>
                            </form>
                            <button class="btn-ghost btn-sm text-akzent" @click="reject=!reject">Ablehnen</button>
                        </div>
                    </div>
                    <form method="post" x-show="reject" x-cloak class="mt-4 flex flex-col sm:flex-row gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <input type="hidden" name="decision" value="rejected">
                        <input class="field flex-1" type="text" name="note" required placeholder="Ablehnungsgrund (z. B. Vereinsveranstaltung)…">
                        <button class="btn-akzent btn-sm" type="submit">Ablehnung senden</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
page_end();
