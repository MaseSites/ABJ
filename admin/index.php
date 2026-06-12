<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$adminTitle = 'Dashboard';
include __DIR__ . '/partials/admin-layout-top.php';

$ostats       = orders_stats(14);
$allProducts  = products_list_all();
$allOrders    = orders_list();
$recentOrders = array_slice($allOrders, 0, 8);
$currency     = setting_get('currency') ?: 'CHF';
$topProducts  = orders_top_products(5, 90);
$lowStock     = array_slice(inv_low_stock(), 0, 6);

// Umsatz der letzten 30 Tage
$rev30 = 0;
try {
    $rev30 = (int)db()->query("SELECT COALESCE(SUM(total_cents),0) AS c FROM orders WHERE payment_status='bezahlt' AND created_at >= datetime('now','-30 days')")->fetch()['c'];
} catch (\Throwable $e) {}

$stats = [
    'revenue'     => format_price($ostats['totalRevenue'], $currency),
    'revenue30'   => format_price($rev30, $currency),
    'orders'      => count($allOrders),
    'open'        => $ostats['openCount'],
    'products'    => count($allProducts),
    'active'      => count(array_filter($allProducts, fn($p) => $p['is_active'])),
    'subscribers' => newsletter_count(),
    'messages'    => messages_unread_count(),
    'reviews'     => reviews_pending_count(),
];

$W = 640; $H = 170; $pad = 24;
$series = $ostats['series'] ?? [];
$n = count($series) ?: 1;
$bw  = ($W - $pad * 2) / $n * 0.58;
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
    <a class="btn btn-primary btn-sm" href="<?= url('/admin/produkt-edit.php') ?>">+ Produkt</a>
    <a class="btn btn-ghost btn-sm" href="<?= url('/admin/bestellungen.php') ?>">Bestellungen</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card stat-highlight">
    <span class="stat-num"><?= h($stats['revenue30']) ?></span>
    <span class="stat-label">Umsatz 30 Tage</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= h($stats['revenue']) ?></span>
    <span class="stat-label">Umsatz gesamt</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $stats['orders'] ?></span>
    <span class="stat-label">Bestellungen</span>
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
  <div class="stat-card">
    <span class="stat-num"><?= $stats['reviews'] ?></span>
    <span class="stat-label">Bewertungen offen</span>
  </div>
</div>

<div class="admin-2col">
  <div class="admin-section">
    <h2>Bestellungen – letzte 14 Tage</h2>
    <svg class="bar-chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Bestellungen der letzten 14 Tage">
      <?php foreach ($series as $i => $d): ?>
        <?php
          $hgt = $ostats['maxOrders'] > 0 ? round($d['orders'] / $ostats['maxOrders'] * ($H - $pad * 2)) : 2;
          $hgt = max($hgt, 2);
          $x = $pad + $i * $gap + ($gap - $bw) / 2;
          $y = $H - $pad - $hgt;
          $day = new DateTime();
          $day->modify('-' . ($d['dayOffset'] ?? ($n - 1 - $i)) . ' days');
          $dname = $dayNames[(int)$day->format('w')];
        ?>
        <g>
          <rect x="<?= round($x, 1) ?>" y="<?= round($y, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= $hgt ?>" rx="4" class="bar"/>
          <text x="<?= round($x + $bw/2, 1) ?>" y="<?= $H - 7 ?>" text-anchor="middle" font-size="9" fill="#55545e"><?= $dname ?></text>
          <?php if ($d['orders'] > 0): ?>
            <text x="<?= round($x + $bw/2, 1) ?>" y="<?= round($y - 5, 1) ?>" text-anchor="middle" font-size="10" fill="#c8ccd8" font-weight="700"><?= $d['orders'] ?></text>
          <?php endif; ?>
        </g>
      <?php endforeach; ?>
    </svg>
  </div>

  <div class="admin-section">
    <h2>Top-Produkte (90 Tage)</h2>
    <?php if (empty($topProducts)): ?>
      <p class="muted">Noch keine Verkäufe.</p>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>Produkt</th><th>Verkauft</th><th>Umsatz</th></tr></thead>
        <tbody>
          <?php foreach ($topProducts as $tp): ?>
          <tr>
            <td><strong><?= h($tp['name']) ?></strong></td>
            <td><?= (int)$tp['qty'] ?>×</td>
            <td><?= format_price((int)$tp['revenue'], $currency) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if (!empty($lowStock)): ?>
    <h2 style="margin-top:1.6rem">Lager-Warnungen</h2>
    <table class="data-table">
      <thead><tr><th>Produkt</th><th>Variante</th><th>Bestand</th></tr></thead>
      <tbody>
        <?php foreach ($lowStock as $ls): ?>
        <tr>
          <td><a href="<?= url('/admin/lager-edit.php?id=' . (int)$ls['product_id']) ?>" style="color:#b89c67;font-weight:600"><?= h($ls['product_name']) ?></a></td>
          <td class="muted"><?= h($ls['size'] ?: '–') ?></td>
          <td><span class="tag <?= ($ls['stock'] - $ls['reserved']) <= 0 ? 'tag-off' : 'tag-warn' ?>"><?= max(0, $ls['stock'] - $ls['reserved']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="admin-section">
  <div class="admin-head-row" style="margin-bottom:1rem">
    <h2 style="margin-bottom:0">Letzte Bestellungen</h2>
    <a class="btn btn-ghost btn-sm" href="<?= url('/admin/bestellungen.php') ?>">Alle anzeigen</a>
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
          <td><a href="<?= url('/admin/bestellung.php?ref=' . urlencode($o['reference'])) ?>" style="color:#b89c67;font-weight:700"><?= h($o['reference']) ?></a>
            <?php if (empty($o['is_seen'])): ?><span class="tag tag-new" style="margin-left:.3rem">NEU</span><?php endif; ?>
          </td>
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
