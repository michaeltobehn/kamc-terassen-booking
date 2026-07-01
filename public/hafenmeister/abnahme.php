<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/repo.php';

$user = require_role('hafenmeister', 'admin');
$pdo  = db();

$id = (int) ($_GET['id'] ?? 0);
$b  = booking_by_id($pdo, $id);

// Nur offene, fällige Abnahmen zulassen.
if (!$b || $b['status'] !== 'confirmed' || $b['inspection_result'] !== null || $b['end_utc'] >= now_utc()) {
    flash_set('error', 'Diese Abnahme ist nicht (mehr) offen.');
    header('Location: /hafenmeister/abnahmen.php');
    exit;
}

$checklist = array_column(inspection_checklist($pdo), 'name');
$cfg  = require __DIR__ . '/../../config.php';

page_start('Abnahme', $user, 'hm-abnahmen');
?>
<div class="container-page max-w-xl" x-data="abnahme(<?= e(json_encode($checklist, JSON_UNESCAPED_UNICODE)) ?>)">
    <!-- Kopf: worum geht's -->
    <div class="mb-5">
        <a href="/hafenmeister/abnahmen.php" class="text-sm text-schiefer">← Abnahmen</a>
        <h1 class="mt-1 text-2xl font-display font-semibold text-navy">Abnahme durchführen</h1>
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="font-ui font-semibold text-navy"><?= e(fmt_date($b['booking_date'])) ?> · <?= e(slot_label($b['slot'])) ?></span>
            <?= member_name($b['member_name']) ?>
            <span class="text-sm text-schiefer"><?= (int) $b['party_size'] ?> Pers.</span>
        </div>
    </div>

    <!-- Checkliste: große Tap-Zeilen, je Punkt OK / Mangel -->
    <div class="card divide-y divide-nebel">
        <div class="p-4 text-sm font-ui font-semibold text-navy">Checkliste</div>
        <template x-for="item in items" :key="item">
            <div class="p-4 flex items-center gap-3">
                <span class="flex-1 text-navy-950" x-text="item"></span>
                <div class="flex gap-1.5">
                    <button type="button" @click="set(item,'ok')"
                            class="h-11 min-w-[3.25rem] rounded-lg text-sm font-medium ring-1 transition"
                            :class="state[item]==='ok' ? 'bg-emerald-600 text-white ring-emerald-600' : 'bg-white text-emerald-700 ring-emerald-600/30'">OK</button>
                    <button type="button" @click="set(item,'mangel')"
                            class="h-11 min-w-[3.75rem] rounded-lg text-sm font-medium ring-1 transition"
                            :class="state[item]==='mangel' ? 'bg-akzent text-white ring-akzent' : 'bg-white text-akzent-700 ring-akzent/30'">Mangel</button>
                </div>
            </div>
        </template>
    </div>

    <!-- Fotos: direkt aufnehmen (Kamera), mehrfach -->
    <div class="card p-4 mt-4">
        <div class="text-sm font-ui font-semibold text-navy mb-3">Fotos zur Doku</div>
        <input type="file" accept="image/*" capture="environment" class="hidden" x-ref="cam" @change="addPhoto($event)">
        <input type="file" name="fotos[]" multiple class="hidden" x-ref="store" form="abnahmeForm">
        <button type="button" @click="$refs.cam.click()" class="btn-ghost w-full h-14 text-base">
            <?= icon('clipboard','h-5 w-5') ?> Foto aufnehmen
        </button>
        <div class="mt-3 grid grid-cols-3 gap-2" x-show="previews.length" x-cloak>
            <template x-for="(p,i) in previews" :key="i">
                <div class="relative">
                    <img :src="p" class="h-24 w-full object-cover rounded-lg ring-1 ring-black/10" alt="Foto">
                    <button type="button" @click="removePhoto(i)" class="absolute -top-2 -right-2 h-6 w-6 rounded-full bg-navy text-white text-xs">✕</button>
                </div>
            </template>
        </div>
    </div>

    <!-- Notiz + (bei Nacharbeit) Frist -->
    <form method="post" action="/hafenmeister/abnahmen.php" enctype="multipart/form-data" id="abnahmeForm" class="mt-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="inspect">
        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <input type="hidden" name="notes" :value="composed">
        <input type="hidden" name="result" x-ref="result">
        <div class="card p-4">
            <label class="label" for="note">Notiz (optional)</label>
            <textarea class="field" id="note" name="freinote" rows="2" x-model="note" placeholder="Freitext zur Abnahme…"></textarea>
            <div class="mt-3" x-show="anyMangel" x-cloak>
                <label class="label" for="due">Frist für Nacharbeit</label>
                <input class="field" type="date" id="due" name="rework_due">
            </div>
        </div>
    </form>

    <!-- Sticky Aktions-Bar -->
    <div class="h-24"></div>
    <div class="fixed inset-x-0 bottom-0 z-40 bg-white/95 backdrop-blur border-t border-black/[0.08] p-3">
        <div class="container-page max-w-xl flex gap-3">
            <button type="button" @click="submit('rework')" form="abnahmeForm"
                    class="btn-akzent flex-1 h-14 text-base" :class="anyMangel ? '' : 'opacity-70'">Nacharbeit nötig</button>
            <button type="button" @click="submit('passed')"
                    class="btn-primary flex-1 h-14 text-base">Alles ok · abnehmen</button>
        </div>
    </div>
</div>

<script>
function abnahme(items) {
    return {
        items: items, state: {}, note: '', previews: [],
        set(item, val) { this.state[item] = (this.state[item] === val ? '' : val); },
        get anyMangel() { return this.items.some(n => this.state[n] === 'mangel'); },
        get composed() {
            const ok = this.items.filter(n => this.state[n] === 'ok');
            const mangel = this.items.filter(n => this.state[n] === 'mangel');
            let s = '';
            if (ok.length) s += 'OK: ' + ok.join(', ');
            if (mangel.length) s += (s ? ' · ' : '') + 'Mangel: ' + mangel.join(', ');
            if (this.note.trim()) s += (s ? ' — ' : '') + this.note.trim();
            return s;
        },
        addPhoto(e) {
            const dt = new DataTransfer();
            [...this.$refs.store.files].forEach(f => dt.items.add(f));
            [...e.target.files].forEach(f => dt.items.add(f));
            this.$refs.store.files = dt.files;
            this.refresh();
            e.target.value = '';
        },
        removePhoto(idx) {
            const dt = new DataTransfer();
            [...this.$refs.store.files].forEach((f, i) => { if (i !== idx) dt.items.add(f); });
            this.$refs.store.files = dt.files;
            this.refresh();
        },
        refresh() { this.previews = [...this.$refs.store.files].map(f => URL.createObjectURL(f)); },
        submit(result) {
            if (result === 'passed' && this.anyMangel && !confirm('Es sind Mängel markiert. Trotzdem als „ok" abnehmen?')) return;
            this.$refs.result.value = result;
            document.getElementById('abnahmeForm').submit();
        },
    };
}
</script>
<?php
page_end();
