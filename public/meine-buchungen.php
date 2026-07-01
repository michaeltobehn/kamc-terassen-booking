<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/actions.php';
require __DIR__ . '/../src/mail.php';

$user = require_login();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'cancel') {
        $res = cancel_booking($pdo, $id, (int) $user['id']);
        $ok  = 'Buchung storniert — der Slot ist wieder frei.';
    } elseif ($action === 'dispute') {
        $res = member_dispute($pdo, $id, (int) $user['id'], (string) ($_POST['note'] ?? ''));
        if ($res === true) { try { notify_staff_case($pdo, $id, 'Mitglied-Widerspruch'); } catch (Throwable $e) {} }
        $ok  = 'Widerspruch eingereicht — der Vorstand prüft.';
    } elseif ($action === 'member_done') {
        $res = member_mark_done($pdo, $id, (int) $user['id'], (string) ($_POST['note'] ?? ''));
        $ok  = 'Danke — die Hafenmeisterei prüft die Nacharbeit.';
    } else {
        $res = ['Unbekannte Aktion.'];
        $ok  = '';
    }
    flash_set($res === true ? 'success' : 'error', $res === true ? $ok : implode(' ', (array) $res));
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
            <a href="/lounge.php" class="btn-akzent mt-4"><?= icon('plus','h-4 w-4') ?> Jetzt buchen</a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($bookings as $b): ?>
                <?php $isFuture = $b['end_utc'] >= now_utc(); $canCancel = $isFuture && in_array($b['status'], ['pending', 'confirmed'], true); ?>
                <div class="card p-5" x-data="{dispute:false}">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <div class="min-w-[9rem]">
                            <div class="font-ui font-semibold text-navy"><?= e(fmt_date($b['booking_date'])) ?></div>
                            <div class="text-sm text-schiefer"><?= e(slot_label($b['slot'])) ?></div>
                        </div>
                        <div class="text-sm text-schiefer">
                            <?= (int) $b['party_size'] ?> Personen<?php if ($b['purpose']): ?> · <?= e($b['purpose']) ?><?php endif; ?>
                        </div>
                        <div class="ml-auto flex items-center gap-2">
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
                    </div>

                    <?php if ($b['status'] === 'rejected' && $b['decision_note']): ?>
                        <div class="mt-3 text-sm text-schiefer bg-nebel/50 rounded-md px-3 py-2">Grund der Ablehnung: <?= e($b['decision_note']) ?></div>
                    <?php endif; ?>

                    <?php if (in_array($b['case_status'], ['rework', 'disputed', 'escalated', 'resolved'], true)): ?>
                        <div class="mt-3 rounded-lg ring-1 ring-black/[0.07] p-3">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-semibold text-navy">Abnahme-Beanstandung</span>
                                <?php if ($b['case_status'] === 'rework'): ?><span class="badge badge-rework">Nacharbeit nötig</span>
                                <?php elseif ($b['case_status'] === 'disputed'): ?><span class="badge badge-disputed">Widerspruch eingereicht</span>
                                <?php elseif ($b['case_status'] === 'escalated'): ?><span class="badge badge-escalated">beim Vorstand</span>
                                <?php else: ?><span class="badge badge-resolved">geklärt<?= $b['resolution'] ? ' · ' . e($b['resolution']) : '' ?></span><?php endif; ?>
                                <?php if ($b['case_status'] === 'rework' && $b['rework_due']): ?><span class="ml-auto text-xs text-schiefer">Frist: <?= e(fmt_date($b['rework_due'])) ?></span><?php endif; ?>
                            </div>
                            <?php if ($b['inspection_notes']): ?><p class="text-sm text-schiefer"><?= e($b['inspection_notes']) ?></p><?php endif; ?>

                            <?php if ($b['case_status'] === 'rework'): ?>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="member_done">
                                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                        <button class="btn-primary btn-sm" type="submit">Nacharbeit erledigt melden</button>
                                    </form>
                                    <button class="btn-ghost btn-sm text-akzent" @click="dispute=!dispute" type="button">Widersprechen</button>
                                </div>
                                <form method="post" x-show="dispute" x-cloak class="mt-2 flex flex-col sm:flex-row gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="dispute">
                                    <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                    <input class="field flex-1" type="text" name="note" required placeholder="Warum widersprichst du? (geht an den Vorstand)">
                                    <button class="btn-akzent btn-sm" type="submit">Widerspruch senden</button>
                                </form>
                            <?php elseif ($b['case_status'] === 'disputed'): ?>
                                <p class="mt-2 text-xs text-schiefer">Dein Widerspruch liegt beim Vorstand zur Klärung.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
page_end();
