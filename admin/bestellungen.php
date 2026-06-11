<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $ref = trim($_POST['ref'] ?? '');
    if ($ref) order_delete($ref);
    redirect('/admin/bestellungen.php');
}

$adminTitle = 'Bestellungen';
include __DIR__ . '/partials/admin-layout-top.php';

$orders   = orders_list();
$currency = setting_get('currency') ?: 'CHF';
?>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Bestellungen</h1></div>

<table class="data-table">
  <thead><tr><th>Referenz</th><th>Kunde</th><th>Datum</th><th>Betrag</th><th>Status</th><th>Zahlung</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($orders as $o):
      $isNew = empty($o['is_seen']);
    ?>
    <tr class="<?= $isNew ? 'order-row-new' : '' ?>">
      <td>
        <a href="/admin/bestellung.php?ref=<?= urlencode($o['reference']) ?>"><?= h($o['reference']) ?></a>
        <?php if ($isNew): ?><span class="tag tag-new" style="margin-left:.4rem">NEU</span><?php endif; ?>
      </td>
      <td><?= h($o['customer_name']) ?></td>
      <td><?= h(substr($o['created_at'], 0, 16)) ?></td>
      <td><?= format_price((int)$o['total_cents'], $currency) ?></td>
      <td><span class="tag"><?= h($o['status']) ?></span></td>
      <td><span class="tag <?= $o['payment_status'] === 'bezahlt' ? 'tag-ok' : 'tag-warn' ?>"><?= h($o['payment_status']) ?></span></td>
      <td style="white-space:nowrap;display:flex;gap:.4rem;align-items:center">
        <a href="/admin/bestellung.php?ref=<?= urlencode($o['reference']) ?>" class="btn btn-ghost btn-sm">Details</a>
        <form method="post" action="/admin/bestellungen.php" onsubmit="return confirm('Bestellung <?= h($o['reference']) ?> wirklich löschen?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="ref" value="<?= h($o['reference']) ?>">
          <button class="btn btn-ghost btn-sm" type="submit" style="color:#e05c48;border-color:#e05c48">Löschen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
