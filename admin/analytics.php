<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$adminTitle = 'Analytics';
include __DIR__ . '/partials/admin-layout-top.php';

$currency  = setting_get('currency') ?: 'CHF';
$days      = (int)($_GET['zeitraum'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) $days = 30;

$stats       = orders_stats($days);
$series      = $stats['series'];
$allOrders   = orders_list();
$paidOrders  = array_filter($allOrders, fn($o) => $o['payment_status'] === 'bezahlt');
$topProducts = orders_top_products(8, $days);
$totalStock  = inv_total_all();

$revTotal   = (int)array_sum(array_map(fn($o) => $o['total_cents'], $paidOrders));
$revPeriod  = (int)array_sum(array_column($series, 'revenue'));
$ordPeriod  = (int)array_sum(array_column($series, 'orders'));
$avgOrder   = count($paidOrders) ? (int)round($revTotal / count($paidOrders)) : 0;
$customers  = count(customers_list());
$subs       = newsletter_count();

// Umsatz nach Kategorie (Zeitraum)
$catRevenue = [];
$cut = (new DateTime("-$days days"))->format('Y-m-d');
foreach ($allOrders as $o) {
    if (substr($o['created_at'], 0, 10) < $cut || $o['status'] === 'storniert') continue;
    foreach ($o['items'] as $it) {
        $p = !empty($it['productId']) ? product_by_id((int)$it['productId']) : null;
        $cat = $p['category'] ?? 'Sonstiges';
        $catRevenue[$cat] = ($catRevenue[$cat] ?? 0) + (int)($it['lineCents'] ?? 0);
    }
}
arsort($catRevenue);
$catTotal = array_sum($catRevenue) ?: 1;

// Chart-Geometrie
$W = 720; $H = 220; $pad = 30;
$n = count($series) ?: 1;
$gap = ($W - $pad * 2) / $n;
$bw  = $gap * ($days > 30 ? 0.7 : 0.6);
$dayNames = ['So','Mo','Di','Mi','Do','Fr','Sa'];

function chart_bars(array $series, int $maxVal, string $key, int $W, int $H, int $pad, float $gap, float $bw, array $dayNames, int $days, bool $isMoney = false): string {
    $out = '';
    $n = count($series) ?: 1;
    $labelEvery = $days <= 7 ? 1 : ($days <= 30 ? 5 : 15);
    foreach ($series as $i => $d) {
        $val = (int)$d[$key];
        $hgt = $maxVal > 0 ? round($val / $maxVal * ($H - $pad * 2)) : 0;
        $hgt = max($hgt, 2);
        $x = $pad + $i * $gap + ($gap - $bw) / 2;
        $y = $H - $pad - $hgt;
        $day = new DateTime();
        $day->modify('-' . ($d['dayOffset'] ?? ($n - 1 - $i)) . ' days');
        $title = $day->format('d.m.') . ' · ' . ($isMoney ? 'CHF ' . number_format($val / 100, 2) : $val . ' Bestellungen');
        $out .= '<g><title>' . h($title) . '</title>';
        $out .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . $hgt . '" rx="3" class="bar"/>';
        if ($i % $labelEvery === 0) {
            $label = $days <= 7 ? $dayNames[(int)$day->format('w')] : $day->format('d.m.');
            $out .= '<text x="' . round($x + $bw / 2, 1) . '" y="' . ($H - 9) . '" text-anchor="middle" font-size="9" fill="#55545e">' . h($label) . '</text>';
        }
        $out .= '</g>';
    }
    return $out;
}
$maxRev = max(1, ...array_column($series, 'revenue'));
$maxOrd = max(1, ...array_column($series, 'orders'));
?>

<p class="admin-kicker">Analytics</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Analytics</h1>
  <div class="chip-row">
    <a class="chip<?= $days === 7 ? ' active' : '' ?>" href="<?= url('/admin/analytics.php?zeitraum=7') ?>">7 Tage</a>
    <a class="chip<?= $days === 30 ? ' active' : '' ?>" href="<?= url('/admin/analytics.php?zeitraum=30') ?>">30 Tage</a>
    <a class="chip<?= $days === 90 ? ' active' : '' ?>" href="<?= url('/admin/analytics.php?zeitraum=90') ?>">90 Tage</a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card stat-highlight">
    <span class="stat-num"><?= format_price($revPeriod, $currency) ?></span>
    <span class="stat-label">Umsatz · <?= $days ?> Tage</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $ordPeriod ?></span>
    <span class="stat-label">Bestellungen · <?= $days ?> Tage</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= format_price($revTotal, $currency) ?></span>
    <span class="stat-label">Gesamtumsatz (bezahlt)</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= format_price($avgOrder, $currency) ?></span>
    <span class="stat-label">Ø Bestellwert</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $customers ?></span>
    <span class="stat-label">Kunden</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $totalStock ?></span>
    <span class="stat-label">Lagerbestand gesamt</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= $subs ?></span>
    <span class="stat-label">Newsletter-Abos</span>
  </div>
</div>

<div class="admin-section">
  <h2>Umsatz pro Tag (<?= $days ?> Tage)</h2>
  <svg class="bar-chart bar-chart-wide" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Umsatz pro Tag">
    <line x1="<?= $pad ?>" y1="<?= $H - $pad ?>" x2="<?= $W - $pad ?>" y2="<?= $H - $pad ?>" stroke="rgba(255,255,255,.08)"/>
    <?= chart_bars($series, $maxRev, 'revenue', $W, $H, $pad, $gap, $bw, $dayNames, $days, true) ?>
  </svg>
</div>

<div class="admin-2col">
  <div class="admin-section">
    <h2>Bestellungen pro Tag</h2>
    <svg class="bar-chart bar-chart-wide" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Bestellungen pro Tag">
      <line x1="<?= $pad ?>" y1="<?= $H - $pad ?>" x2="<?= $W - $pad ?>" y2="<?= $H - $pad ?>" stroke="rgba(255,255,255,.08)"/>
      <?= chart_bars($series, $maxOrd, 'orders', $W, $H, $pad, $gap, $bw, $dayNames, $days) ?>
    </svg>
  </div>

  <div class="admin-section">
    <h2>Umsatz nach Kategorie</h2>
    <?php if (empty($catRevenue)): ?>
      <p class="muted">Keine Verkäufe im Zeitraum.</p>
    <?php else: ?>
      <div class="hbar-list">
        <?php foreach (array_slice($catRevenue, 0, 6, true) as $cat => $rev): ?>
        <div class="hbar-row">
          <div class="hbar-meta">
            <span><?= h($cat) ?></span>
            <strong><?= format_price($rev, $currency) ?></strong>
          </div>
          <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($rev / $catTotal * 100, 1) ?>%"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="admin-section">
  <h2>Top-Produkte (<?= $days ?> Tage)</h2>
  <?php if (empty($topProducts)): ?>
    <p class="muted">Keine Verkäufe im Zeitraum.</p>
  <?php else: ?>
    <?php $maxQty = max(1, ...array_column($topProducts, 'qty')); ?>
    <div class="hbar-list">
      <?php foreach ($topProducts as $tp): ?>
      <div class="hbar-row">
        <div class="hbar-meta">
          <span><?= h($tp['name']) ?></span>
          <strong><?= (int)$tp['qty'] ?>× · <?= format_price((int)$tp['revenue'], $currency) ?></strong>
        </div>
        <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($tp['qty'] / $maxQty * 100, 1) ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
