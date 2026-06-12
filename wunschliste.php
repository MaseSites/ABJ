<?php
require_once __DIR__ . '/lib/bootstrap.php';
$cartCount   = cart_count();
$currentPath = '/wunschliste';
$pageTitle   = 'Wunschliste';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main id="main" class="container section" data-wish-page>
  <span class="section-title-label">Gemerkte Produkte</span>
  <h1 class="section-title">Wunschliste</h1>
  <div class="product-grid" data-wish-grid></div>
  <div class="cart-empty-state" data-wish-empty hidden>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56">
      <path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/>
    </svg>
    <p>Deine Wunschliste ist leer.</p>
    <a class="btn btn-primary" href="<?= url('/shop.php') ?>">Zum Shop</a>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
