<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$adminTitle = 'Dashboard';
include __DIR__ . '/partials/admin-layout-top.php';

$stats       = orders_stats(7);
$products    = products_list_all();
$activeCount = count(array_filter($products, function($p) { return $p['is_active']; }));
$lowStock    = inv_low_stock();
$currency    = setting_get('currency') ?: 'EUR';
?>

<div class="admin-header">
  <h1>Dashboard</h1>
</div>

<div class="admin-stats-grid">
  <div class="stat-card">
    <span class="stat-label">Umsatz gesamt</span>
    <span class="stat-value"><?= format_price($stats['totalRevenue'], $currency) ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-label">Offene Bestellungen</span>
    <span class="stat-value"><?= $stats['openCount'] ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-label">Aktive Produkte</span>
    <span class="stat-value"><?= $activeCount ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-label">Niedriger Bestand</span>
    <span class="stat-value<?= !empty($lowStock) ? ' stat-warn' : '' ?>"><?= count($lowStock) ?></span>
  </div>
</div>

<div class="admin-chart-section">
  <h2>Bestellungen letzte 7 Tage</h2>
  <div class="bar-chart">
    <?php foreach ($stats['series'] as $day): ?>
      <?php $pct = $stats['maxOrders'] > 0 ? round($day['orders'] / $stats['maxOrders'] * 100) : 0; ?>
      <div class="bar-col">
        <div class="bar-fill" style="height:<?= $pct ?>%"></div>
        <span class="bar-label"><?= $day['orders'] ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($lowStock)): ?>
<div class="admin-section">
  <h2>Niedriger Lagerbestand</h2>
  <table class="admin-table">
    <thead><tr><th>Produkt</th><th>Variante</th><th>Bestand</th><th>Min.</th></tr></thead>
    <tbody>
      <?php foreach ($lowStock as $row): ?>
      <tr>
        <td><?= h($row['product_name']) ?></td>
        <td><?= h($row['size'] ?: '—') ?></td>
        <td class="<?= $row['stock'] <= 0 ? 'text-danger' : 'text-warn' ?>"><?= $row['stock'] ?></td>
        <td><?= $row['min_stock'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
