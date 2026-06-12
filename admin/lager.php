<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

// Sicherstellen, dass jedes Produkt einen Lagereintrag hat (Selbstheilung)
inv_ensure_entries();

$adminTitle = 'Lager';
include __DIR__ . '/partials/admin-layout-top.php';
$inventory  = inv_all();
$currency   = setting_get('currency') ?: 'CHF';
$totalStock = inv_total_all();
$totalValue = inv_total_value();
$outCount   = count(array_filter($inventory, fn($r) => $r['is_out']));
$lowCount   = count(array_filter($inventory, fn($r) => $r['is_low'] && !$r['is_out']));
?>
<p class="admin-kicker">Katalog</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Lager</h1>
</div>

<div class="stat-grid">
  <div class="stat-card stat-highlight">
    <span class="stat-num" data-total-value><?= format_price($totalValue, $currency) ?></span>
    <span class="stat-label">Gesamtlagerwert</span>
  </div>
  <div class="stat-card">
    <span class="stat-num" data-total-stock><?= $totalStock ?></span>
    <span class="stat-label">Artikel an Lager</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $lowCount ?></span>
    <span class="stat-label">Niedriger Bestand</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $outCount ?></span>
    <span class="stat-label">Ausverkauft</span>
  </div>
</div>

<?php if (empty($inventory)): ?>
  <p class="muted">Noch keine Produkte angelegt.</p>
<?php else: ?>
<input type="search" class="admin-search" data-table-filter placeholder="Lager filtern… (Produkt, SKU)" aria-label="Lager filtern">
<div class="table-scroll">
<table class="data-table" data-filter-table>
  <thead><tr><th>Produkt</th><th>Grösse</th><th>SKU</th><th>Bestand</th><th>Reserviert</th><th>Verfügbar</th><th>Wert</th><th>Min.</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($inventory as $row):
      $unit  = $row['variant_price_cents'] !== null
             ? (int)$row['variant_price_cents']
             : (int)($row['sale_price_cents'] ?? $row['price_cents']);
      $value = max(0, (int)$row['available']) * $unit;
    ?>
    <tr class="<?= $row['is_out'] ? 'row-danger' : ($row['is_low'] ? 'row-warn' : '') ?>" data-stock-row data-id="<?= (int)$row['id'] ?>">
      <td><strong><?= h($row['product_name']) ?></strong></td>
      <td><?= h($row['size'] ?: '—') ?></td>
      <td class="muted"><?= h($row['sku'] ?: '—') ?></td>
      <td>
        <div class="stock-stepper" data-stock-stepper>
          <button type="button" class="qty-btn" data-stock-minus aria-label="Bestand verringern">&minus;</button>
          <input type="number" data-stock-input value="<?= (int)$row['stock'] ?>" min="0" max="999999" aria-label="Bestand">
          <button type="button" class="qty-btn" data-stock-plus aria-label="Bestand erhöhen">+</button>
        </div>
      </td>
      <td><?= $row['reserved'] ?></td>
      <td>
        <span class="tag <?= $row['is_out'] ? 'tag-off' : ($row['is_low'] ? 'tag-warn' : 'tag-ok') ?>" data-avail-tag><?= $row['available'] ?></span>
      </td>
      <td data-value-cell><?= format_price($value, $currency) ?></td>
      <td><?= $row['min_stock'] ?></td>
      <td><a href="<?= url('/admin/lager-edit.php?id=' . $row['id']) ?>" class="btn btn-ghost btn-sm">Mehr</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
