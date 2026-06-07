<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="panel">
  <h1>Shop</h1>
</section>
<section class="grid">
  <?php foreach (($products ?? []) as $product): ?>
    <article class="card">
      <h2><?= htmlspecialchars($product['name']) ?></h2>
      <p><?= htmlspecialchars($product['description']) ?></p>
      <p><?= number_format(((int)$product['price_cents']) / 100, 2, '.', ' ') ?> <?= htmlspecialchars($currency) ?></p>
      <a class="button" href="?route=product&slug=<?= urlencode($product['slug']) ?>">Ansehen</a>
    </article>
  <?php endforeach; ?>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
