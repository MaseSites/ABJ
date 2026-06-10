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
  <h1>404</h1>
  <p class="muted">Diese Seite wurde nicht gefunden.</p>
  <a class="btn btn-primary" href="/">Zur Startseite</a>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
