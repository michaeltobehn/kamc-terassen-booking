<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';

$user = current_user(); // auch ohne Login lesbar (per Link aus Buchung/Mail)
$cfg  = require __DIR__ . '/../config.php';
$ver  = (string) ($cfg['app']['hausordnung_version'] ?? '2026-1');

page_start('Hausordnung', $user, 'hausordnung');
if (!$user) {
    echo '<div class="container-page pt-8"></div>';
}
page_header('Hausordnung · Lounge oben', 'Version ' . $ver . ' · verbindlich für jede Buchung');
?>
<div class="container-page max-w-3xl">
    <div class="card p-6 sm:p-8 space-y-6 leading-relaxed text-navy-950">
        <section>
            <h2 class="text-lg font-semibold">1. Nutzung</h2>
            <p class="mt-2 text-sm text-schiefer">Die „Lounge oben" (Obergeschoss: Terrasse + Innenbereich) steht Vereinsmitgliedern
               für private Anlässe zur Verfügung. Externe Gäste nur in Begleitung eines Mitglieds — das Mitglied haftet.</p>
        </section>
        <section class="rounded-lg bg-akzent/5 ring-1 ring-akzent/20 p-4">
            <h2 class="text-lg font-semibold text-akzent-700">2. Rote Linie: keine Musik</h2>
            <p class="mt-2 text-sm">Keine Musik und keine Lautsprecher — im Innen- wie Außenbereich. Rücksicht auf Nachbarschaft und Hafen.</p>
        </section>
        <section>
            <h2 class="text-lg font-semibold">3. Slots & Personen</h2>
            <ul class="mt-2 text-sm text-schiefer list-disc list-inside space-y-1">
                <li>Zwei feste Slots pro Tag: <strong>Tag</strong> (Öffnung bis 18:00) und <strong>Abend</strong> (18:00 bis Schließung).</li>
                <li>Maximal <strong>16 Personen</strong>.</li>
                <li>Buchung mindestens <strong>24 Stunden</strong> im Voraus.</li>
            </ul>
        </section>
        <section>
            <h2 class="text-lg font-semibold">4. Verpflegung & Grill</h2>
            <p class="mt-2 text-sm text-schiefer">Selbstversorgung (Speisen und Getränke mitbringen). Der Gasgrill „Burnhard" wird
               gestellt (inkl. Ersatz-Gasflasche). Nach Nutzung: Grill reinigen, auf Brandflecken prüfen, Terrasse kehren, Gas zudrehen.
               Der Kühlschrank darf genutzt werden — bitte danach leeren.</p>
        </section>
        <section>
            <h2 class="text-lg font-semibold">5. Schlüssel, Begehung & Abnahme</h2>
            <p class="mt-2 text-sm text-schiefer">Schlüsselübergabe und -rückgabe erfolgen ausschließlich über die Hafenmeisterei (RSK),
               jeweils mit Begehung. Nach dem Termin nimmt der Hafenmeister ab (Grill sauber? Brandflecken? gekehrt? Möbel zurück?).</p>
        </section>
        <section>
            <h2 class="text-lg font-semibold">6. Kosten</h2>
            <p class="mt-2 text-sm text-schiefer">Die Nutzung ist im Pilotbetrieb kostenlos. Kein Gebühren- oder Pfandmodell, keine Online-Zahlung.</p>
        </section>
        <p class="text-xs text-schiefer border-t border-nebel pt-4">
            KAMC e.V. · Vorstandsbeschluss 22.05.2026 · Pilotbetrieb bis 31.10.2026. Diese Fassung wird revisionssicher zu jeder Buchung gespeichert.
        </p>
    </div>
</div>
<?php
page_end();
