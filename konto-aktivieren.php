<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_customer();

$cust    = current_customer();
$account = account_by_id((int)$cust['id']);

// Bereits freigeschaltet? Dann zurück ins Konto.
if (account_is_activated($account)) redirect('/konto.php');

$weiter = $_GET['weiter'] ?? ($_POST['weiter'] ?? '/konto.php');
if (!is_string($weiter) || $weiter === '' || $weiter[0] !== '/') $weiter = '/konto.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $res  = account_activate_with_code((int)$cust['id'], $code);
    if ($res['ok']) {
        redirect($weiter . (str_contains($weiter, '?') ? '&' : '?') . 'aktiviert=1');
    }
    $error = $res['error'] ?? 'Aktivierung fehlgeschlagen.';
}

$cartCount   = cart_count();
$currentPath = '/konto-aktivieren';
$pageTitle   = 'Konto aktivieren';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main id="main" class="container section narrow">
  <div class="auth-card">
    <span class="section-title-label">Freischaltung</span>
    <h1 class="auth-title">Konto aktivieren</h1>
    <p class="muted" style="margin:0 0 1.4rem">Dein Konto ist aktuell <strong>eingeschränkt</strong>. Gib deinen Aktivierungscode ein, um alle Funktionen freizuschalten. Bereits abgegebene Bestellungen werden danach automatisch bearbeitet.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

    <form method="post" action="<?= url('/konto-aktivieren.php') ?>" class="auth-form">
      <input type="hidden" name="weiter" value="<?= h($weiter) ?>">
      <label class="field"><span>Aktivierungscode</span>
        <input type="text" name="code" required maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="z. B. AB12CD" style="letter-spacing:.14em;text-transform:uppercase" autofocus>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Jetzt aktivieren</button>
    </form>

    <p class="auth-alt"><a href="<?= url('/konto.php') ?>">Später &ndash; zurück zum Konto</a></p>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
