<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$adminTitle = 'Bestellungen';
include __DIR__ . '/partials/admin-layout-top.php';

$orders   = orders_list();
$currency = setting_get('currency') ?: 'EUR';
?>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Bestellungen</h1></div>

<table class="data-table">
  <thead><tr><th>Referenz</th><th>Kunde</th><th>Datum</th><th>Betrag</th><th>Status</th><th>Zahlung</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td><a href="/admin/bestellung.php?ref=<?= urlencode($o['reference']) ?>"><?= h($o['reference']) ?></a></td>
      <td><?= h($o['customer_name']) ?></td>
      <td><?= h(substr($o['created_at'], 0, 16)) ?></td>
      <td><?= format_price((int)$o['total_cents'], $currency) ?></td>
      <td><span class="tag"><?= h($o['status']) ?></span></td>
      <td><span class="tag <?= $o['payment_status'] === 'bezahlt' ? 'tag-ok' : 'tag-warn' ?>"><?= h($o['payment_status']) ?></span></td>
      <td><a href="/admin/bestellung.php?ref=<?= urlencode($o['reference']) ?>" class="btn btn-ghost btn-sm">Details</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
