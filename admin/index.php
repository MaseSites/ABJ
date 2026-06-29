<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$adminTitle = 'Dashboard';
include __DIR__ . '/partials/admin-layout-top.php';

$allOrders    = orders_list();
$recentOrders = array_slice($allOrders, 0, 6);
$currency     = setting_get('currency') ?: 'CHF';
$lowStock     = array_slice(inv_low_stock(), 0, 5);
$totalValue   = inv_total_value();
$openCount    = count(array_filter($allOrders, fn($o) => $o['payment_status'] !== 'bezahlt' && $o['status'] !== 'storniert'));
$newCount     = count(array_filter($allOrders, fn($o) => empty($o['is_seen'])));
$pendingPay   = count(array_filter($allOrders, fn($o) => $o['payment_status'] !== 'bezahlt' && $o['status'] !== 'storniert'));

$revTotal = 0; $rev30 = 0; $revPrev30 = 0; $orders30 = 0;
try {
    $revTotal  = (int)db()->query("SELECT COALESCE(SUM(total_cents),0) c FROM orders WHERE payment_status='bezahlt'")->fetch()['c'];
    $rev30     = (int)db()->query("SELECT COALESCE(SUM(total_cents),0) c FROM orders WHERE payment_status='bezahlt' AND created_at >= datetime('now','-30 days')")->fetch()['c'];
    $revPrev30 = (int)db()->query("SELECT COALESCE(SUM(total_cents),0) c FROM orders WHERE payment_status='bezahlt' AND created_at >= datetime('now','-60 days') AND created_at < datetime('now','-30 days')")->fetch()['c'];
    $orders30  = (int)db()->query("SELECT COUNT(*) n FROM orders WHERE created_at >= datetime('now','-30 days')")->fetch()['n'];
} catch (\Throwable $e) {}

$trend = $revPrev30 > 0 ? round(($rev30 - $revPrev30) / $revPrev30 * 100) : ($rev30 > 0 ? 100 : 0);
try { $customerCount = accounts_count(); } catch (\Throwable $e) { $customerCount = 0; }
try { $requestCount = (int)db()->query("SELECT COUNT(*) n FROM product_requests WHERE status='neu'")->fetch()['n']; } catch (\Throwable $e) { $requestCount = 0; }

$hour = (int)date('G');
$greeting = $hour < 11 ? 'Guten Morgen' : ($hour < 17 ? 'Guten Tag' : 'Guten Abend');
$adminName = $_SESSION['admin_username'] ?? 'admin';
$dateStr = (function () {
    $tage  = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    $monate = ['','Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
    return $tage[(int)date('w')] . ', ' . (int)date('j') . '. ' . $monate[(int)date('n')] . ' ' . date('Y');
})();

function statusTagClass(string $status): string {
    return $status === 'bezahlt' ? 'tag-ok' : 'tag-pending';
}
?>

<!-- Begrüßung -->
<div class="dash-hero">
  <div>
    <p class="admin-kicker">Dashboard</p>
    <h1><?= h($greeting) ?>, <?= h(ucfirst($adminName)) ?></h1>
    <p class="dash-date"><?= h($dateStr) ?></p>
  </div>
  <div class="dash-hero-actions">
    <a class="btn btn-primary" href="<?= url('/admin/produkt/neu') ?>">
      <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>
      Neues Produkt
    </a>
    <a class="btn" href="<?= url('/admin/analytics.php') ?>">Analytics</a>
  </div>
</div>

<!-- KPI-Karten -->
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon kpi-icon-gold">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 17l5-5 4 4 8-8"/><path d="M16 8h5v5"/></svg>
    </div>
    <div class="kpi-body">
      <span class="kpi-value"><?= format_price($rev30, $currency) ?></span>
      <span class="kpi-label">Umsatz · 30 Tage</span>
    </div>
    <?php if ($trend !== 0): ?>
      <span class="kpi-trend <?= $trend >= 0 ? 'up' : 'down' ?>"><?= $trend >= 0 ? '▲' : '▼' ?> <?= abs($trend) ?>%</span>
    <?php endif; ?>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon kpi-icon-green">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.3 9.2h3.6a1.7 1.7 0 010 3.4H9.3M9.3 12.6h3.9"/></svg>
    </div>
    <div class="kpi-body">
      <span class="kpi-value"><?= format_price($revTotal, $currency) ?></span>
      <span class="kpi-label">Gesamtumsatz (bezahlt)</span>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon kpi-icon-blue">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 4h2l2.4 12.4a2 2 0 002 1.6h7.7a2 2 0 002-1.5L21 8H6"/><circle cx="9" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg>
    </div>
    <div class="kpi-body">
      <span class="kpi-value"><?= $orders30 ?></span>
      <span class="kpi-label">Bestellungen · 30 Tage</span>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon kpi-icon-violet">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/></svg>
    </div>
    <div class="kpi-body">
      <span class="kpi-value"><?= format_price($totalValue, $currency) ?></span>
      <span class="kpi-label">Lagerwert</span>
    </div>
  </div>
</div>

<!-- Aktion erforderlich -->
<div class="todo-grid">
  <a class="todo-card <?= $newCount > 0 ? 'is-alert' : 'is-ok' ?>" href="<?= url('/admin/bestellungen.php?filter=neu') ?>">
    <span class="todo-num"><?= $newCount ?></span>
    <span class="todo-text"><strong>Neue Bestellungen</strong><small>Noch nicht angesehen</small></span>
    <svg class="todo-arrow" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3l5 5-5 5"/></svg>
  </a>
  <a class="todo-card <?= $pendingPay > 0 ? 'is-warn' : 'is-ok' ?>" href="<?= url('/admin/bestellungen.php?filter=offen') ?>">
    <span class="todo-num"><?= $pendingPay ?></span>
    <span class="todo-text"><strong>Offene Zahlungen</strong><small>Auf „Bezahlt" prüfen</small></span>
    <svg class="todo-arrow" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3l5 5-5 5"/></svg>
  </a>
  <a class="todo-card <?= count($lowStock) > 0 ? 'is-warn' : 'is-ok' ?>" href="<?= url('/admin/lager.php') ?>">
    <span class="todo-num"><?= count($lowStock) ?></span>
    <span class="todo-text"><strong>Niedriger Bestand</strong><small>Artikel nachbestellen</small></span>
    <svg class="todo-arrow" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3l5 5-5 5"/></svg>
  </a>
  <a class="todo-card is-neutral" href="<?= url('/admin/kunden.php') ?>">
    <span class="todo-num"><?= $customerCount ?></span>
    <span class="todo-text"><strong>Kundenkonten</strong><small>Registrierte Kunden</small></span>
    <svg class="todo-arrow" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3l5 5-5 5"/></svg>
  </a>
  <a class="todo-card <?= $requestCount > 0 ? 'is-warn' : 'is-ok' ?>" href="<?= url('/admin/anfragen.php') ?>">
    <span class="todo-num"><?= $requestCount ?></span>
    <span class="todo-text"><strong>Anfragen</strong><small>Produktanfragen prüfen</small></span>
    <svg class="todo-arrow" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3l5 5-5 5"/></svg>
  </a>
</div>

<!-- Zweispaltig: Bestellungen + Seitenleiste -->
<div class="dash-cols">
  <div class="admin-section">
    <div class="admin-head-row" style="margin-bottom:1.1rem">
      <h2 style="margin-bottom:0">Letzte Bestellungen</h2>
      <a class="btn btn-ghost btn-sm" href="<?= url('/admin/bestellungen.php') ?>">Alle ansehen</a>
    </div>
    <?php if (empty($recentOrders)): ?>
      <div class="dash-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="40" height="40"><path d="M3 4h2l2.4 12.4a2 2 0 002 1.6h7.7a2 2 0 002-1.5L21 8H6"/><circle cx="9" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg>
        <p>Noch keine Bestellungen.</p>
      </div>
    <?php else: ?>
    <div class="table-scroll">
      <table class="data-table">
        <thead><tr><th>Referenz</th><th>Kunde</th><th>Datum</th><th>Summe</th><th>Zahlung</th></tr></thead>
        <tbody>
          <?php foreach ($recentOrders as $o): ?>
          <tr class="<?= empty($o['is_seen']) ? 'order-row-new' : '' ?>">
            <td><a href="<?= url('/admin/bestellung.php?ref=' . urlencode($o['reference'])) ?>" style="color:#b89c67;font-weight:700"><?= h($o['reference']) ?></a></td>
            <td><strong><?= h($o['customer_name'] ?: '—') ?></strong></td>
            <td class="muted"><?= h(substr($o['created_at'], 0, 10)) ?></td>
            <td style="font-weight:700;color:#e0e2ea"><?= format_price($o['total_cents'], $currency) ?></td>
            <td><span class="tag <?= statusTagClass($o['payment_status']) ?>"><?= h(payment_status_label($o['payment_status'])) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="dash-side">
    <div class="admin-section" style="margin-bottom:1.2rem">
      <div class="admin-head-row" style="margin-bottom:1.1rem">
        <h2 style="margin-bottom:0">Lager-Warnungen</h2>
        <a class="btn btn-ghost btn-sm" href="<?= url('/admin/lager.php') ?>">Lager</a>
      </div>
      <?php if (empty($lowStock)): ?>
        <p class="dash-allgood">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3.5 3.5L13 4.5"/></svg>
          Alles gut — keine niedrigen Bestände.
        </p>
      <?php else: ?>
        <ul class="dash-low">
          <?php foreach ($lowStock as $ls): $avail = max(0, $ls['stock'] - $ls['reserved']); ?>
          <li>
            <a href="<?= url('/admin/lager-edit.php?id=' . (int)$ls['id']) ?>">
              <span class="dash-low-name"><?= h($ls['product_name']) ?><?= $ls['size'] ? ' · ' . h($ls['size']) : '' ?></span>
              <span class="tag <?= $avail <= 0 ? 'tag-off' : 'tag-warn' ?>"><?= $avail ?> Stk.</span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="admin-section">
      <h2 style="margin-bottom:1.1rem">Schnellzugriff</h2>
      <div class="dash-quick">
        <a href="<?= url('/admin/produkte.php') ?>">Produkte</a>
        <a href="<?= url('/admin/finanzen.php') ?>">Finanzen</a>
        <a href="<?= url('/admin/preisrechner.php') ?>">Preisrechner</a>
        <a href="<?= url('/admin/rabatte.php') ?>">Rabattcodes</a>
        <a href="<?= url('/admin/bewertungen.php') ?>">Bewertungen</a>
        <a href="<?= url('/admin/einstellungen.php') ?>">Einstellungen</a>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
