<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$adminTitle = 'Analytics';
include __DIR__ . '/partials/admin-layout-top.php';

$currency = setting_get('currency') ?: 'CHF';
$days     = (int)($_GET['zeitraum'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) $days = 30;

$stats       = orders_stats($days);
$series      = $stats['series'];
$allOrders   = orders_list();
$paidOrders  = array_filter($allOrders, fn($o) => $o['payment_status'] === 'bezahlt');
$topProducts = orders_top_products(8, $days);
$totalStock  = inv_total_all();
$totalValue  = inv_total_value();

$revTotal   = (int)array_sum(array_map(fn($o) => $o['total_cents'], $paidOrders));
$revPeriod  = (int)array_sum(array_column($series, 'revenue'));
$ordPeriod  = (int)array_sum(array_column($series, 'orders'));
$avgPeriod  = $ordPeriod ? (int)round($revPeriod / $ordPeriod) : 0;
$customers  = count(customers_list());
$subs       = newsletter_count();
$accounts   = (int)db()->query('SELECT COUNT(*) AS n FROM accounts')->fetch()['n'];

$cut = (new DateTime("-$days days"))->format('Y-m-d');
$inPeriod = array_values(array_filter($allOrders, fn($o) => substr($o['created_at'], 0, 10) >= $cut && $o['status'] !== 'storniert'));

// --- Verteilungen / Aggregationen (im Zeitraum) ---
$catRevenue = [];           // Umsatz nach Kategorie
$payDist    = [];           // Zahlungsarten
$statusDist = [];           // Bestellstatus
$weekday    = array_fill(1, 7, 0); // Umsatz nach Wochentag (1=Mo..7=So)
$itemsSum   = 0;

foreach ($inPeriod as $o) {
    $pm = $o['payment_method'] ?: 'unbekannt';
    $payDist[$pm] = ($payDist[$pm] ?? 0) + 1;
    $st = $o['status'] ?: 'neu';
    $statusDist[$st] = ($statusDist[$st] ?? 0) + 1;
    $wd = (int)date('N', strtotime($o['created_at']));
    $weekday[$wd] += (int)$o['total_cents'];
    foreach ($o['items'] as $it) {
        $itemsSum += (int)($it['qty'] ?? 1);
        $pid = !empty($it['productId']) ? (int)$it['productId'] : 0;
        $p = $pid ? product_by_id($pid) : null;
        $cat = $p['category'] ?? 'Sonstiges';
        $catRevenue[$cat] = ($catRevenue[$cat] ?? 0) + (int)($it['lineCents'] ?? 0);
    }
}
arsort($catRevenue);
arsort($payDist);
arsort($statusDist);
$catTotal = array_sum($catRevenue) ?: 1;
$avgItems = count($inPeriod) ? round($itemsSum / count($inPeriod), 1) : 0;

// Neukunden vs. Stammkunden (gesamt, anhand E-Mail-Häufigkeit)
$emailCounts = [];
foreach ($allOrders as $o) {
    $e = strtolower(trim($o['email']));
    if ($e === '') continue;
    $emailCounts[$e] = ($emailCounts[$e] ?? 0) + 1;
}
$returning = count(array_filter($emailCounts, fn($c) => $c > 1));
$oneTime   = count($emailCounts) - $returning;

$payLabels = ['vorkasse' => 'Banküberweisung', 'unbekannt' => 'Unbekannt'];
$statusLabels = ['neu' => 'Neu', 'in_bearbeitung' => 'In Bearbeitung', 'versendet' => 'Versendet', 'storniert' => 'Storniert'];

// --- Chart-Helfer ---
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
        $hgt = max($maxVal > 0 ? round($val / $maxVal * ($H - $pad * 2)) : 0, 2);
        $x = $pad + $i * $gap + ($gap - $bw) / 2;
        $y = $H - $pad - $hgt;
        $day = (new DateTime())->modify('-' . ($d['dayOffset'] ?? ($n - 1 - $i)) . ' days');
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

// SVG-Donut: $segments = [['label'=>..,'value'=>..,'color'=>..], ...]
function chart_donut(array $segments, string $centerTop = '', string $centerSub = ''): string {
    $total = array_sum(array_column($segments, 'value')) ?: 1;
    $r = 52; $cx = 70; $cy = 70; $circ = 2 * M_PI * $r;
    $offset = 0;
    $svg = '<svg viewBox="0 0 140 140" class="donut" role="img">';
    $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="16"/>';
    foreach ($segments as $s) {
        if ($s['value'] <= 0) continue;
        $len = $s['value'] / $total * $circ;
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="' . h($s['color']) . '" stroke-width="16"'
              . ' stroke-dasharray="' . round($len, 2) . ' ' . round($circ - $len, 2) . '"'
              . ' stroke-dashoffset="' . round(-$offset, 2) . '" transform="rotate(-90 ' . $cx . ' ' . $cy . ')">'
              . '<title>' . h($s['label'] . ': ' . $s['value']) . '</title></circle>';
        $offset += $len;
    }
    if ($centerTop !== '') {
        $svg .= '<text x="70" y="66" text-anchor="middle" font-size="17" font-weight="800" fill="#fff">' . h($centerTop) . '</text>';
        $svg .= '<text x="70" y="84" text-anchor="middle" font-size="8.5" fill="#7a7f8e" letter-spacing=".06em">' . h(strtoupper($centerSub)) . '</text>';
    }
    return $svg . '</svg>';
}
$donutColors = ['#b89c67', '#6ee7b7', '#a5b4fc', '#d4af56', '#f8a090', '#7a7f8e'];

$maxRev = max(1, ...array_column($series, 'revenue'));
$maxOrd = max(1, ...array_column($series, 'orders'));
$maxWd  = max(1, ...array_values($weekday));
?>

<p class="admin-kicker">Auswertung</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Analytics</h1>
  <div class="chip-row">
    <a class="chip<?= $days === 7 ? ' active' : '' ?>" href="<?= url('/admin/analytics.php?zeitraum=7') ?>">7 Tage</a>
    <a class="chip<?= $days === 30 ? ' active' : '' ?>" href="<?= url('/admin/analytics.php?zeitraum=30') ?>">30 Tage</a>
    <a class="chip<?= $days === 90 ? ' active' : '' ?>" href="<?= url('/admin/analytics.php?zeitraum=90') ?>">90 Tage</a>
  </div>
</div>

<!-- 3 Hero-Kacheln (wichtigste KPIs) -->
<div class="hero-stat-grid">
  <div class="hero-stat">
    <span class="hero-stat-label">Umsatz · <?= $days ?> Tage</span>
    <span class="hero-stat-num"><?= format_price($revPeriod, $currency) ?></span>
    <span class="hero-stat-sub">Gesamt bezahlt: <?= format_price($revTotal, $currency) ?></span>
  </div>
  <div class="hero-stat">
    <span class="hero-stat-label">Bestellungen · <?= $days ?> Tage</span>
    <span class="hero-stat-num"><?= $ordPeriod ?></span>
    <span class="hero-stat-sub">Ø <?= $avgItems ?> Artikel pro Bestellung</span>
  </div>
  <div class="hero-stat">
    <span class="hero-stat-label">Ø Bestellwert · <?= $days ?> Tage</span>
    <span class="hero-stat-num"><?= format_price($avgPeriod, $currency) ?></span>
    <span class="hero-stat-sub"><?= count($emailCounts) ?> Kunden gesamt</span>
  </div>
</div>

<!-- Sekundäre KPIs (symmetrisch, 4er-Raster) -->
<div class="stat-grid stat-grid-4">
  <div class="stat-card">
    <span class="stat-num"><?= $accounts ?></span>
    <span class="stat-label">Registrierte Konten</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= format_price($totalValue, $currency) ?></span>
    <span class="stat-label">Gesamtlagerwert</span>
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
    <h2>Umsatz nach Wochentag</h2>
    <?php $wdNames = [1=>'Mo',2=>'Di',3=>'Mi',4=>'Do',5=>'Fr',6=>'Sa',7=>'So']; ?>
    <svg class="bar-chart bar-chart-wide" viewBox="0 0 360 200" role="img" aria-label="Umsatz nach Wochentag">
      <line x1="20" y1="170" x2="340" y2="170" stroke="rgba(255,255,255,.08)"/>
      <?php $gw = (340 - 20) / 7; $bwd = $gw * 0.55; foreach ($wdNames as $i => $nm):
        $val = $weekday[$i]; $hgt = max(round($val / $maxWd * 140), 2);
        $x = 20 + ($i - 1) * $gw + ($gw - $bwd) / 2; $y = 170 - $hgt; ?>
        <g><title><?= h($nm . ': CHF ' . number_format($val / 100, 2)) ?></title>
          <rect x="<?= round($x,1) ?>" y="<?= round($y,1) ?>" width="<?= round($bwd,1) ?>" height="<?= $hgt ?>" rx="3" class="bar"/>
          <text x="<?= round($x + $bwd/2,1) ?>" y="188" text-anchor="middle" font-size="10" fill="#55545e"><?= $nm ?></text>
        </g>
      <?php endforeach; ?>
    </svg>
  </div>
</div>

<div class="admin-2col">
  <!-- Zahlungsarten (Donut) -->
  <div class="admin-section">
    <h2>Zahlungsarten (<?= $days ?> Tage)</h2>
    <?php if (empty($payDist)): ?>
      <p class="muted">Keine Bestellungen im Zeitraum.</p>
    <?php else:
      $i = 0; $segs = []; $legend = [];
      foreach ($payDist as $pm => $cnt) { $c = $donutColors[$i % count($donutColors)]; $segs[] = ['label'=>$payLabels[$pm] ?? $pm,'value'=>$cnt,'color'=>$c]; $legend[] = [$payLabels[$pm] ?? $pm, $cnt, $c]; $i++; }
    ?>
      <div class="donut-row">
        <?= chart_donut($segs, (string)array_sum($payDist), 'Bestellungen') ?>
        <ul class="legend">
          <?php foreach ($legend as [$lab, $cnt, $c]): ?>
            <li><span class="legend-dot" style="background:<?= h($c) ?>"></span><?= h($lab) ?> <strong><?= $cnt ?></strong></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <!-- Bestellstatus (Donut) -->
  <div class="admin-section">
    <h2>Bestellstatus (<?= $days ?> Tage)</h2>
    <?php if (empty($statusDist)): ?>
      <p class="muted">Keine Bestellungen im Zeitraum.</p>
    <?php else:
      $i = 0; $segs = []; $legend = [];
      foreach ($statusDist as $st => $cnt) { $c = $donutColors[$i % count($donutColors)]; $lab = $statusLabels[$st] ?? ucfirst($st); $segs[] = ['label'=>$lab,'value'=>$cnt,'color'=>$c]; $legend[] = [$lab, $cnt, $c]; $i++; }
    ?>
      <div class="donut-row">
        <?= chart_donut($segs, (string)array_sum($statusDist), 'Bestellungen') ?>
        <ul class="legend">
          <?php foreach ($legend as [$lab, $cnt, $c]): ?>
            <li><span class="legend-dot" style="background:<?= h($c) ?>"></span><?= h($lab) ?> <strong><?= $cnt ?></strong></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="admin-2col">
  <!-- Umsatz nach Kategorie -->
  <div class="admin-section">
    <h2>Umsatz nach Kategorie (<?= $days ?> Tage)</h2>
    <?php if (empty($catRevenue)): ?>
      <p class="muted">Keine Verkäufe im Zeitraum.</p>
    <?php else: ?>
      <div class="hbar-list">
        <?php foreach (array_slice($catRevenue, 0, 6, true) as $cat => $rev): ?>
        <div class="hbar-row">
          <div class="hbar-meta"><span><?= h($cat) ?></span><strong><?= format_price($rev, $currency) ?></strong></div>
          <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($rev / $catTotal * 100, 1) ?>%"></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Neu- vs. Stammkunden -->
  <div class="admin-section">
    <h2>Kundenbindung (gesamt)</h2>
    <?php if (empty($emailCounts)): ?>
      <p class="muted">Noch keine Kunden.</p>
    <?php else: ?>
      <div class="donut-row">
        <?= chart_donut([
          ['label' => 'Stammkunden', 'value' => $returning, 'color' => '#b89c67'],
          ['label' => 'Einmalig',    'value' => $oneTime,   'color' => '#6ee7b7'],
        ], (string)count($emailCounts), 'Kunden') ?>
        <ul class="legend">
          <li><span class="legend-dot" style="background:#b89c67"></span>Stammkunden <strong><?= $returning ?></strong></li>
          <li><span class="legend-dot" style="background:#6ee7b7"></span>Einmalig <strong><?= $oneTime ?></strong></li>
        </ul>
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
        <div class="hbar-meta"><span><?= h($tp['name']) ?></span><strong><?= (int)$tp['qty'] ?>× · <?= format_price((int)$tp['revenue'], $currency) ?></strong></div>
        <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($tp['qty'] / $maxQty * 100, 1) ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ===================== Besucher & IP-Adressen ===================== -->
<?php
$ipSummary    = visits_ip_summary(80);
$recentVisits = visits_recent(5);
$myIp         = client_ip();
$ipUsers      = ip_user_map(array_merge(array_column($ipSummary, 'ip'), array_column($recentVisits, 'ip')));
$ipUserCell   = function (string $ip) use ($ipUsers): string {
    $u = $ipUsers[$ip] ?? null;
    if (!$u) return '<span class="muted">unbekannt</span>';
    $name = trim((string)($u['name'] ?? ''));
    return ($name !== '' ? '<strong>' . h($name) . '</strong> ' : '') . '<span class="muted">' . h($u['email'] ?? '') . '</span>';
};

function vis_when(string $ts): string {
    $t = strtotime($ts . ' UTC');
    if (!$t) return $ts;
    $diff = time() - $t;
    if ($diff < 60)    return 'gerade eben';
    if ($diff < 3600)  return floor($diff / 60) . ' Min.';
    if ($diff < 86400) return floor($diff / 3600) . ' Std.';
    return date('d.m. H:i', $t);
}
?>

<div class="admin-head-row" style="margin:2.4rem 0 1.1rem"><h2 style="margin:0">Besucher &amp; IP-Adressen</h2></div>

<div class="stat-grid stat-grid-4" style="margin-bottom:1.2rem">
  <div class="stat-card"><span class="stat-num"><?= count($ipSummary) ?></span><span class="stat-label">Eindeutige IPs (Log)</span></div>
  <div class="stat-card"><span class="stat-num"><?= (int)db()->query("SELECT COUNT(*) n FROM visits WHERE created_at >= datetime('now','-1 day')")->fetch()['n'] ?></span><span class="stat-label">Aufrufe · 24 h</span></div>
  <div class="stat-card"><span class="stat-num"><?= (int)db()->query("SELECT COUNT(*) n FROM visits")->fetch()['n'] ?></span><span class="stat-label">Aufrufe gesamt</span></div>
  <div class="stat-card"><span class="stat-num" style="font-size:1rem;word-break:break-all"><?= h($myIp) ?></span><span class="stat-label">Deine IP</span></div>
</div>

<!-- IP-Übersicht (read-only; Sperren unter Sicherheit) -->
<div class="admin-section">
  <h2>Besucher nach IP</h2>
  <?php if (empty($ipSummary)): ?>
    <p class="muted">Noch keine erfassten Besuche.</p>
  <?php else: ?>
  <p style="font-size:.82rem;color:#8a8a95;margin:0 0 1rem">IP-Adressen sperren oder freischalten kannst du unter <a href="<?= url('/admin/sicherheit.php') ?>" style="color:#7e8bb8">Sicherheit</a>.</p>
  <input type="search" class="admin-search" data-table-filter placeholder="IP filtern…" aria-label="IP filtern">
  <div class="table-card">
  <table class="data-table" data-filter-table>
    <thead><tr><th>IP-Adresse</th><th>Nutzer</th><th>Aufrufe</th><th>Zuletzt</th><th>Letzte Seite</th></tr></thead>
    <tbody>
      <?php foreach ($ipSummary as $v): ?>
      <tr>
        <td data-label="IP-Adresse"><strong style="font-variant-numeric:tabular-nums"><?= h($v['ip']) ?></strong><?= $v['ip'] === $myIp ? ' <span class="tag tag-new">du</span>' : '' ?></td>
        <td data-label="Nutzer"><?= $ipUserCell($v['ip']) ?></td>
        <td data-label="Aufrufe"><?= (int)$v['hits'] ?></td>
        <td data-label="Zuletzt" class="muted"><?= h(vis_when($v['last_seen'])) ?></td>
        <td data-label="Letzte Seite" class="muted" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($v['last_path']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- Letzte Seitenaufrufe (5) -->
<div class="admin-section">
  <h2>Letzte Seitenaufrufe</h2>
  <?php if (empty($recentVisits)): ?>
    <p class="muted">Noch keine Seitenaufrufe erfasst.</p>
  <?php else: ?>
  <div class="table-card">
  <table class="data-table">
    <thead><tr><th>Zeit</th><th>IP</th><th>Nutzer</th><th>Seite</th></tr></thead>
    <tbody>
      <?php foreach ($recentVisits as $v): ?>
      <tr>
        <td data-label="Zeit" class="muted" style="white-space:nowrap"><?= h(vis_when($v['created_at'])) ?></td>
        <td data-label="IP" style="font-variant-numeric:tabular-nums"><?= h($v['ip']) ?></td>
        <td data-label="Nutzer"><?= $ipUserCell($v['ip']) ?></td>
        <td data-label="Seite"><?= h($v['path']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
