<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="panel">
  <h1><?= htmlspecialchars($product['name']) ?></h1>
  <p><?= htmlspecialchars($product['description']) ?></p>
  <p><?= number_format(((int)$product['price_cents']) / 100, 2, '.', ' ') ?> <?= htmlspecialchars($currency) ?></p>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
