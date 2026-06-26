<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$currency = setting_get('currency') ?: 'CHF';

// Registrierte Konten + Bestellstatistik (per E-Mail) zusammenführen.
$accounts   = accounts_list();
$orderStats = customers_list();
$byEmail    = [];
foreach ($orderStats as $c) $byEmail[strtolower(trim($c['email']))] = $c;

$rows = [];
foreach ($accounts as $a) {
    $key  = strtolower(trim($a['email']));
    $stat = $byEmail[$key] ?? null;
    $rows[] = [
        'name'          => $a['name'] ?: ($stat['customer_name'] ?? ''),
        'email'         => $a['email'],
        'phone'         => $stat['phone'] ?? '',
        'order_count'   => (int)($stat['order_count'] ?? 0),
        'revenue_cents' => (int)($stat['revenue_cents'] ?? 0),
        'last_order_at' => $stat['last_order_at'] ?? '',
        'created_at'    => $a['created_at'] ?? '',
        'registered'    => true,
    ];
    unset($byEmail[$key]);
}
// Übrige Besteller ohne Konto (z.B. alte Bestellungen) als "Gast" anhängen.
foreach ($byEmail as $stat) {
    $rows[] = [
        'name'          => $stat['customer_name'],
        'email'         => $stat['email'],
        'phone'         => $stat['phone'],
        'order_count'   => (int)$stat['order_count'],
        'revenue_cents' => (int)$stat['revenue_cents'],
        'last_order_at' => $stat['last_order_at'],
        'created_at'    => '',
        'registered'    => false,
    ];
}
// Sortierung: zuletzt aktiv (Bestellung oder Registrierung) zuerst.
usort($rows, fn($a, $b) => strcmp(
    max($b['last_order_at'], $b['created_at']),
    max($a['last_order_at'], $a['created_at'])
));

$registeredCount = count($accounts);

// CSV-Export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kunden-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'E-Mail', 'Telefon', 'Konto', 'Bestellungen', 'Umsatz (bezahlt)', 'Konto seit', 'Letzte Bestellung'], ';');
    foreach ($rows as $c) {
        fputcsv($out, [
            $c['name'], $c['email'], $c['phone'],
            $c['registered'] ? 'ja' : 'nein',
            $c['order_count'],
            number_format(($c['revenue_cents'] ?? 0) / 100, 2, '.', ''),
            substr($c['created_at'] ?? '', 0, 16),
            substr($c['last_order_at'] ?? '', 0, 16),
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
  <h1>Kunden (<?= count($rows) ?>)</h1>
  <a class="btn btn-ghost btn-sm" href="<?= url('/admin/kunden.php?export=csv') ?>">CSV exportieren</a>
</div>

<div class="stat-grid" style="margin-bottom:1.6rem">
  <div class="stat-card">
    <span class="stat-num"><?= $registeredCount ?></span>
    <span class="stat-label">Registrierte Konten</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= count(array_filter($rows, fn($r) => $r['order_count'] > 0)) ?></span>
    <span class="stat-label">Mit Bestellung</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= format_price(array_sum(array_column($rows, 'revenue_cents')), $currency) ?></span>
    <span class="stat-label">Umsatz (bezahlt)</span>
  </div>
</div>

<?php if (empty($rows)): ?>
  <p class="muted">Noch keine Kunden — sobald sich jemand registriert oder bestellt, erscheint er hier.</p>
<?php else: ?>
<input type="search" class="admin-search" data-table-filter placeholder="Kunden filtern… (Name, E-Mail)" aria-label="Kunden filtern">
<table class="data-table" data-filter-table>
  <thead><tr><th>Kunde</th><th>E-Mail</th><th>Konto seit</th><th>Bestellungen</th><th>Umsatz (bezahlt)</th><th>Letzte Bestellung</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $c): ?>
    <tr>
      <td>
        <strong><?= h($c['name'] ?: '—') ?></strong>
        <?php if (!$c['registered']): ?><span class="tag tag-off" style="margin-left:.4rem">Gast</span><?php endif; ?>
        <?php if ($c['phone']): ?><br><span class="muted" style="font-size:.78rem"><?= h($c['phone']) ?></span><?php endif; ?>
      </td>
      <td><a href="mailto:<?= h($c['email']) ?>" style="color:#b89c67"><?= h($c['email']) ?></a></td>
      <td class="muted" style="font-size:.82rem"><?= h(substr($c['created_at'] ?? '', 0, 10) ?: '—') ?></td>
      <td><?= (int)$c['order_count'] ?></td>
      <td style="font-weight:700"><?= format_price((int)($c['revenue_cents'] ?? 0), $currency) ?></td>
      <td class="muted" style="font-size:.82rem"><?= h(substr($c['last_order_at'] ?? '', 0, 16) ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
