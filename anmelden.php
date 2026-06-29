<?php
require_once __DIR__ . '/lib/bootstrap.php';

if (is_customer()) redirect('/konto.php');

$error  = '';
$weiter = $_GET['weiter'] ?? ($_POST['weiter'] ?? '/konto.php');
$prefillEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!login_throttle_allowed('customer', 12, 15)) {
        $error = 'Zu viele Login-Versuche. Bitte warte etwa 15 Minuten und versuche es erneut.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $prefillEmail = $email;
        $acc = account_verify_login($email, $password);
        if ($acc) {
            login_throttle_clear('customer');
            customer_login((int)$acc['id'], $acc['email'], $acc['name']);
            redirect($weiter && $weiter[0] === '/' ? $weiter : '/konto.php');
        }
        login_throttle_hit('customer');
        $error = 'E-Mail oder Passwort ist falsch.';
    }
}

$cartCount   = cart_count();
$currentPath = '/anmelden';
$pageTitle   = 'Anmelden';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main id="main" class="container section narrow">
  <div class="auth-card">
    <span class="section-title-label">Willkommen zurück</span>
    <h1 class="auth-title">Anmelden</h1>
    <p class="muted" style="margin:0 0 1.4rem">Melde dich an, um deine Bestellungen zu sehen.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

    <form method="post" action="<?= url('/anmelden.php') ?>" class="auth-form">
      <input type="hidden" name="weiter" value="<?= h($weiter) ?>">
      <label class="field"><span>E-Mail *</span>
        <input type="email" name="email" value="<?= h($prefillEmail) ?>" required maxlength="200" autocomplete="email" autofocus placeholder="name@beispiel.ch">
      </label>
      <label class="field"><span>Passwort *</span>
        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
      </label>
      <button class="btn btn-primary btn-block" type="submit">Anmelden</button>
    </form>

    <p class="auth-alt">Noch kein Konto? <a href="<?= url('/registrieren.php' . ($weiter !== '/konto.php' ? '?weiter=' . urlencode($weiter) : '')) ?>">Konto erstellen</a></p>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
