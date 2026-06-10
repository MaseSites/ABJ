<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$adminTitle = 'Lager';
include __DIR__ . '/partials/admin-layout-top.php';
$inventory = inv_all();
?>
<div class="admin-header">
  <h1>Lager</h1>
</div>

<table class="admin-table">
  <thead><tr><th>Produkt</th><th>Grösse</th><th>SKU</th><th>Bestand</th><th>Reserviert</th><th>Verfügbar</th><th>Min.</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($inventory as $row): ?>
    <tr class="<?= $row['is_out'] ? 'row-danger' : ($row['is_low'] ? 'row-warn' : '') ?>">
      <td><?= h($row['product_name']) ?></td>
      <td><?= h($row['size'] ?: '—') ?></td>
      <td><?= h($row['sku'] ?: '—') ?></td>
      <td><?= $row['stock'] ?></td>
      <td><?= $row['reserved'] ?></td>
      <td><?= $row['available'] ?></td>
      <td><?= $row['min_stock'] ?></td>
      <td><a href="/admin/lager-edit.php?id=<?= $row['id'] ?>" class="btn btn-ghost btn-sm">Bearbeiten</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
