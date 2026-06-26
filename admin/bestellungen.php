<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $ref = trim($_POST['ref'] ?? '');
    if ($ref) order_delete($ref);
    redirect('/admin/bestellungen.php');
}

// Zahlungsstatus pro Bestellung umstellen (offen <-> bezahlt)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_payment') {
    $ref = trim($_POST['ref'] ?? '');
    $ps  = ($_POST['payment_status'] ?? '') === 'bezahlt' ? 'bezahlt' : 'offen';
    if ($ref) {
        $o = order_by_ref($ref);
        if ($o) {
            // Beim Bezahlen eine noch "neue" Bestellung in Bearbeitung nehmen.
            $newStatus = ($ps === 'bezahlt' && $o['status'] === 'neu') ? 'in_bearbeitung' : $o['status'];
            order_update_status($ref, $newStatus, $ps);
        }
    }
    $back = trim($_POST['filter'] ?? '');
    redirect('/admin/bestellungen.php' . ($back ? '?filter=' . urlencode($back) : ''));
}

// NEU-Markierung entfernen (als gelesen markieren) – AJAX oder Fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_seen') {
    $ref = trim($_POST['ref'] ?? '');
    if ($ref) order_mark_seen($ref);
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_has($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if ($isAjax) json_response(['ok' => true]);
    redirect('/admin/bestellungen.php');
}

$orders   = orders_list();
$currency = setting_get('currency') ?: 'CHF';

// Filter
$filter = $_GET['filter'] ?? '';
if ($filter === 'offen')     $orders = array_values(array_filter($orders, fn($o) => $o['payment_status'] !== 'bezahlt' && $o['status'] !== 'storniert'));
if ($filter === 'bezahlt')   $orders = array_values(array_filter($orders, fn($o) => $o['payment_status'] === 'bezahlt'));
if ($filter === 'neu')       $orders = array_values(array_filter($orders, fn($o) => empty($o['is_seen'])));

// CSV-Export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bestellungen-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Referenz', 'Datum', 'Kunde', 'E-Mail', 'Telefon', 'Artikel', 'Zwischensumme', 'Rabatt', 'Versand', 'Gesamt', 'Zahlung', 'Status', 'Zahlungsart', 'Adresse'], ';');
    foreach ($orders as $o) {
        $addr = is_array($o['address']) ? $o['address'] : [];
        $addrStr = trim(($addr['street'] ?? '') . ' ' . ($addr['housenr'] ?? '') . ', ' . ($addr['zip'] ?? '') . ' ' . ($addr['city'] ?? '') . ', ' . ($addr['country'] ?? ''), ' ,');
        $itemsStr = implode(' | ', array_map(fn($it) => ($it['name'] ?? '') . (!empty($it['size']) ? ' (' . $it['size'] . ')' : '') . ' ×' . ($it['qty'] ?? 1), $o['items']));
        $sub = (int)$o['total_cents'] - (int)$o['shipping_cents'] + (int)($o['discount_cents'] ?? 0);
        fputcsv($out, [
            $o['reference'], substr($o['created_at'], 0, 16), $o['customer_name'], $o['email'], $o['phone'],
            $itemsStr,
            number_format($sub / 100, 2, '.', ''),
            number_format((int)($o['discount_cents'] ?? 0) / 100, 2, '.', ''),
            number_format((int)$o['shipping_cents'] / 100, 2, '.', ''),
            number_format((int)$o['total_cents'] / 100, 2, '.', ''),
            $o['payment_status'], $o['status'], $o['payment_method'], $addrStr,
        ], ';');
    }
    fclose($out);
    exit;
}

$adminTitle = 'Bestellungen';
include __DIR__ . '/partials/admin-layout-top.php';
?>
<p class="admin-kicker">Übersicht</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Bestellungen (<?= count($orders) ?>)</h1>
  <a class="btn btn-ghost btn-sm" href="<?= url('/admin/bestellungen.php?export=csv' . ($filter ? '&filter=' . urlencode($filter) : '')) ?>">CSV exportieren</a>
</div>

<div class="chip-row" style="margin-bottom:1.4rem">
  <a class="chip<?= $filter === '' ? ' active' : '' ?>" href="<?= url('/admin/bestellungen.php') ?>">Alle</a>
  <a class="chip<?= $filter === 'neu' ? ' active' : '' ?>" href="<?= url('/admin/bestellungen.php?filter=neu') ?>">Neu</a>
  <a class="chip<?= $filter === 'offen' ? ' active' : '' ?>" href="<?= url('/admin/bestellungen.php?filter=offen') ?>">Offen</a>
  <a class="chip<?= $filter === 'bezahlt' ? ' active' : '' ?>" href="<?= url('/admin/bestellungen.php?filter=bezahlt') ?>">Bezahlt</a>
</div>

<?php if (empty($orders)): ?>
  <p class="muted">Keine Bestellungen in dieser Ansicht.</p>
<?php else: ?>
<input type="search" class="admin-search" data-table-filter placeholder="Bestellungen filtern… (Referenz, Kunde)" aria-label="Bestellungen filtern">
<div class="table-card">
<table class="data-table" data-filter-table>
  <thead><tr><th>Referenz</th><th>Kunde</th><th>Datum</th><th>Betrag</th><th>Status</th><th>Zahlung</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($orders as $o):
      $isNew = empty($o['is_seen']);
    ?>
    <tr class="<?= $isNew ? 'order-row-new' : '' ?>" data-order-row>
      <td>
        <div class="order-ref-cell">
          <?php if ($isNew): ?>
          <button type="button" class="badge-neu" data-mark-seen data-ref="<?= h($o['reference']) ?>" title="Als gelesen markieren – klicken zum Entfernen" aria-label="Neue Bestellung – als gelesen markieren">neu</button>
          <?php endif; ?>
          <a href="<?= url('/admin/bestellung.php?ref=' . urlencode($o['reference'])) ?>"><?= h($o['reference']) ?></a>
        </div>
      </td>
      <td><?= h($o['customer_name']) ?></td>
      <td><?= h(substr($o['created_at'], 0, 16)) ?></td>
      <td><?= format_price((int)$o['total_cents'], $currency) ?></td>
      <td><span class="tag"><?= h($o['status']) ?></span></td>
      <td><span class="tag <?= payment_status_class($o['payment_status']) ?>"><?= h(payment_status_label($o['payment_status'])) ?></span></td>
      <td style="white-space:nowrap;display:flex;gap:.4rem;align-items:center">
        <form method="post" action="<?= url('/admin/bestellungen.php') ?>">
          <input type="hidden" name="action" value="set_payment">
          <input type="hidden" name="ref" value="<?= h($o['reference']) ?>">
          <input type="hidden" name="filter" value="<?= h($filter) ?>">
          <?php if ($o['payment_status'] === 'bezahlt'): ?>
            <input type="hidden" name="payment_status" value="offen">
            <button class="btn btn-ghost btn-sm" type="submit" title="Auf „Zahlung ausstehend" zurücksetzen">Als offen</button>
          <?php else: ?>
            <input type="hidden" name="payment_status" value="bezahlt">
            <button class="btn btn-sm" type="submit" style="background:#1f7a4d;border-color:#1f7a4d;color:#fff" title="Als bezahlt markieren">Als bezahlt</button>
          <?php endif; ?>
        </form>
        <a href="<?= url('/admin/bestellung.php?ref=' . urlencode($o['reference'])) ?>" class="btn btn-ghost btn-sm">Details</a>
        <form method="post" action="<?= url('/admin/bestellungen.php') ?>" onsubmit="return confirm('Bestellung <?= h($o['reference']) ?> wirklich löschen?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="ref" value="<?= h($o['reference']) ?>">
          <button class="btn btn-danger btn-sm" type="submit">Löschen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
