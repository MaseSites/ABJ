<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="panel">
  <h1>Einstellungen</h1>
  <form method="post">
    <input name="shop_name" value="<?= htmlspecialchars($settings['shop_name'] ?? 'ABJ Shop') ?>" placeholder="Shop Name">
    <input name="currency" value="<?= htmlspecialchars($settings['currency'] ?? 'CHF') ?>" placeholder="Währung">
    <textarea name="announcement" placeholder="Hinweis"><?= htmlspecialchars($settings['announcement'] ?? '') ?></textarea>
    <input name="sale_ends_at" value="<?= htmlspecialchars($settings['sale_ends_at'] ?? '') ?>" placeholder="Sale Ende">
    <button class="button" type="submit">Speichern</button>
  </form>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
