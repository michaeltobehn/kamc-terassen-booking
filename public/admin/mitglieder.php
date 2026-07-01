<?php
declare(strict_types=1);
require __DIR__ . '/../../src/layout.php';
require __DIR__ . '/../../src/actions.php';

$user = require_role('admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = upsert_member($pdo, $_POST);
    flash_set($res === true ? 'success' : 'error', $res === true ? 'Mitglied gespeichert.' : implode(' ', (array) $res));
    header('Location: /admin/mitglieder.php');
    exit;
}

$members = members_all($pdo);
$edit    = isset($_GET['edit']) ? member_by_id($pdo, (int) $_GET['edit']) : null;

page_start('Mitglieder', $user, 'admin-members');
page_header('Mitgliederverwaltung', 'Rollen, Status und Provisionierung. Kein öffentliches Self-Signup.');
?>
<div class="container-page grid gap-6 lg:grid-cols-3">
    <!-- Formular -->
    <form method="post" class="card p-6 space-y-4 lg:col-span-1 self-start">
        <?= csrf_field() ?>
        <h2 class="text-lg font-semibold"><?= $edit ? 'Mitglied bearbeiten' : 'Neues Mitglied' ?></h2>
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div><label class="label" for="name">Name</label>
            <input class="field" type="text" id="name" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div><label class="label" for="email">E-Mail</label>
            <input class="field" type="email" id="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label" for="role">Rolle</label>
                <select class="field" id="role" name="role">
                    <?php foreach (['member' => 'Mitglied', 'hafenmeister' => 'Hafenmeister', 'admin' => 'Vorstand'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($edit['role'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="label" for="status">Status</label>
                <select class="field" id="status" name="status">
                    <option value="active"  <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>aktiv</option>
                    <option value="pending" <?= ($edit['status'] ?? '') === 'pending' ? 'selected' : '' ?>>pending</option>
                </select></div>
        </div>
        <div><label class="label" for="password"><?= $edit ? 'Neues Passwort (optional)' : 'Startpasswort' ?></label>
            <input class="field" type="text" id="password" name="password" <?= $edit ? '' : 'required' ?> placeholder="min. 4 Zeichen"></div>
        <div class="flex gap-2">
            <button class="btn-primary" type="submit"><?= $edit ? 'Speichern' : 'Anlegen' ?></button>
            <?php if ($edit): ?><a class="btn-ghost" href="/admin/mitglieder.php">Abbrechen</a><?php endif; ?>
        </div>
    </form>

    <!-- Liste -->
    <div class="card overflow-x-auto lg:col-span-2 self-start">
        <table class="w-full text-sm">
            <thead class="bg-nebel/60 text-left text-schiefer">
                <tr>
                    <th class="px-4 py-3 font-ui font-semibold">Name</th>
                    <th class="px-4 py-3 font-ui font-semibold">E-Mail</th>
                    <th class="px-4 py-3 font-ui font-semibold">Rolle</th>
                    <th class="px-4 py-3 font-ui font-semibold">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-nebel">
                <?php foreach ($members as $m): ?>
                    <tr class="hover:bg-sand/60">
                        <td class="px-4 py-3 font-medium"><?= e($m['name']) ?></td>
                        <td class="px-4 py-3 text-schiefer"><?= e($m['email']) ?></td>
                        <td class="px-4 py-3"><?= e(role_label($m['role'])) ?></td>
                        <td class="px-4 py-3">
                            <span class="badge <?= $m['status'] === 'active' ? 'badge-confirmed' : 'badge-pending' ?>"><?= e($m['status']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-right"><a class="text-akzent hover:underline" href="?edit=<?= (int) $m['id'] ?>">Bearbeiten</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
page_end();
