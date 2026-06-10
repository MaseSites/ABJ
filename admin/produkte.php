<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$adminTitle = 'Produkte';
include __DIR__ . '/partials/admin-layout-top.php';

$products = products_list_all();
$currency = setting_get('currency') ?: 'EUR';
?>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Produkte</h1>
  <a href="/admin/produkt-edit.php" class="btn btn-primary">+ Neues Produkt</a>
</div>

<?php if (!empty($products)): ?>
<input type="search" class="admin-search" data-product-filter placeholder="Produkte filtern… (Name, Kategorie)" aria-label="Produkte filtern">
<?php endif; ?>

<?php if (empty($products)): ?>
  <p class="muted">Noch keine Produkte. Lege das erste an.</p>
<?php else: ?>
<table class="data-table">
  <thead>
    <tr><th>Bild</th><th>Name</th><th>Kategorie</th><th>Preis</th><th>Bestand</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($products as $p): ?>
    <tr>
      <td class="cell-img"><img src="<?= h($p['images'][0]['src'] ?? placeholder_svg($p['name'])) ?>" alt=""></td>
      <td>
        <strong><?= h($p['name']) ?></strong>
        <?php if ($p['is_bestseller']): ?><span class="tag tag-gold">Bestseller</span><?php endif; ?>
      </td>
      <td><?= h($p['category']) ?></td>
      <td>
        <?php if ($p['sale_price_cents']): ?>
          <?= format_price((int)$p['sale_price_cents'], $currency) ?>
          <span class="muted" style="text-decoration:line-through"><?= format_price((int)$p['price_cents'], $currency) ?></span>
        <?php else: ?>
          <?= format_price((int)$p['price_cents'], $currency) ?>
        <?php endif; ?>
      </td>
      <td><?= (int)$p['stock'] ?></td>
      <td><span class="tag <?= $p['is_active'] ? 'tag-ok' : 'tag-off' ?>"><?= $p['is_active'] ? 'aktiv' : 'inaktiv' ?></span></td>
      <td class="cell-actions">
        <a href="/admin/produkt-edit.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Bearbeiten</a>
        <button class="btn btn-danger btn-sm"
                data-delete-product="<?= $p['id'] ?>"
                data-name="<?= h($p['name']) ?>">Löschen</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
