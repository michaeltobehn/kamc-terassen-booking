<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/booking.php';
require __DIR__ . '/../src/repo.php';

/**
 * Listing-Detailseite „Lounge oben" im Airbnb-Stil: Galerie + zweispaltiges
 * Layout mit sticky Buchungs-Card. Öffentlich ansehbar; Buchen erfordert Login.
 */
$user = current_user();
$pdo  = db();
$cfg  = require __DIR__ . '/../config.php';
$hausVersion = (string) ($cfg['app']['hausordnung_version'] ?? '2026-1');
$amenities = amenities_all($pdo, true);

$errors = [];
$prefill = [
    'booking_date' => (string) ($_GET['date'] ?? ''),
    'slot'         => (string) ($_GET['slot'] ?? ''),
    'party_size'   => '',
];

// Buchung nur für eingeloggte Mitglieder verarbeiten.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
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
    $res = create_booking($pdo, $data);
    if (is_int($res)) {
        flash_set('success', 'Anfrage eingegangen! Die Hafenmeisterei prüft deine Buchung.');
        header('Location: /meine-buchungen.php');
        exit;
    }
    $errors = $res;
    $prefill = ['booking_date' => $data['booking_date'], 'slot' => $data['slot'], 'party_size' => (string) ($_POST['party_size'] ?? '')];
}

// Galerie-Kacheln (Platzhalter bis echte Fotos vorliegen; Airbnb-1+4-Grid).
$tiles = [
    ['cap' => 'Terrasse & Rheinblick', 'icon' => 'anchor'],
    ['cap' => 'Innenbereich',          'icon' => 'sparkle'],
    ['cap' => 'Gasgrill „Burnhard"',   'icon' => 'sun'],
    ['cap' => 'Sitzgruppe',            'icon' => 'users'],
    ['cap' => 'Deck Chairs',           'icon' => 'moon'],
];

page_start('Lounge oben', $user, '', $user ? 'app' : 'public');
?>
<div class="container-page <?= $user ? '' : 'pt-24' ?>">

    <!-- Titelzeile -->
    <div class="mb-4">
        <h1 class="text-2xl sm:text-3xl font-display font-semibold text-navy">Lounge oben — die Clubterrasse</h1>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-schiefer">
            <span class="font-medium text-navy">★ Vereinsintern</span>
            <span>·</span><span>Rheinauhafen Köln</span>
            <span>·</span><span>Terrasse + Innenbereich</span>
            <span>·</span><span>bis 16 Gäste</span>
        </div>
    </div>

    <!-- Galerie (1 groß + 4 klein) -->
    <div class="grid grid-cols-4 grid-rows-2 gap-2 h-[52vh] max-h-[460px] rounded-xl2 overflow-hidden">
        <div class="gallery-tile col-span-2 row-span-2"><?= icon($tiles[0]['icon'], 'absolute right-4 top-4 h-8 w-8 text-navy/15') ?><span class="gallery-cap"><?= e($tiles[0]['cap']) ?></span></div>
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="gallery-tile"><?= icon($tiles[$i]['icon'], 'absolute right-3 top-3 h-6 w-6 text-navy/15') ?><span class="gallery-cap"><?= e($tiles[$i]['cap']) ?></span></div>
        <?php endfor; ?>
    </div>
    <p class="mt-2 text-xs text-schiefer">Fotos folgen — Platzhalter im KAMC-Look. Echte Aufnahmen von Terrasse, Grill &amp; Innenbereich lassen sich pro Ausstattungs-Item pflegen.</p>

    <div class="mt-8 grid lg:grid-cols-3 gap-10 lg:gap-14 items-start">
        <!-- LINKS: Inhalt -->
        <div class="lg:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-navy">Die ganze Lounge für deinen Anlass</h2>
                    <p class="mt-1 text-schiefer">Ein Slot · bis 16 Personen · Selbstversorgung · kostenlos für Mitglieder</p>
                </div>
                <img src="/assets/img/kamc-logo.png" alt="" class="h-12 w-12 rounded-full ring-1 ring-navy/10 shrink-0">
            </div>

            <div class="divider-y"></div>

            <!-- Highlights -->
            <div class="space-y-5">
                <?php
                $highlights = [
                    ['anchor', 'Direkt am Rheinauhafen', 'Terrasse mit Blick aufs Wasser, ruhige Lage im Club.'],
                    ['sun', 'Zwei feste Slots', 'Tag (bis 18:00) oder Abend (18:00 bis Schließung) — keine Zeitwahl-Rechnerei.'],
                    ['sparkle', 'Grill inklusive', 'Gasgrill „Burnhard" inkl. Ersatz-Gasflasche steht bereit.'],
                    ['shield', 'Kostenlos & fair', 'Kein Entgelt im Pilot — gleiche Regeln für alle Mitglieder.'],
                ];
                foreach ($highlights as $h): ?>
                    <div class="flex gap-4">
                        <span class="ico shrink-0"><?= icon($h[0]) ?></span>
                        <div><div class="font-semibold text-navy"><?= e($h[1]) ?></div>
                             <div class="text-sm text-schiefer"><?= e($h[2]) ?></div></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="divider-y"></div>

            <!-- Beschreibung -->
            <div class="prose-kamc">
                <h3 class="text-lg font-semibold text-navy mb-2">Über die Lounge oben</h3>
                <p class="text-navy-950/80 leading-relaxed">
                    Das Obergeschoss des KAMC — Terrasse und Innenbereich mit rund 24 Sitzplätzen — steht
                    Vereinsmitgliedern für private Anlässe zur Verfügung: Geburtstage, Crew-Treffen, kleine Feiern.
                    Du buchst einen festen Slot, bringst Speisen und Getränke selbst mit und nutzt den vom Verein
                    gestellten Gasgrill. Schlüsselübergabe und Abnahme laufen persönlich über die Hafenmeisterei.
                </p>
            </div>

            <div class="divider-y"></div>

            <!-- Amenities -->
            <h3 class="text-lg font-semibold text-navy mb-2">Das ist alles dabei</h3>
            <div class="grid sm:grid-cols-2 gap-x-8">
                <?php foreach ($amenities as $a): ?>
                    <div class="amenity-row border-b border-nebel/70">
                        <span class="text-navy shrink-0 mt-0.5"><?= icon('check', 'h-5 w-5') ?></span>
                        <div>
                            <div class="font-medium text-navy-950"><?= e($a['name']) ?></div>
                            <?php if ($a['notes']): ?><div class="text-xs text-schiefer mt-0.5">Hinweis: <?= e($a['notes']) ?></div><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="divider-y"></div>

            <!-- Hausregeln -->
            <h3 class="text-lg font-semibold text-navy mb-3">Gut zu wissen</h3>
            <div class="grid sm:grid-cols-2 gap-4 text-sm">
                <div class="flex gap-3"><span class="text-akzent"><?= icon('moon','h-5 w-5') ?></span><div><span class="font-medium">Keine Musik / Lautsprecher</span><div class="text-schiefer">Rücksicht auf Hafen &amp; Nachbarn.</div></div></div>
                <div class="flex gap-3"><span class="text-navy"><?= icon('users','h-5 w-5') ?></span><div><span class="font-medium">Max. 16 Personen</span><div class="text-schiefer">Gäste nur in Begleitung eines Mitglieds.</div></div></div>
                <div class="flex gap-3"><span class="text-navy"><?= icon('key','h-5 w-5') ?></span><div><span class="font-medium">Schlüssel mit Begehung</span><div class="text-schiefer">Übergabe &amp; Rückgabe über die Hafenmeisterei.</div></div></div>
                <div class="flex gap-3"><span class="text-navy"><?= icon('clipboard','h-5 w-5') ?></span><div><span class="font-medium">Abnahme danach</span><div class="text-schiefer">Grill sauber, gekehrt, Kühlschrank leer.</div></div></div>
            </div>
            <a href="/hausordnung.php" class="inline-block mt-4 text-sm font-medium text-akzent hover:underline">Ganze Hausordnung lesen →</a>
        </div>

        <!-- RECHTS: sticky Buchungs-Card -->
        <div class="lg:sticky lg:top-24">
            <div class="book-card" x-data="booker(<?= $user ? 'true' : 'false' ?>)">
                <div class="flex items-baseline justify-between">
                    <div><span class="text-2xl font-display font-semibold text-navy">Kostenlos</span>
                         <span class="text-schiefer text-sm">/ Slot</span></div>
                    <span class="text-sm text-schiefer">★ Vereinsintern</span>
                </div>

                <?php if ($errors): ?>
                    <div class="mt-4 rounded-lg bg-akzent/10 text-akzent-700 ring-1 ring-akzent/20 px-3 py-2 text-sm">
                        <ul class="list-disc list-inside space-y-0.5"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="mt-4" x-ref="form">
                    <?= csrf_field() ?>
                    <!-- Datum/Slot/Gäste in einem gerahmten Block (Airbnb-Stil) -->
                    <div class="rounded-xl ring-1 ring-navy/15 divide-y divide-navy/10 overflow-hidden">
                        <label class="block px-3.5 pt-2.5">
                            <span class="text-[11px] font-ui font-semibold uppercase tracking-wide text-navy">Datum</span>
                            <input type="date" name="booking_date" required class="book-field !px-0 !py-1" x-model="date" @change="check()" value="<?= e($prefill['booking_date']) ?>">
                        </label>
                        <div class="grid grid-cols-2 divide-x divide-navy/10">
                            <label class="block px-3.5 py-2">
                                <span class="text-[11px] font-ui font-semibold uppercase tracking-wide text-navy">Slot</span>
                                <select name="slot" required class="book-field !px-0 !py-1" x-model="slot">
                                    <option value="">wählen</option>
                                    <option value="tag" <?= $prefill['slot']==='tag'?'selected':'' ?>>Tag · bis 18:00</option>
                                    <option value="abend" <?= $prefill['slot']==='abend'?'selected':'' ?>>Abend · ab 18:00</option>
                                </select>
                            </label>
                            <label class="block px-3.5 py-2">
                                <span class="text-[11px] font-ui font-semibold uppercase tracking-wide text-navy">Gäste</span>
                                <input type="number" name="party_size" min="1" max="16" required placeholder="1–16" class="book-field !px-0 !py-1" value="<?= e($prefill['party_size']) ?>">
                            </label>
                        </div>
                    </div>

                    <!-- Live-Verfügbarkeitshinweis -->
                    <div class="mt-2 min-h-[1.25rem] text-xs" x-show="date" x-cloak>
                        <span x-show="loading" class="text-schiefer">Prüfe Verfügbarkeit…</span>
                        <template x-if="!loading && avail">
                            <span :class="slotFree ? 'text-emerald-700' : 'text-akzent-700'"
                                  x-text="slotHint"></span>
                        </template>
                    </div>

                    <?php if ($user): ?>
                        <label class="flex items-start gap-2 mt-4 text-xs text-navy-950">
                            <input type="checkbox" name="hausordnung" value="1" required class="mt-0.5 h-4 w-4 rounded border-navy/30 text-akzent">
                            <span>Ich akzeptiere die <a href="/hausordnung.php" target="_blank" class="text-akzent hover:underline">Hausordnung</a> (v<?= e($hausVersion) ?>) — keine Musik, Selbstversorgung, Grill reinigen.</span>
                        </label>
                        <input type="text" name="purpose" maxlength="200" placeholder="Anlass (optional) — z. B. Geburtstag" class="field mt-3 !py-2 text-sm">
                        <button type="submit" class="btn-akzent w-full mt-4 text-base py-3">Buchung anfragen</button>
                        <p class="mt-3 text-center text-xs text-schiefer">Du wirst noch nicht belastet — Bestätigung durch die Hafenmeisterei.</p>
                    <?php else: ?>
                        <a href="/login.php" class="btn-akzent w-full mt-4 text-base py-3">Anmelden &amp; buchen</a>
                        <p class="mt-3 text-center text-xs text-schiefer">Buchen ist Mitgliedern vorbehalten. Zugang über den Vorstand.</p>
                    <?php endif; ?>
                </form>

                <div class="mt-5 pt-4 border-t border-nebel flex flex-wrap gap-2">
                    <span class="pill"><?= icon('users','h-4 w-4') ?> bis 16</span>
                    <span class="pill"><?= icon('sun','h-4 w-4') ?> Tag &amp; Abend</span>
                    <span class="pill"><?= icon('shield','h-4 w-4') ?> kostenlos</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function booker(isUser) {
    return {
        date: <?= json_encode($prefill['booking_date']) ?>, slot: <?= json_encode($prefill['slot']) ?>,
        avail: null, loading: false,
        get slotFree(){ if(!this.avail) return false; const s=this.slot||'tag'; return this.avail[s]==='frei'; },
        get slotHint(){
            if(!this.avail) return '';
            const map={frei:'frei',belegt:'belegt',blackout:'gesperrt',geschlossen:'geschlossen',vergangen:'nicht buchbar (Vorlauf/Vergangenheit)'};
            const t='Tag: '+(map[this.avail.tag]||this.avail.tag), a='Abend: '+(map[this.avail.abend]||this.avail.abend);
            return t+' · '+a;
        },
        async check(){
            if(!this.date){ this.avail=null; return; }
            this.loading=true; this.avail=null;
            try{ const r=await fetch('/availability.php?from='+this.date+'&to='+this.date); const d=await r.json(); this.avail=d[0]||null; }catch(e){}
            this.loading=false;
        },
        init(){ if(this.date) this.check(); }
    };
}
</script>
<?php
page_end();
