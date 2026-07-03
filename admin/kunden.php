<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$currency = setting_get('currency') ?: 'CHF';

// Konto löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_account') {
    require_cap('customers.manage');
    $aid = (int)($_POST['id'] ?? 0);
    if ($aid) account_delete($aid);
    redirect('/admin/kunden.php?deleted=1');
}

// Konto aktivieren (freischalten)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'activate_account') {
    require_cap('customers.manage');
    $aid = (int)($_POST['id'] ?? 0);
    if ($aid) account_activate($aid);
    redirect('/admin/kunden.php?activated=1');
}

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
        'id'            => (int)$a['id'],
        'name'          => $a['name'] ?: ($stat['customer_name'] ?? ''),
        'email'         => $a['email'],
        'phone'         => $stat['phone'] ?? '',
        'order_count'   => (int)($stat['order_count'] ?? 0),
        'revenue_cents' => (int)($stat['revenue_cents'] ?? 0),
        'last_order_at' => $stat['last_order_at'] ?? '',
        'created_at'    => $a['created_at'] ?? '',
        'registered'    => true,
        'activated'     => account_is_activated($a),
    ];
    unset($byEmail[$key]);
}
foreach ($byEmail as $stat) {
    $rows[] = [
        'id'            => 0,
        'name'          => $stat['customer_name'],
        'email'         => $stat['email'],
        'phone'         => $stat['phone'],
        'order_count'   => (int)$stat['order_count'],
        'revenue_cents' => (int)$stat['revenue_cents'],
        'last_order_at' => $stat['last_order_at'],
        'created_at'    => '',
        'registered'    => false,
        'activated'     => true,
    ];
}
usort($rows, fn($a, $b) => strcmp(
    max($b['last_order_at'], $b['created_at']),
    max($a['last_order_at'], $a['created_at'])
));

$registeredCount = count($accounts);
$withOrders      = count(array_filter($rows, fn($r) => $r['order_count'] > 0));
$totalRevenue    = array_sum(array_column($rows, 'revenue_cents'));

// CSV-Export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kunden-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'E-Mail', 'Telefon', 'Konto', 'Bestellungen', 'Umsatz (bezahlt)', 'Konto seit', 'Letzte Bestellung'], ';');
    foreach ($rows as $c) {
        fputcsv($out, [
            $c['name'], $c['email'], $c['phone'], $c['registered'] ? 'ja' : 'nein',
            $c['order_count'], number_format(($c['revenue_cents'] ?? 0) / 100, 2, '.', ''),
            substr($c['created_at'] ?? '', 0, 16), substr($c['last_order_at'] ?? '', 0, 16),
        ], ';');
    }
    fclose($out);
    exit;
}

function cust_initials(string $name, string $email): string {
    $name = trim($name);
    if ($name !== '') {
        $parts = preg_split('/\s+/', $name);
        $ini = mb_strtoupper(mb_substr($parts[0], 0, 1));
        if (count($parts) > 1) $ini .= mb_strtoupper(mb_substr(end($parts), 0, 1));
        return $ini;
    }
    return mb_strtoupper(mb_substr($email, 0, 1));
}

$adminTitle = 'Kunden';
include __DIR__ . '/partials/admin-layout-top.php';
?>
<p class="admin-kicker">Übersicht</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Kunden</h1>
  <a class="btn btn-ghost btn-sm" href="<?= url('/admin/kunden.php?export=csv') ?>">CSV exportieren</a>
</div>

<?php if (!empty($_GET['deleted'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Konto gelöscht.</div><?php endif; ?>
<?php if (!empty($_GET['activated'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Konto aktiviert. Zurückgehaltene Bestellungen wurden freigegeben.</div><?php endif; ?>

<div class="stat-grid" style="margin-bottom:1.6rem">
  <div class="stat-card stat-highlight"><span class="stat-num"><?= $registeredCount ?></span><span class="stat-label">Registrierte Konten</span></div>
  <div class="stat-card"><span class="stat-num"><?= $withOrders ?></span><span class="stat-label">Mit Bestellung</span></div>
  <div class="stat-card"><span class="stat-num"><?= count($rows) ?></span><span class="stat-label">Kunden gesamt</span></div>
  <div class="stat-card"><span class="stat-num"><?= format_price($totalRevenue, $currency) ?></span><span class="stat-label">Umsatz (bezahlt)</span></div>
</div>

<?php if (empty($rows)): ?>
  <div class="cust-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" width="48" height="48"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c.6-3.6 3.2-5.4 6.5-5.4s5.9 1.8 6.5 5.4"/><path d="M16 8.2a3 3 0 010 5.4M18.5 14.5c1.7.8 2.9 2.4 3.2 4.8"/></svg>
    <p>Noch keine Kunden - sobald sich jemand registriert oder bestellt, erscheint er hier.</p>
  </div>
<?php else: ?>
<input type="search" class="admin-search" data-cust-filter placeholder="Kunden suchen... (Name, E-Mail)" aria-label="Kunden suchen" style="margin-bottom:1.1rem">

<div class="cust-list">
  <?php foreach ($rows as $c):
    $hue = (int)(hexdec(substr(md5(strtolower($c['email'])), 0, 4)) % 360);
  ?>
  <div class="cust-card" data-cust-card data-search="<?= h(strtolower($c['name'] . ' ' . $c['email'])) ?>">
    <div class="cust-avatar" style="background:linear-gradient(135deg,hsl(<?= $hue ?>,42%,32%),hsl(<?= ($hue + 40) % 360 ?>,38%,24%))">
      <?= h(cust_initials($c['name'], $c['email'])) ?>
    </div>
    <div class="cust-main">
      <div class="cust-name-row">
        <?php if ($c['registered']): ?>
          <a class="cust-name cust-name-link" href="<?= url('/admin/kunde.php?id=' . (int)$c['id']) ?>"><?= h($c['name'] ?: 'Unbenannt') ?></a>
          <span class="cust-badge cust-badge-acc">Konto</span>
          <?php if (empty($c['activated'])): ?><span class="tag tag-partial" style="font-size:.66rem">eingeschränkt</span><?php endif; ?>
        <?php else: ?>
          <span class="cust-name"><?= h($c['name'] ?: 'Unbenannt') ?></span>
          <span class="cust-badge cust-badge-guest">Gast</span>
        <?php endif; ?>
      </div>
      <a class="cust-email" href="mailto:<?= h($c['email']) ?>"><?= h($c['email']) ?></a>
      <?php if ($c['phone']): ?><span class="cust-phone"><?= h($c['phone']) ?></span><?php endif; ?>
    </div>
    <div class="cust-stats">
      <div class="cust-stat"><strong><?= (int)$c['order_count'] ?></strong><span>Bestellungen</span></div>
      <div class="cust-stat"><strong><?= format_price((int)$c['revenue_cents'], $currency) ?></strong><span>Umsatz</span></div>
      <div class="cust-stat"><strong><?= h($c['registered'] ? (substr($c['created_at'], 0, 10) ?: '-') : '-') ?></strong><span>Konto seit</span></div>
    </div>
    <div class="cust-actions">
      <?php if ($c['registered']): ?>
      <?php if (empty($c['activated'])): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="action" value="activate_account">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button class="btn btn-sm" type="submit" style="background:#e6b64a;border-color:#e6b64a;color:#241a04" title="Konto freischalten">Aktivieren</button>
      </form>
      <?php endif; ?>
      <a class="btn btn-ghost btn-sm" href="<?= url('/admin/kunde.php?id=' . (int)$c['id']) ?>">Bearbeiten</a>
      <form method="post" onsubmit="return confirm('Konto von <?= h($c['name'] ?: $c['email']) ?> wirklich löschen? Die Bestellhistorie bleibt erhalten.')">
        <input type="hidden" name="action" value="delete_account">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button class="cust-del" type="submit" title="Konto löschen" aria-label="Konto löschen">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6.5 4V2.8h3V4M5 4l.6 9h4.8L11 4"/></svg>
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<p class="cust-empty-filter muted" hidden style="text-align:center;padding:1.5rem">Keine Kunden gefunden.</p>

<script>
(function () {
  var input = document.querySelector('[data-cust-filter]');
  if (!input) return;
  var empty = document.querySelector('.cust-empty-filter');
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    var shown = 0;
    document.querySelectorAll('[data-cust-card]').forEach(function (el) {
      var match = el.getAttribute('data-search').indexOf(q) !== -1;
      el.style.display = match ? '' : 'none';
      if (match) shown++;
    });
    if (empty) empty.hidden = shown !== 0;
  });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
