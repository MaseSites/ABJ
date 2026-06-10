<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$adminTitle = 'Dashboard';
include __DIR__ . '/partials/admin-layout-top.php';

$ostats      = orders_stats(7);
$allProducts = products_list_all();
$allOrders   = orders_list();
$recentOrders = array_slice($allOrders, 0, 8);
$currency    = setting_get('currency') ?: 'EUR';

$stats = [
    'revenue'     => format_price($ostats['totalRevenue'], $currency),
    'orders'      => count($allOrders),
    'open'        => $ostats['openCount'],
    'products'    => count($allProducts),
    'active'      => count(array_filter($allProducts, function($p) { return $p['is_active']; })),
    'subscribers' => newsletter_count(),
    'messages'    => messages_unread_count(),
];

$W = 480; $H = 140; $pad = 20;
$series = $ostats['series'] ?? [];
$n = count($series) ?: 1;
$bw  = ($W - $pad * 2) / $n * 0.55;
$gap = ($W - $pad * 2) / $n;
$dayNames = ['So','Mo','Di','Mi','Do','Fr','Sa'];

function statusTagClass(string $status): string {
    return ['bezahlt' => 'tag-ok', 'offen' => 'tag-warn', 'storniert' => 'tag-off'][$status] ?? '';
}
?>

<p class="admin-kicker">Dashboard</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Übersicht</h1>
  <div style="display:flex;gap:.6rem">
    <a class="btn btn-primary btn-sm" href="/admin/produkt-edit.php">+ Produkt</a>
    <a class="btn btn-ghost btn-sm" href="/admin/bestellungen.php">Bestellungen</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <span class="stat-num"><?= h($stats['revenue']) ?></span>
    <span class="stat-label">Umsatz (bezahlt)</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $stats['orders'] ?></span>
    <span class="stat-label">Bestellungen gesamt</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $stats['open'] ?></span>
    <span class="stat-label">Offen</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $stats['products'] ?></span>
    <span class="stat-label">Produkte (<?= $stats['active'] ?> aktiv)</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $stats['subscribers'] ?></span>
    <span class="stat-label">Newsletter-Abos</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $stats['messages'] ?></span>
    <span class="stat-label">Neue Nachrichten</span>
  </div>
</div>

<div class="admin-section">
  <h2>Bestellungen – letzte 7 Tage</h2>
  <svg class="bar-chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Bestellungen der letzten 7 Tage">
    <?php foreach ($series as $i => $d): ?>
      <?php
        $h = $ostats['maxOrders'] > 0 ? round($d['orders'] / $ostats['maxOrders'] * ($H - $pad * 2)) : 2;
        $h = max($h, 2);
        $x = $pad + $i * $gap + ($gap - $bw) / 2;
        $y = $H - $pad - $h;
        $day = new DateTime();
        $day->modify('-' . ($d['dayOffset'] ?? ($n - 1 - $i)) . ' days');
        $dname = $dayNames[(int)$day->format('w')];
      ?>
      <g>
        <rect x="<?= round($x, 1) ?>" y="<?= round($y, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= $h ?>" rx="4" class="bar"/>
        <text x="<?= round($x + $bw/2, 1) ?>" y="<?= $H - 5 ?>" text-anchor="middle" font-size="10" fill="#3d4055"><?= $dname ?></text>
        <?php if ($d['orders'] > 0): ?>
          <text x="<?= round($x + $bw/2, 1) ?>" y="<?= round($y - 4, 1) ?>" text-anchor="middle" font-size="10" fill="#c8ccd8" font-weight="700"><?= $d['orders'] ?></text>
        <?php endif; ?>
      </g>
    <?php endforeach; ?>
  </svg>
</div>

<div class="admin-section">
  <div class="admin-head-row" style="margin-bottom:1rem">
    <h2 style="margin-bottom:0">Letzte Bestellungen</h2>
    <a class="btn btn-ghost btn-sm" href="/admin/bestellungen.php">Alle anzeigen</a>
  </div>
  <?php if (empty($recentOrders)): ?>
    <p class="muted">Noch keine Bestellungen vorhanden.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th>Referenz</th><th>Kunde</th><th>Summe</th><th>Zahlung</th><th>Status</th><th>Datum</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><a href="/admin/bestellung.php?ref=<?= urlencode($o['reference']) ?>" style="color:#b89c67;font-weight:700"><?= h($o['reference']) ?></a></td>
          <td><strong><?= h($o['customer_name']) ?></strong></td>
          <td style="font-weight:700;color:#e0e2ea"><?= format_price($o['total_cents'], $currency) ?></td>
          <td><span class="tag <?= statusTagClass($o['payment_status']) ?>"><?= h($o['payment_status']) ?></span></td>
          <td><span class="tag"><?= h($o['status']) ?></span></td>
          <td class="muted" style="font-size:.8rem"><?= substr($o['created_at'] ?? '', 0, 10) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
