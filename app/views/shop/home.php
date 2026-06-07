<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="hero">
  <h1><?= htmlspecialchars($shopName ?? 'ABJ Shop') ?></h1>
  <p>Ausgewählte Produkte, klar präsentiert.</p>
</section>
<section class="grid">
  <?php foreach (($products ?? []) as $product): ?>
    <article class="card">
      <h2><?= htmlspecialchars($product['name']) ?></h2>
      <p><?= htmlspecialchars($product['category']) ?></p>
      <p><?= number_format(((int)$product['price_cents']) / 100, 2, '.', ' ') ?> <?= htmlspecialchars($currency) ?></p>
      <a class="button" href="?route=product&slug=<?= urlencode($product['slug']) ?>">Details</a>
    </article>
  <?php endforeach; ?>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
