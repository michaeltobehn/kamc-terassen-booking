<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/actions.php';

$user = require_role('admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        delete_amenity($pdo, (int) ($_POST['id'] ?? 0));
        flash_set('success', 'Ausstattung entfernt.');
    } else {
        $res = save_amenity($pdo, $_POST);
        flash_set($res === true ? 'success' : 'error', $res === true ? 'Ausstattung gespeichert.' : implode(' ', (array) $res));
    }
    header('Location: /admin/ausstattung.php');
    exit;
}

$items = amenities_all($pdo);
$edit  = isset($_GET['edit']) ? amenity_by_id($pdo, (int) $_GET['edit']) : null;

page_start('Ausstattung pflegen', $user, 'admin-amenities');
page_header('Ausstattung pflegen', 'Speist Galerie & Abnahme-Checkliste.');
?>
<div class="container-page grid gap-6 lg:grid-cols-3">
    <form method="post" class="card p-6 space-y-4 self-start">
        <?= csrf_field() ?>
        <h2 class="text-lg font-semibold"><?= $edit ? 'Bearbeiten' : 'Neue Ausstattung' ?></h2>
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div><label class="label" for="name">Name</label>
            <input class="field" type="text" id="name" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div><label class="label" for="description">Beschreibung</label>
            <textarea class="field" id="description" name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></div>
        <div><label class="label" for="notes">„Bitte beachten"</label>
            <textarea class="field" id="notes" name="notes" rows="2"><?= e($edit['notes'] ?? '') ?></textarea></div>
        <div><label class="label" for="image_path">Bild-Pfad</label>
            <input class="field" type="text" id="image_path" name="image_path" placeholder="/assets/img/…" value="<?= e($edit['image_path'] ?? '') ?>"></div>
        <div><label class="label" for="sort_order">Reihenfolge</label>
            <input class="field" type="number" id="sort_order" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></div>
        <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="inspection_relevant" value="1" <?= !empty($edit['inspection_relevant']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-navy/30 text-akzent"> Abnahme-relevant</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= (!$edit || !empty($edit['is_active'])) ? 'checked' : '' ?> class="h-4 w-4 rounded border-navy/30 text-akzent"> aktiv</label>
        </div>
        <div class="flex gap-2">
            <button class="btn-primary" type="submit"><?= $edit ? 'Speichern' : 'Anlegen' ?></button>
            <?php if ($edit): ?><a class="btn-ghost" href="/admin/ausstattung.php">Abbrechen</a><?php endif; ?>
        </div>
    </form>

    <div class="lg:col-span-2 space-y-3 self-start">
        <?php foreach ($items as $a): ?>
            <div class="card p-4 flex items-center gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold"><?= e($a['name']) ?></span>
                        <?php if ((int) $a['inspection_relevant'] === 1): ?><span class="badge badge-blackout">Abnahme</span><?php endif; ?>
                        <?php if ((int) $a['is_active'] !== 1): ?><span class="badge badge-cancelled">inaktiv</span><?php endif; ?>
                    </div>
                    <?php if ($a['description']): ?><p class="text-sm text-schiefer mt-0.5"><?= e($a['description']) ?></p><?php endif; ?>
                </div>
                <span class="text-xs text-schiefer">#<?= (int) $a['sort_order'] ?></span>
                <a class="btn-ghost btn-sm" href="?edit=<?= (int) $a['id'] ?>">Bearbeiten</a>
                <form method="post" onsubmit="return confirm('Wirklich löschen?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                    <button class="btn-ghost btn-sm text-akzent" type="submit">Löschen</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
page_end();
