<?php
declare(strict_types=1);
require __DIR__ . '/../src/layout.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        header('Location: /dashboard.php');
        exit;
    }
    $error = 'E-Mail oder Passwort stimmt nicht.';
}

if (current_user()) {
    header('Location: /index.php');
    exit;
}

page_start('Anmelden');
?>
<div class="min-h-screen flex items-center justify-center px-4 py-12 bg-black/[0.015]">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <img src="/assets/img/kamc-logo.png" alt="KAMC" class="mx-auto h-20 w-20 rounded-full ring-1 ring-black/10">
            <h1 class="mt-5 font-display text-3xl text-navy">Lounge oben</h1>
            <p class="mt-1 text-schiefer text-sm">Ahoi! Buchung der Clubterrasse für KAMC-Mitglieder.</p>
        </div>
        <div class="card p-6 sm:p-8">
            <?php if ($error): ?>
                <div class="mb-4 rounded-lg bg-akzent/10 text-akzent-700 ring-1 ring-akzent/20 px-4 py-3 text-sm"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="label" for="email">E-Mail</label>
                    <input class="field" type="email" id="email" name="email" required autofocus autocomplete="username" placeholder="name@example.com">
                </div>
                <div>
                    <label class="label" for="password">Passwort</label>
                    <input class="field" type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </div>
                <button class="btn-primary w-full" type="submit">Anmelden</button>
            </form>
            <p class="mt-5 text-xs text-schiefer text-center">
                Kein Self-Signup — Zugänge werden vom Vorstand provisioniert.<br>
                Passwort vergessen? Bitte an die Hafenmeisterei wenden.
            </p>
        </div>
        <div class="mt-4 rounded-lg bg-black/[0.03] ring-1 ring-black/[0.06] text-schiefer text-xs px-4 py-3 leading-relaxed">
            <strong class="text-navy">Dev-Zugänge</strong> (Passwort <code class="text-navy">kamc</code>):
            admin@kamc.dev · hafen@kamc.dev · member@kamc.dev
        </div>
    </div>
</div>
<?php
page_end();
