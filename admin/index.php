<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$adminTitle = 'Dashboard';
include __DIR__ . '/partials/admin-layout-top.php';

$allOrders    = orders_list();
$recentOrders = array_slice($allOrders, 0, 5);
$currency     = setting_get('currency') ?: 'CHF';
$lowStock     = array_slice(inv_low_stock(), 0, 5);
$totalStock   = inv_total_all();
$openCount    = count(array_filter($allOrders, fn($o) => $o['payment_status'] !== 'bezahlt' && $o['status'] !== 'storniert'));
$newCount     = count(array_filter($allOrders, fn($o) => empty($o['is_seen'])));

$revTotal = 0; $rev30 = 0;
try {
    $revTotal = (int)db()->query("SELECT COALESCE(SUM(total_cents),0) AS c FROM orders WHERE payment_status='bezahlt'")->fetch()['c'];
    $rev30    = (int)db()->query("SELECT COALESCE(SUM(total_cents),0) AS c FROM orders WHERE payment_status='bezahlt' AND created_at >= datetime('now','-30 days')")->fetch()['c'];
} catch (\Throwable $e) {}

function statusTagClass(string $status): string {
    return ['bezahlt' => 'tag-ok', 'offen' => 'tag-warn', 'storniert' => 'tag-off'][$status] ?? '';
}
?>

<p class="admin-kicker">Dashboard</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Übersicht</h1>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap">
    <a class="btn btn-primary btn-sm" href="<?= url('/admin/produkt-edit.php') ?>">+ Produkt</a>
    <a class="btn btn-sm" href="<?= url('/admin/analytics.php') ?>">Analytics ansehen</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card stat-highlight">
    <span class="stat-num"><?= format_price($rev30, $currency) ?></span>
    <span class="stat-label">Umsatz · letzte 30 Tage</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= format_price($revTotal, $currency) ?></span>
    <span class="stat-label">Gesamtumsatz</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $openCount ?></span>
    <span class="stat-label">Offene Bestellungen<?= $newCount ? " · $newCount neu" : '' ?></span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $totalStock ?></span>
    <span class="stat-label">Lagerbestand gesamt</span>
  </div>
</div>

<div class="admin-2col">
  <div class="admin-section">
    <div class="admin-head-row" style="margin-bottom:1rem">
      <h2 style="margin-bottom:0">Letzte Bestellungen</h2>
      <a class="btn btn-ghost btn-sm" href="<?= url('/admin/bestellungen.php') ?>">Alle</a>
    </div>
    <?php if (empty($recentOrders)): ?>
      <p class="muted">Noch keine Bestellungen vorhanden.</p>
    <?php else: ?>
    <div class="table-scroll">
      <table class="data-table">
        <thead><tr><th>Referenz</th><th>Kunde</th><th>Summe</th><th>Zahlung</th></tr></thead>
        <tbody>
          <?php foreach ($recentOrders as $o): ?>
          <tr class="<?= empty($o['is_seen']) ? 'order-row-new' : '' ?>">
            <td><a href="<?= url('/admin/bestellung.php?ref=' . urlencode($o['reference'])) ?>" style="color:#b89c67;font-weight:700"><?= h($o['reference']) ?></a></td>
            <td><strong><?= h($o['customer_name']) ?></strong></td>
            <td style="font-weight:700;color:#e0e2ea"><?= format_price($o['total_cents'], $currency) ?></td>
            <td><span class="tag <?= statusTagClass($o['payment_status']) ?>"><?= h($o['payment_status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="admin-section">
    <div class="admin-head-row" style="margin-bottom:1rem">
      <h2 style="margin-bottom:0">Lager-Warnungen</h2>
      <a class="btn btn-ghost btn-sm" href="<?= url('/admin/lager.php') ?>">Zum Lager</a>
    </div>
    <?php if (empty($lowStock)): ?>
      <p class="muted">Alles gut — keine niedrigen Bestände.</p>
    <?php else: ?>
    <div class="table-scroll">
      <table class="data-table">
        <thead><tr><th>Produkt</th><th>Variante</th><th>Bestand</th></tr></thead>
        <tbody>
          <?php foreach ($lowStock as $ls): ?>
          <tr>
            <td><a href="<?= url('/admin/lager-edit.php?id=' . (int)$ls['id']) ?>" style="color:#b89c67;font-weight:600"><?= h($ls['product_name']) ?></a></td>
            <td class="muted"><?= h($ls['size'] ?: '–') ?></td>
            <td><span class="tag <?= ($ls['stock'] - $ls['reserved']) <= 0 ? 'tag-off' : 'tag-warn' ?>"><?= max(0, $ls['stock'] - $ls['reserved']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <h2 style="margin:1.4rem 0 .8rem">Schnellzugriff</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <a class="btn btn-sm" href="<?= url('/admin/produkte.php') ?>">Produkte</a>
      <a class="btn btn-sm" href="<?= url('/admin/rabatte.php') ?>">Rabattcodes</a>
      <a class="btn btn-sm" href="<?= url('/admin/bewertungen.php') ?>">Bewertungen</a>
      <a class="btn btn-sm" href="<?= url('/admin/kunden.php') ?>">Kunden</a>
      <a class="btn btn-sm" href="<?= url('/admin/einstellungen.php') ?>">Einstellungen</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
