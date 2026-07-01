<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/repo.php';

/**
 * Öffentliche Startseite (Landing) — zeigt das Angebot OHNE Login.
 * Eingeloggte Nutzer bekommen im Header den Weg zur Übersicht.
 */
$user     = current_user();
$amenities = amenities_all(db(), true);

page_start('Willkommen', $user, '', 'public');
?>
<!-- ============ HERO (minimal, hell) ============ -->
<section class="relative border-b border-black/[0.06]">
    <div class="container-page pt-32 pb-20 sm:pt-40 sm:pb-28">
        <div class="max-w-2xl">
            <p class="eyebrow">Rheinauhafen Köln · KAMC e.V.</p>
            <h1 class="mt-5 font-display font-semibold text-4xl sm:text-6xl leading-[1.05] text-navy">
                Feiern über dem <span class="text-akzent">Rheinauhafen</span>.
            </h1>
            <p class="mt-6 lead max-w-xl">
                Die „Lounge oben" — Terrasse und Innenbereich des Clubs — für deinen privaten Anlass.
                Zwei feste Slots pro Tag, bis zu 16 Personen, <strong class="text-navy font-semibold">kostenlos für Mitglieder</strong>.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/lounge.php" class="btn-akzent text-base px-6 py-3"><?= icon('plus','h-5 w-5') ?> Jetzt buchen</a>
                <a href="#angebot" class="btn-ghost text-base px-6 py-3">Angebot ansehen</a>
            </div>
            <div class="mt-10 flex flex-wrap gap-x-8 gap-y-2 text-sm text-schiefer">
                <span class="flex items-center gap-2"><?= icon('anchor','h-4 w-4 text-navy/40') ?> Nur für Mitglieder</span>
                <span class="flex items-center gap-2"><?= icon('sun','h-4 w-4 text-navy/40') ?> Tag &amp; Abend</span>
                <span class="flex items-center gap-2"><?= icon('users','h-4 w-4 text-navy/40') ?> bis 16 Personen</span>
            </div>
        </div>
    </div>
</section>

<!-- ============ ANGEBOT + LIVE-VERFÜGBARKEIT ============ -->
<section id="angebot" class="section">
    <div class="container-page">
        <div class="max-w-2xl">
            <p class="eyebrow !text-navy/50">Das Angebot</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-display font-semibold text-navy">Zwei Slots. Ein Ort mit Aussicht.</h2>
            <p class="mt-3 lead">Keine komplizierte Zeitwahl — du buchst einen festen Slot. Verfügbarkeit wird live berechnet.</p>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-3">
            <div class="feature-card">
                <span class="ico-akzent"><?= icon('sun') ?></span>
                <h3 class="mt-4 text-xl font-semibold">Tag-Slot</h3>
                <p class="mt-1 text-schiefer">Von Öffnung bis <strong>18:00 Uhr</strong>. Ideal für Brunch, Kaffee &amp; Kuchen oder Nachmittag am Wasser.</p>
            </div>
            <div class="feature-card">
                <span class="ico"><?= icon('moon') ?></span>
                <h3 class="mt-4 text-xl font-semibold">Abend-Slot</h3>
                <p class="mt-1 text-schiefer">Ab <strong>18:00 Uhr</strong> bis Schließung (bis 02:00). Für Geburtstage und laue Sommerabende.</p>
            </div>
            <!-- Live-Widget (minimal, hell) -->
            <div class="feature-card" x-data="freeDays()" x-init="load()">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-navy">Nächste freie Tage</h3>
                    <span class="badge badge-frei">live</span>
                </div>
                <ul class="mt-4 space-y-1 text-sm" x-show="days.length">
                    <template x-for="d in days" :key="d.date">
                        <li class="flex items-center justify-between border-b border-black/[0.06] py-2">
                            <span x-text="d.label" class="text-navy-950"></span>
                            <span class="flex gap-1">
                                <template x-if="d.tag"><span class="badge badge-frei">Tag</span></template>
                                <template x-if="d.abend"><span class="badge badge-frei">Abend</span></template>
                            </span>
                        </li>
                    </template>
                </ul>
                <p x-show="!days.length && !loading" x-cloak class="mt-4 text-sm text-schiefer">Aktuell keine freien Tage im Fenster.</p>
                <a href="/lounge.php" class="btn-akzent btn-sm mt-5">Termin sichern</a>
            </div>
        </div>
    </div>
</section>

<!-- ============ ABLAUF ============ -->
<section id="ablauf" class="section bg-white">
    <div class="container-page">
        <p class="eyebrow !text-navy/50">So funktioniert's</p>
        <h2 class="mt-2 text-3xl sm:text-4xl font-display font-semibold text-navy">In drei Schritten gebucht</h2>
        <div class="mt-10 grid gap-8 md:grid-cols-3">
            <div class="flex gap-4">
                <span class="step-num">1</span>
                <div><h3 class="font-semibold text-lg">Slot wählen</h3>
                    <p class="mt-1 text-schiefer">Im Kalender einen freien Tag- oder Abend-Slot aussuchen (mind. 24 h im Voraus).</p></div>
            </div>
            <div class="flex gap-4">
                <span class="step-num">2</span>
                <div><h3 class="font-semibold text-lg">Anfragen</h3>
                    <p class="mt-1 text-schiefer">Personenzahl und Anlass angeben, Hausordnung bestätigen — fertig ist die Anfrage.</p></div>
            </div>
            <div class="flex gap-4">
                <span class="step-num">3</span>
                <div><h3 class="font-semibold text-lg">Bestätigung &amp; Schlüssel</h3>
                    <p class="mt-1 text-schiefer">Die Hafenmeisterei bestätigt und übergibt den Schlüssel mit Begehung.</p></div>
            </div>
        </div>
    </div>
</section>

<!-- ============ AUSSTATTUNG ============ -->
<section id="ausstattung" class="section">
    <div class="container-page">
        <div class="flex items-end justify-between flex-wrap gap-4">
            <div class="max-w-xl">
                <p class="eyebrow !text-navy/50">Ausstattung</p>
                <h2 class="mt-2 text-3xl sm:text-4xl font-display font-semibold text-navy">Alles da — einfach mitbringen, was schmeckt.</h2>
            </div>
        </div>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach (array_slice($amenities, 0, 6) as $a): ?>
                <div class="feature-card flex flex-col">
                    <div class="aspect-[16/10] -m-6 mb-4 rounded-t-xl bg-nebel flex items-center justify-center overflow-hidden">
                        <?php if ($a['image_path']): ?>
                            <img src="<?= e($a['image_path']) ?>" alt="<?= e($a['name']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <img src="/assets/img/kamc-logo.png" alt="" class="h-14 w-14 rounded-full opacity-30">
                        <?php endif; ?>
                    </div>
                    <h3 class="font-semibold text-lg"><?= e($a['name']) ?></h3>
                    <?php if ($a['description']): ?><p class="mt-1 text-sm text-schiefer flex-1"><?= e($a['description']) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ REGELN (minimal) ============ -->
<section class="section border-y border-black/[0.06] bg-black/[0.015]">
    <div class="container-page grid gap-10 md:grid-cols-4 text-center">
        <div><div class="mx-auto ico"><?= icon('shield') ?></div><h3 class="mt-4 font-semibold text-navy">Kostenlos</h3><p class="mt-1 text-sm text-schiefer">Kein Entgelt, kein Pfand im Pilot.</p></div>
        <div><div class="mx-auto ico"><?= icon('moon') ?></div><h3 class="mt-4 font-semibold text-navy">Keine Musik</h3><p class="mt-1 text-sm text-schiefer">Rücksicht auf Hafen &amp; Nachbarn.</p></div>
        <div><div class="mx-auto ico"><?= icon('anchor') ?></div><h3 class="mt-4 font-semibold text-navy">Für Mitglieder</h3><p class="mt-1 text-sm text-schiefer">Gäste nur in Begleitung.</p></div>
        <div><div class="mx-auto ico"><?= icon('sparkle') ?></div><h3 class="mt-4 font-semibold text-navy">Selbstversorgung</h3><p class="mt-1 text-sm text-schiefer">Grill gestellt, Rest bringst du mit.</p></div>
    </div>
</section>

<!-- ============ FINAL CTA ============ -->
<section class="section">
    <div class="container-page">
        <div class="card p-10 sm:p-14 text-center">
            <h2 class="text-3xl sm:text-4xl font-display font-semibold text-navy">Bereit für deinen Termin an Deck?</h2>
            <p class="mt-3 lead max-w-xl mx-auto">Melde dich an und sichere dir deinen Slot in der Lounge oben.</p>
            <div class="mt-7 flex flex-wrap gap-3 justify-center">
                <a href="/lounge.php" class="btn-akzent text-base px-6 py-3"><?= icon('plus','h-5 w-5') ?> Jetzt buchen</a>
                <?php if (!$user): ?><a href="/login.php" class="btn-ghost text-base px-6 py-3">Anmelden</a><?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
function freeDays() {
    return {
        days: [], loading: false,
        pad(n){ return String(n).padStart(2,'0'); },
        async load(){
            this.loading = true;
            const t = new Date();
            const from = t.getFullYear()+'-'+this.pad(t.getMonth()+1)+'-'+this.pad(t.getDate());
            const e = new Date(t.getTime()+45*864e5);
            const to = e.getFullYear()+'-'+this.pad(e.getMonth()+1)+'-'+this.pad(e.getDate());
            try {
                const res = await fetch('/availability.php?from='+from+'&to='+to);
                const data = await res.json();
                const names=['So','Mo','Di','Mi','Do','Fr','Sa'];
                this.days = data.filter(d => d.tag==='frei' || d.abend==='frei').slice(0,5).map(d=>{
                    const dt = new Date(d.date+'T00:00:00');
                    return { date:d.date, label: names[dt.getDay()]+'., '+d.date.split('-').reverse().join('.'),
                             tag: d.tag==='frei', abend: d.abend==='frei' };
                });
            } catch(e) {}
            this.loading = false;
        }
    };
}
</script>
<?php
page_end();
