<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$adminTitle = 'Produkte';
include __DIR__ . '/partials/admin-layout-top.php';

$products = products_list_all();
$currency = setting_get('currency') ?: 'CHF';
$activeCount = count(array_filter($products, fn($p) => $p['is_active']));
?>
<p class="admin-kicker">Katalog</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Produkte <span class="muted" style="font-weight:400;font-size:1rem">(<?= count($products) ?>)</span></h1>
  <a href="<?= url('/admin/produkt/neu') ?>" class="btn btn-primary">
    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>
    Neues Produkt
  </a>
</div>

<?php if (empty($products)): ?>
  <div class="dash-empty" style="padding:3rem 1rem">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="48" height="48"><path d="M2 4l1.5-2.5h9L14 4"/><rect x="1" y="4" width="14" height="10" rx="1.5"/><path d="M5 4v1.5a3 3 0 006 0V4"/></svg>
    <p>Noch keine Produkte. Lege das erste an.</p>
    <a class="btn btn-primary" href="<?= url('/admin/produkt/neu') ?>">+ Neues Produkt</a>
  </div>
<?php else: ?>
<input type="search" class="admin-search" data-product-filter placeholder="Produkte filtern… (Name, Kategorie)" aria-label="Produkte filtern">

<div class="table-card">
<table class="data-table" data-filter-table>
  <thead>
    <tr><th>Produkt</th><th>Kategorie</th><th>Preis</th><th>Bestand</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($products as $p):
      $stock = inv_total_stock((int)$p['id']);
    ?>
    <tr>
      <td>
        <div class="prod-cell">
          <img class="prod-cell-img" src="<?= h($p['images'][0]['src'] ?? placeholder_svg($p['name'])) ?>" alt="">
          <div class="prod-cell-info">
            <strong><?= h($p['name']) ?></strong>
            <?php if ($p['is_bestseller']): ?><span class="tag tag-gold">Bestseller</span><?php endif; ?>
          </div>
        </div>
      </td>
      <td class="muted"><?= h($p['category']) ?></td>
      <td>
        <?php if ($p['sale_price_cents']): ?>
          <strong style="color:#e0e2ea"><?= format_price((int)$p['sale_price_cents'], $currency) ?></strong>
          <span class="muted" style="text-decoration:line-through;font-size:.8rem"><?= format_price((int)$p['price_cents'], $currency) ?></span>
        <?php else: ?>
          <strong style="color:#e0e2ea"><?= format_price((int)$p['price_cents'], $currency) ?></strong>
        <?php endif; ?>
      </td>
      <td><span class="tag <?= $stock <= 0 ? 'tag-off' : ($stock <= 5 ? 'tag-warn' : 'tag-ok') ?>"><?= $stock ?> Stk.</span></td>
      <td><span class="tag <?= $p['is_active'] ? 'tag-ok' : 'tag-off' ?>"><?= $p['is_active'] ? 'Aktiv' : 'Inaktiv' ?></span></td>
      <td class="cell-actions">
        <a href="<?= url('/admin/produkt/' . $p['id']) ?>" class="btn btn-ghost btn-sm">Bearbeiten</a>
        <button class="btn btn-danger btn-sm" data-delete-product="<?= $p['id'] ?>" data-name="<?= h($p['name']) ?>">Löschen</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
