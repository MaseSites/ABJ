<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="panel">
  <h1>Warenkorb</h1>
  <p>Total: <?= number_format(($total ?? 0), 2, '.', ' ') ?> <?= htmlspecialchars($currency) ?></p>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
