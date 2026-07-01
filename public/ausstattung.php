<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/repo.php';

$user = require_login();
$items = amenities_all(db(), true);

page_start('Ausstattung', $user, 'ausstattung');
page_header('Ausstattung „Lounge oben"', 'Das buchst du — inklusive der wichtigen „Bitte beachten"-Hinweise.');
?>
<div class="container-page grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($items as $a): ?>
        <div class="card overflow-hidden flex flex-col">
            <div class="aspect-[4/3] bg-gradient-to-br from-navy to-himmel/40 flex items-center justify-center">
                <?php if ($a['image_path']): ?>
                    <img src="<?= e($a['image_path']) ?>" alt="<?= e($a['name']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <img src="/assets/img/kamc-logo.png" alt="" class="h-20 w-20 rounded-full opacity-80 ring-2 ring-white/30">
                <?php endif; ?>
            </div>
            <div class="p-5 flex-1 flex flex-col">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-lg font-semibold"><?= e($a['name']) ?></h3>
                    <?php if ((int) $a['inspection_relevant'] === 1): ?>
                        <span class="badge badge-blackout shrink-0">Abnahme</span>
                    <?php endif; ?>
                </div>
                <?php if ($a['description']): ?>
                    <p class="mt-2 text-sm text-schiefer flex-1"><?= e($a['description']) ?></p>
                <?php endif; ?>
                <?php if ($a['notes']): ?>
                    <div class="mt-3 rounded-md bg-himmel-light/50 ring-1 ring-navy/10 px-3 py-2 text-xs text-navy-950">
                        <span class="font-semibold">Bitte beachten:</span> <?= e($a['notes']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php
page_end();
