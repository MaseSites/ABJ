<?php
require_once __DIR__ . '/lib/bootstrap.php';

if (is_customer()) redirect('/konto.php');

$error = '';
$weiter = $_GET['weiter'] ?? ($_POST['weiter'] ?? '/konto.php');
$prefill = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $prefill  = ['name' => $name, 'email' => $email];

    $promo = trim($_POST['promo'] ?? '');
    $res = account_create($email, $password, $name);
    if ($res['ok']) {
        // Promo-/Empfehlungscode verknüpfen (optional, einmalig verwendbar)
        if ($promo !== '' && code_is_usable(code_find($promo))) {
            code_mark_used($promo, (int)$res['id']);
            $owner = promo_owner_of_code($promo);
            if ($owner) account_set_referrer((int)$res['id'], $owner);
        }
        customer_login($res['id'], $email, $name);
        redirect($weiter && $weiter[0] === '/' ? $weiter : '/konto.php');
    }
    $error = $res['error'];
}

$cartCount   = cart_count();
$currentPath = '/registrieren';
$pageTitle   = 'Konto erstellen';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main id="main" class="container section narrow">
  <div class="auth-card">
    <span class="section-title-label">Willkommen</span>
    <h1 class="auth-title">Konto erstellen</h1>
    <p class="muted" style="margin:0 0 1.4rem">Erstelle ein Konto, um deine Bestellungen jederzeit einzusehen und schneller zu bestellen.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

    <form method="post" action="<?= url('/registrieren.php') ?>" class="auth-form">
      <input type="hidden" name="weiter" value="<?= h($weiter) ?>">
      <label class="field"><span>Name</span>
        <input type="text" name="name" value="<?= h($prefill['name']) ?>" maxlength="120" autocomplete="name" placeholder="Vor- und Nachname">
      </label>
      <label class="field"><span>E-Mail *</span>
        <input type="email" name="email" value="<?= h($prefill['email']) ?>" required maxlength="200" autocomplete="email" placeholder="name@beispiel.ch">
      </label>
      <label class="field"><span>Passwort * <small class="muted">(min. 8 Zeichen)</small></span>
        <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="••••••••">
      </label>
      <label class="field"><span>Promo-Code <small class="muted">(optional)</small></span>
        <input type="text" name="promo" value="<?= h($_GET['promo'] ?? '') ?>" maxlength="20" autocomplete="off" placeholder="Code eines Freundes" style="letter-spacing:.06em">
      </label>
      <button class="btn btn-primary btn-block" type="submit">Konto erstellen</button>
    </form>

    <p class="auth-alt">Schon ein Konto? <a href="<?= url('/anmelden.php' . ($weiter !== '/konto.php' ? '?weiter=' . urlencode($weiter) : '')) ?>">Jetzt anmelden</a></p>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
