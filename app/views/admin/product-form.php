<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="panel">
  <h1><?= $product ? 'Produkt bearbeiten' : 'Produkt hinzufügen' ?></h1>
  <form method="post">
    <input name="name" value="<?= htmlspecialchars($product['name'] ?? '') ?>" placeholder="Name" required>
    <input name="slug" value="<?= htmlspecialchars($product['slug'] ?? '') ?>" placeholder="Slug">
    <textarea name="description" placeholder="Beschreibung"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
    <input name="category" value="<?= htmlspecialchars($product['category'] ?? 'Allgemein') ?>" placeholder="Kategorie">
    <input name="price_cents" type="number" value="<?= (int)($product['price_cents'] ?? 0) ?>" placeholder="Preis in Cent">
    <input name="sale_price_cents" type="number" value="<?= (int)($product['sale_price_cents'] ?? 0) ?>" placeholder="Sale Preis in Cent">
    <input name="stock" type="number" value="<?= (int)($product['stock'] ?? 0) ?>" placeholder="Lager">
    <label><input type="checkbox" name="is_active" <?= !isset($product) || !empty($product['is_active']) ? 'checked' : '' ?>> Aktiv</label>
    <button class="button" type="submit">Speichern</button>
  </form>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
