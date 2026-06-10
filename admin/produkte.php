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

<table class="data-table">
  <thead>
    <tr><th>Bild</th><th>Name</th><th>Kategorie</th><th>Preis</th><th>Bestand</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($products as $p): ?>
    <tr>
      <td><img src="<?= h($p['images'][0]['src'] ?? placeholder_svg($p['name'])) ?>" style="width:40px;height:50px;object-fit:cover;border-radius:4px"></td>
      <td><a href="/admin/produkt-edit.php?id=<?= $p['id'] ?>"><?= h($p['name']) ?></a></td>
      <td><?= h($p['category']) ?></td>
      <td>
        <?php if ($p['sale_price_cents']): ?>
          <span style="color:var(--accent)"><?= format_price((int)$p['sale_price_cents'], $currency) ?></span>
          <s style="opacity:.5"><?= format_price((int)$p['price_cents'], $currency) ?></s>
        <?php else: ?>
          <?= format_price((int)$p['price_cents'], $currency) ?>
        <?php endif; ?>
      </td>
      <td><?= (int)$p['stock'] ?></td>
      <td><span class="tag <?= $p['is_active'] ? 'tag-ok' : 'tag-off' ?>"><?= $p['is_active'] ? 'aktiv' : 'inaktiv' ?></span></td>
      <td>
        <a href="/admin/produkt-edit.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Bearbeiten</a>
        <form method="post" action="/admin/produkt-delete.php" style="display:inline" onsubmit="return confirm('Produkt löschen?')">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button class="btn btn-ghost btn-sm btn-danger" type="submit">Löschen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
