<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/booking.php';

$user = require_login();
$pdo  = db();
$cfg  = require __DIR__ . '/../config.php';
$hausVersion = (string) ($cfg['app']['hausordnung_version'] ?? '2026-1');

$errors = [];
$in = [
    'booking_date' => (string) ($_GET['date'] ?? $_POST['booking_date'] ?? ''),
    'slot'         => (string) ($_GET['slot'] ?? $_POST['slot'] ?? ''),
    'party_size'   => (string) ($_POST['party_size'] ?? ''),
    'purpose'      => (string) ($_POST['purpose'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = [
        'member_id'            => (int) $user['id'],
        'booking_date'         => (string) ($_POST['booking_date'] ?? ''),
        'slot'                 => (string) ($_POST['slot'] ?? ''),
        'party_size'           => ctype_digit((string) ($_POST['party_size'] ?? '')) ? (int) $_POST['party_size'] : ($_POST['party_size'] ?? null),
        'purpose'              => (string) ($_POST['purpose'] ?? ''),
        'hausordnung_accepted' => !empty($_POST['hausordnung']),
        'hausordnung_version'  => $hausVersion,
    ];
    $result = create_booking($pdo, $data);
    if (is_int($result)) {
        require_once __DIR__ . '/../src/mail.php';
        try { notify_booking_created($pdo, (int) $result); } catch (Throwable $e) {}
        flash_set('success', 'Anfrage eingegangen! Die Hafenmeisterei prüft deine Buchung. Du bekommst Bescheid.');
        header('Location: /meine-buchungen.php');
        exit;
    }
    $errors = $result;
    $in['party_size'] = (string) ($_POST['party_size'] ?? '');
}

page_start('Buchen', $user, 'buchen');
page_header('Neue Buchung', 'Zwei feste Slots pro Tag — Tag (bis 18:00) oder Abend (ab 18:00). Max. 16 Personen, mind. 24 h Vorlauf.');
?>
<div class="container-page max-w-2xl">
    <?php if ($errors): ?>
        <div class="mb-6 rounded-lg bg-akzent/10 text-akzent-700 ring-1 ring-akzent/20 px-4 py-3 text-sm">
            <p class="font-semibold mb-1">Buchung nicht möglich:</p>
            <ul class="list-disc list-inside space-y-0.5">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="card p-6 sm:p-8 space-y-6" x-data="{accepted:false}">
        <?= csrf_field() ?>
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="label" for="booking_date">Datum</label>
                <input class="field" type="date" id="booking_date" name="booking_date" required value="<?= e($in['booking_date']) ?>">
            </div>
            <div>
                <label class="label" for="slot">Slot</label>
                <select class="field" id="slot" name="slot" required>
                    <option value="">Bitte wählen…</option>
                    <option value="tag"   <?= $in['slot'] === 'tag' ? 'selected' : '' ?>>Tag (Öffnung bis 18:00)</option>
                    <option value="abend" <?= $in['slot'] === 'abend' ? 'selected' : '' ?>>Abend (18:00 bis Schließung)</option>
                </select>
            </div>
        </div>

        <div>
            <label class="label" for="party_size">Personenzahl (max. 16)</label>
            <input class="field" type="number" id="party_size" name="party_size" min="1" max="16" required
                   value="<?= e($in['party_size']) ?>" placeholder="z. B. 10">
        </div>

        <div>
            <label class="label" for="purpose">Anlass <span class="text-schiefer font-normal">(optional)</span></label>
            <input class="field" type="text" id="purpose" name="purpose" maxlength="200"
                   value="<?= e($in['purpose']) ?>" placeholder="z. B. Geburtstag, Crew-Treffen">
        </div>

        <div class="rounded-lg bg-himmel-light/40 ring-1 ring-navy/10 p-4">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="hausordnung" value="1" required x-model="accepted"
                       class="mt-1 h-5 w-5 rounded border-navy/30 text-akzent focus:ring-akzent">
                <span class="text-sm text-navy-950">
                    Ich habe die <a href="/hausordnung.php" target="_blank" class="text-akzent hover:underline font-medium">Hausordnung</a>
                    (Version <?= e($hausVersion) ?>) gelesen und akzeptiere sie — insbesondere:
                    <strong>keine Musik / keine Lautsprecher</strong>, Selbstversorgung, Grill reinigen, Kühlschrank leeren.
                </span>
            </label>
        </div>

        <div class="flex items-center gap-3">
            <button class="btn-akzent" type="submit" :disabled="!accepted">Buchung anfragen</button>
            <a href="/kalender.php" class="btn-ghost">Zum Kalender</a>
        </div>
        <p class="text-xs text-schiefer">
            Datenschutz-Hinweis: Wir speichern Name, Datum, Personenzahl und Anlass zur Bearbeitung deiner Buchung.
        </p>
    </form>
</div>
<?php
page_end();
