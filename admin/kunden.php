<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$customers = customers_list();
$currency  = setting_get('currency') ?: 'CHF';

// CSV-Export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kunden-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM für Excel
    fputcsv($out, ['Name', 'E-Mail', 'Telefon', 'Bestellungen', 'Umsatz (bezahlt)', 'Letzte Bestellung', 'Erste Bestellung'], ';');
    foreach ($customers as $c) {
        fputcsv($out, [
            $c['customer_name'], $c['email'], $c['phone'],
            $c['order_count'],
            number_format(($c['revenue_cents'] ?? 0) / 100, 2, '.', ''),
            substr($c['last_order_at'] ?? '', 0, 16),
            substr($c['first_order_at'] ?? '', 0, 16),
        ], ';');
    }
    fclose($out);
    exit;
}

$adminTitle = 'Kunden';
include __DIR__ . '/partials/admin-layout-top.php';
?>
<p class="admin-kicker">Übersicht</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Kunden (<?= count($customers) ?>)</h1>
  <a class="btn btn-ghost btn-sm" href="<?= url('/admin/kunden.php?export=csv') ?>">CSV exportieren</a>
</div>

<?php if (empty($customers)): ?>
  <p class="muted">Noch keine Kunden — Kunden erscheinen hier automatisch nach der ersten Bestellung.</p>
<?php else: ?>
<input type="search" class="admin-search" data-table-filter placeholder="Kunden filtern… (Name, E-Mail)" aria-label="Kunden filtern">
<table class="data-table" data-filter-table>
  <thead><tr><th>Kunde</th><th>E-Mail</th><th>Bestellungen</th><th>Umsatz (bezahlt)</th><th>Letzte Bestellung</th></tr></thead>
  <tbody>
    <?php foreach ($customers as $c): ?>
    <tr>
      <td><strong><?= h($c['customer_name']) ?></strong><?php if ($c['phone']): ?><br><span class="muted" style="font-size:.78rem"><?= h($c['phone']) ?></span><?php endif; ?></td>
      <td><a href="mailto:<?= h($c['email']) ?>" style="color:#b89c67"><?= h($c['email']) ?></a></td>
      <td><?= (int)$c['order_count'] ?></td>
      <td style="font-weight:700"><?= format_price((int)($c['revenue_cents'] ?? 0), $currency) ?></td>
      <td class="muted" style="font-size:.82rem"><?= h(substr($c['last_order_at'] ?? '', 0, 16)) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
