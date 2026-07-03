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

// Nach Produkt gruppieren
$groups = [];
foreach ($inventory as $row) {
    $pid = (int)$row['product_id'];
    if (!isset($groups[$pid])) {
        $groups[$pid] = ['name' => $row['product_name'], 'slug' => $row['slug'], 'rows' => []];
    }
    $groups[$pid]['rows'][] = $row;
}
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

<?php if (empty($groups)): ?>
  <p class="muted">Noch keine Produkte angelegt.</p>
<?php else: ?>
<input type="search" class="admin-search" data-lager-filter placeholder="Lager filtern... (Produkt)" aria-label="Lager filtern">

<div class="lager-list">
  <?php foreach ($groups as $pid => $g):
    $rows      = $g['rows'];
    $available = array_sum(array_map(fn($r) => max(0, (int)$r['available']), $rows));
    $value     = 0;
    foreach ($rows as $r) {
      $unit = $r['variant_price_cents'] !== null ? (int)$r['variant_price_cents'] : (int)($r['sale_price_cents'] ?? $r['price_cents']);
      $value += max(0, (int)$r['available']) * $unit;
    }
    $anyOut = count(array_filter($rows, fn($r) => $r['is_out']));
    $isMulti = count($rows) > 1 || ($rows[0]['size'] ?? '') !== '';
  ?>
  <details class="lager-product" data-lager-row<?= $available <= 0 ? ' data-allout' : '' ?>>
    <summary>
      <span class="lager-chevron" aria-hidden="true">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 4 10 8 6 12"/></svg>
      </span>
      <span class="lager-prod-name" data-lager-name><?= h($g['name']) ?></span>
      <span class="lager-prod-meta">
        <span class="tag"><?= count($rows) ?> <?= $isMulti ? 'Varianten' : 'Eintrag' ?></span>
        <span class="tag <?= $available <= 0 ? 'tag-off' : 'tag-ok' ?>">Verfügbar <?= $available ?></span>
        <span class="muted"><?= format_price($value, $currency) ?></span>
        <a href="<?= url('/admin/produkt/' . $pid) ?>" class="btn btn-ghost btn-sm" onclick="event.stopPropagation()">Bearbeiten</a>
      </span>
    </summary>

    <table class="data-table lager-variant-table">
      <thead><tr><th>Variante</th><th>SKU</th><th>Bestand</th><th>Reserviert</th><th>Verfügbar</th><th>Wert</th><th>Min.</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row):
          $unit  = $row['variant_price_cents'] !== null ? (int)$row['variant_price_cents'] : (int)($row['sale_price_cents'] ?? $row['price_cents']);
          $rowValue = max(0, (int)$row['available']) * $unit;
        ?>
        <tr class="<?= $row['is_out'] ? 'row-danger' : ($row['is_low'] ? 'row-warn' : '') ?>" data-stock-row data-id="<?= (int)$row['id'] ?>">
          <td><?= h($row['size'] ?: 'Standard') ?></td>
          <td class="muted"><?= h($row['sku'] ?: '-') ?></td>
          <td>
            <div class="stock-stepper" data-stock-stepper>
              <button type="button" class="qty-btn" data-stock-minus aria-label="Bestand verringern">&minus;</button>
              <input type="number" data-stock-input value="<?= (int)$row['stock'] ?>" min="0" max="999999" aria-label="Bestand">
              <button type="button" class="qty-btn" data-stock-plus aria-label="Bestand erhöhen">+</button>
            </div>
          </td>
          <td><?= $row['reserved'] ?></td>
          <td><span class="tag <?= $row['is_out'] ? 'tag-off' : ($row['is_low'] ? 'tag-warn' : 'tag-ok') ?>" data-avail-tag><?= $row['available'] ?></span></td>
          <td data-value-cell><?= format_price($rowValue, $currency) ?></td>
          <td><?= $row['min_stock'] ?></td>
          <td><a href="<?= url('/admin/lager-edit.php?id=' . $row['id']) ?>" class="btn btn-ghost btn-sm">Mehr</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </details>
  <?php endforeach; ?>
</div>

<script>
(function () {
  var input = document.querySelector('[data-lager-filter]');
  if (!input) return;
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    document.querySelectorAll('[data-lager-row]').forEach(function (el) {
      var name = (el.querySelector('[data-lager-name]') || {}).textContent || '';
      var match = name.toLowerCase().indexOf(q) !== -1;
      el.style.display = match ? '' : 'none';
      if (q && match) el.open = true;
    });
  });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
