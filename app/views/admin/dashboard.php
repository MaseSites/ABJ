<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="grid">
  <article class="card"><h2>Produkte</h2><p><?= (int)($stats['products'] ?? 0) ?></p></article>
  <article class="card"><h2>Bestellungen</h2><p><?= (int)($stats['orders'] ?? 0) ?></p></article>
  <article class="card"><h2>Abonnenten</h2><p><?= (int)($stats['subscribers'] ?? 0) ?></p></article>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
