<?php
require_once __DIR__ . '/lib/bootstrap.php';

$reference    = trim($_GET['ref'] ?? '');
$contactEmail = setting_get('contact_email') ?: '';
$cartCount    = cart_count();
$currentPath  = '/bestellung';
$pageTitle    = 'Bestellung eingegangen';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section narrow center">
  <div class="confirmation-icon">✓</div>
  <h1>Bestellung eingegangen!</h1>
  <p class="muted">Deine Bestellnummer: <strong><?= h($reference) ?></strong></p>
  <p class="muted">
    Wir haben deine Bestellung erhalten und melden uns bald.
    <?php if ($contactEmail): ?>
      Fragen? <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a>
    <?php endif; ?>
  </p>
  <a class="btn btn-primary" href="/shop">Weiter einkaufen</a>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
