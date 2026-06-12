<?php
require_once __DIR__ . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (mb_strlen($name) < 2 || !str_has($email, '@') || mb_strlen($message) < 5) {
        redirect('/kontakt.php?error=1');
    }
    $stmt = db()->prepare("INSERT INTO messages (name,email,subject,message) VALUES (?,?,?,?)");
    $stmt->execute([mb_substr($name, 0, 120), mb_substr($email, 0, 200), mb_substr($subject, 0, 200), mb_substr($message, 0, 2000)]);
    redirect('/kontakt.php?sent=1');
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
  <span class="section-title-label">Wir helfen gern</span>
  <h1 class="section-title">Kontakt</h1>
  <p class="muted" style="margin-bottom:1.6rem">
    Fragen zu einer Bestellung, einem Produkt oder etwas anderem? Schreib uns — wir melden uns
    in der Regel innerhalb von 24 Stunden.
    <?php if ($contactEmail): ?>
      <br>Direkt per E-Mail: <a href="mailto:<?= h($contactEmail) ?>" style="color:var(--accent-3)"><?= h($contactEmail) ?></a>
    <?php endif; ?>
  </p>

  <?php if ($sent): ?>
    <div class="alert alert-ok">Danke für deine Nachricht! Wir melden uns so schnell wie möglich.</div>
    <a class="btn btn-ghost" href="<?= url('/shop.php') ?>">Zurück zum Shop</a>
  <?php else: ?>
    <?php if ($_GET['error'] ?? ''): ?>
      <div class="alert alert-error" style="margin-bottom:1rem">Bitte fülle alle Felder korrekt aus.</div>
    <?php endif; ?>
    <form method="post" action="<?= url('/kontakt.php') ?>" class="checkout-section" style="gap:1rem">
      <div class="form-row-2">
        <label class="field"><span>Name *</span><input type="text" name="name" required minlength="2" maxlength="120" placeholder="Dein Name"></label>
        <label class="field"><span>E-Mail *</span><input type="email" name="email" required maxlength="200" placeholder="name@beispiel.ch"></label>
      </div>
      <label class="field"><span>Betreff</span><input type="text" name="subject" maxlength="200" placeholder="Worum geht es?"></label>
      <label class="field"><span>Nachricht *</span><textarea name="message" required minlength="5" maxlength="2000" rows="6" placeholder="Deine Nachricht…"></textarea></label>
      <button class="btn btn-primary" type="submit" style="align-self:flex-start">Nachricht senden</button>
    </form>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
