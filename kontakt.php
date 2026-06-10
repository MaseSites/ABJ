<?php
require_once __DIR__ . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $message = trim($_POST['message'] ?? '');
    if (mb_strlen($name) < 2 || !str_contains($email, '@') || mb_strlen($message) < 5) {
        redirect('/kontakt?error=1');
    }
    $stmt = db()->prepare("INSERT INTO messages (name,email,message) VALUES (?,?,?)");
    $stmt->execute([$name, $email, $message]);
    redirect('/kontakt?sent=1');
}

$sent         = ($_GET['sent'] ?? '') === '1';
$contactEmail = setting_get('contact_email') ?: '';
$cartCount    = cart_count();
$currentPath  = '/kontakt';
$pageTitle    = 'Kontakt';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section narrow">
  <h1 class="section-title">Kontakt</h1>
  <?php if ($contactEmail): ?>
    <p class="muted" style="margin-bottom:1.4rem">Oder schreib uns direkt: <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a></p>
  <?php endif; ?>

  <?php if ($sent): ?>
    <div class="alert alert-ok">Danke! Wir melden uns bald.</div>
  <?php else: ?>
    <?php if ($_GET['error'] ?? ''): ?>
      <div class="alert alert-error" style="margin-bottom:1rem">Bitte fülle alle Felder korrekt aus.</div>
    <?php endif; ?>
    <form method="post" action="/kontakt" class="form-stack">
      <label class="field"><span>Name</span><input type="text" name="name" required minlength="2" maxlength="120"></label>
      <label class="field"><span>E-Mail</span><input type="email" name="email" required maxlength="200"></label>
      <label class="field"><span>Nachricht</span><textarea name="message" required minlength="5" maxlength="2000" rows="5"></textarea></label>
      <button class="btn btn-primary" type="submit">Senden</button>
    </form>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
