<?php
require_once __DIR__ . '/lib/bootstrap.php';
http_response_code(404);
$cartCount   = cart_count();
$currentPath = '';
$pageTitle   = 'Seite nicht gefunden';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main id="main" class="container section narrow center">
  <div class="error-status">404</div>
  <h1 style="margin-top:.4rem">Seite nicht gefunden</h1>
  <p class="muted" style="margin-bottom:1.6rem">Diese Seite existiert nicht (mehr) oder wurde verschoben.</p>
  <div style="display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= url('/') ?>">Zur Startseite</a>
    <a class="btn btn-ghost" href="<?= url('/shop.php') ?>">Zum Shop</a>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
