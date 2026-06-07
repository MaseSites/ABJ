<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="panel">
  <h1>Produkte</h1>
  <a class="button" href="?route=admin/product/add">Produkt hinzufügen</a>
</section>
<section class="grid">
  <?php foreach (($products ?? []) as $product): ?>
    <article class="card">
      <h2><?= htmlspecialchars($product['name']) ?></h2>
      <a class="button" href="?route=admin/product/edit&id=<?= (int)$product['id'] ?>">Bearbeiten</a>
    </article>
  <?php endforeach; ?>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
