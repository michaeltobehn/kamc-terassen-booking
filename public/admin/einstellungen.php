<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/actions.php';

$user = require_role('admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = (string) ($_POST['form'] ?? '');
    $res = true;
    if ($form === 'settings') {
        $res = save_settings($pdo, $_POST);
        $msg = 'Einstellungen gespeichert.';
    } elseif ($form === 'hours') {
        $res = save_opening_hours($pdo, $_POST);
        $msg = 'Öffnungszeiten gespeichert.';
    } elseif ($form === 'blackout_add') {
        $res = add_blackout($pdo, (string) ($_POST['blackout_date'] ?? ''), (string) ($_POST['reason'] ?? ''));
        $msg = 'Sperrtag hinzugefügt.';
    } elseif ($form === 'blackout_del') {
        delete_blackout($pdo, (int) ($_POST['id'] ?? 0));
        $msg = 'Sperrtag entfernt.';
    }
    flash_set($res === true ? 'success' : 'error', $res === true ? ($msg ?? 'Gespeichert.') : implode(' ', (array) $res));
    header('Location: /admin/einstellungen.php');
    exit;
}

$s     = settings_raw($pdo);
$hours = opening_hours_all($pdo);
$black = blackouts_all($pdo);
$wd    = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
$hm    = fn($t) => substr((string) $t, 0, 5);

page_start('Einstellungen', $user, 'admin-settings');
page_header('Einstellungen', 'Slots, Vorlauf, Öffnungszeiten und Sperrtage.');
?>
<div class="container-page grid gap-6 lg:grid-cols-2">
    <!-- Grundeinstellungen -->
    <form method="post" class="card p-6 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="settings">
        <h2 class="text-lg font-semibold">Grundeinstellungen</h2>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="label" for="ev">Slot-Grenze Tag/Abend</label>
                <input class="field" type="time" id="ev" name="evening_start_local" value="<?= e($hm($s['evening_start_local'] ?? '18:00')) ?>"></div>
            <div><label class="label" for="lead">Vorlauf (Stunden)</label>
                <input class="field" type="number" id="lead" name="lead_time_hours" min="0" value="<?= (int) ($s['lead_time_hours'] ?? 24) ?>"></div>
            <div><label class="label" for="exp">Pending-Expiry (Stunden)</label>
                <input class="field" type="number" id="exp" name="pending_expiry_hours" min="0" value="<?= (int) ($s['pending_expiry_hours'] ?? 48) ?>"></div>
            <div><label class="label" for="max">Max. Personen</label>
                <input class="field" type="number" id="max" name="max_party_size" min="1" value="<?= (int) ($s['max_party_size'] ?? 16) ?>"></div>
            <div class="sm:col-span-2"><label class="label" for="win">Buchungsfenster bis</label>
                <input class="field" type="date" id="win" name="booking_window_end" value="<?= e((string) ($s['booking_window_end'] ?? '')) ?>"></div>
        </div>
        <button class="btn-primary" type="submit">Speichern</button>
    </form>

    <!-- Öffnungszeiten -->
    <form method="post" class="card p-6 space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="hours">
        <h2 class="text-lg font-semibold">Öffnungszeiten</h2>
        <p class="text-xs text-schiefer -mt-1">Schließzeit ≤ Öffnungszeit ⇒ Folgetag (z. B. 08:00 → 02:00).</p>
        <div class="space-y-2">
            <?php foreach ($wd as $n => $name): $h = $hours[$n] ?? []; ?>
                <div class="flex items-center gap-2">
                    <span class="w-24 text-sm font-ui"><?= $name ?></span>
                    <input class="field !py-1.5 w-28" type="time" name="open[<?= $n ?>]"  value="<?= e($hm($h['open_time'] ?? '08:00')) ?>">
                    <span class="text-schiefer">–</span>
                    <input class="field !py-1.5 w-28" type="time" name="close[<?= $n ?>]" value="<?= e($hm($h['close_time'] ?? '02:00')) ?>">
                    <label class="flex items-center gap-1.5 text-sm text-schiefer ml-2">
                        <input type="checkbox" name="closed[<?= $n ?>]" value="1" <?= !empty($h['is_closed']) ? 'checked' : '' ?>
                               class="h-4 w-4 rounded border-navy/30 text-akzent"> zu
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn-primary" type="submit">Speichern</button>
    </form>

    <!-- Blackouts -->
    <div class="card p-6 space-y-4 lg:col-span-2">
        <h2 class="text-lg font-semibold">Sperrtage / Blackouts</h2>
        <form method="post" class="flex flex-wrap items-end gap-3">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="blackout_add">
            <div><label class="label" for="bd">Datum</label><input class="field" type="date" id="bd" name="blackout_date" required></div>
            <div class="flex-1 min-w-[12rem]"><label class="label" for="br">Grund</label>
                <input class="field" type="text" id="br" name="reason" placeholder="z. B. Sommerfest (Vereinsveranstaltung)"></div>
            <button class="btn-primary" type="submit">Sperrtag hinzufügen</button>
        </form>
        <?php if ($black): ?>
            <div class="divide-y divide-nebel">
                <?php foreach ($black as $bl): ?>
                    <div class="py-2.5 flex items-center gap-3">
                        <span class="font-ui font-medium w-32"><?= e(fmt_date($bl['blackout_date'])) ?></span>
                        <span class="text-sm text-schiefer flex-1"><?= e((string) ($bl['reason'] ?? '')) ?></span>
                        <form method="post" onsubmit="return confirm('Sperrtag entfernen?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form" value="blackout_del">
                            <input type="hidden" name="id" value="<?= (int) $bl['id'] ?>">
                            <button class="btn-ghost btn-sm text-akzent" type="submit">Entfernen</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-schiefer">Keine Sperrtage.</p>
        <?php endif; ?>
    </div>
</div>
<?php
page_end();
