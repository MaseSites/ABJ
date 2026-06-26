<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$ref   = trim($_GET['ref'] ?? '');
$order = $ref ? order_by_ref($ref) : null;
if (!$order) redirect('/admin/bestellungen.php');

try { order_mark_seen($ref); } catch (Throwable $e) { /* column may not exist yet */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    order_update_status($ref, trim($_POST['status'] ?? 'neu'), trim($_POST['payment_status'] ?? 'offen'));
    redirect('/admin/bestellung.php?ref=' . urlencode($ref) . '&saved=1');
}

$adminTitle = 'Bestellung ' . $ref;
include __DIR__ . '/partials/admin-layout-top.php';
$currency = setting_get('currency') ?: 'CHF';
$addr     = is_array($order['address']) ? $order['address'] : ['raw' => $order['address']];
?>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Bestellung <?= h($ref) ?></h1>
  <a href="<?= url('/admin/bestellungen.php') ?>" class="btn btn-ghost">← Zurück</a>
</div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>

<div class="admin-form" style="max-width:700px">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:2rem">
    <div>
      <h2 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.1em;opacity:.5;margin-bottom:.5rem">Kunde</h2>
      <p><?= h($order['customer_name']) ?><br><?= h($order['email']) ?><?= $order['phone'] ? '<br>' . h($order['phone']) : '' ?></p>
    </div>
    <div>
      <h2 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.1em;opacity:.5;margin-bottom:.5rem">Lieferadresse</h2>
      <?php if (is_array($addr)): ?>
        <p><?= h(($addr['firstname'] ?? '') . ' ' . ($addr['lastname'] ?? '')) ?><br>
           <?= h(($addr['street'] ?? '') . ' ' . ($addr['housenr'] ?? '')) ?><br>
           <?= h(($addr['zip'] ?? '') . ' ' . ($addr['city'] ?? '')) ?><br>
           <?= h($addr['country'] ?? '') ?></p>
      <?php else: ?>
        <p><?= h($addr['raw'] ?? '') ?></p>
      <?php endif; ?>
    </div>
  </div>

  <h2 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.1em;opacity:.5;margin-bottom:.5rem">Artikel</h2>
  <table class="data-table" style="margin-bottom:1.5rem">
    <thead><tr><th>Produkt</th><th>Variante</th><th>Menge</th><th>Preis</th></tr></thead>
    <tbody>
      <?php foreach ($order['items'] as $item): ?>
      <tr>
        <td><?= h($item['name']) ?></td>
        <td><?= h($item['size'] ?? '—') ?></td>
        <td><?= (int)$item['qty'] ?></td>
        <td><?= format_price((int)$item['lineCents'], $currency) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p><strong>Versand:</strong> <?= format_price((int)$order['shipping_cents'], $currency) ?></p>
  <p><strong>Gesamt:</strong> <?= format_price((int)$order['total_cents'], $currency) ?></p>
  <p><strong>Zahlung:</strong> <span class="tag <?= payment_status_class($order['payment_status']) ?>"><?= h(payment_status_label($order['payment_status'])) ?></span></p>

  <form method="post" style="margin-top:1.5rem;display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
    <label class="field"><span>Status</span>
      <select name="status">
        <?php foreach (['neu','in_bearbeitung','versendet','abgeschlossen','storniert'] as $s): ?>
          <option value="<?= $s ?>"<?= $order['status'] === $s ? ' selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field"><span>Zahlungsstatus</span>
      <select name="payment_status">
        <option value="offen"<?= $order['payment_status'] !== 'bezahlt' ? ' selected' : '' ?>>Zahlung ausstehend</option>
        <option value="bezahlt"<?= $order['payment_status'] === 'bezahlt' ? ' selected' : '' ?>>Bezahlt</option>
      </select>
    </label>
    <button class="btn btn-primary" type="submit">Speichern</button>
  </form>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
