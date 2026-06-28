<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$ref   = trim($_GET['ref'] ?? '');
$order = $ref ? order_by_ref($ref) : null;
if (!$order) redirect('/admin/bestellungen.php');

try { order_mark_seen($ref); } catch (Throwable $e) { /* column may not exist yet */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'set_price') {
        $prod = (int)round((float)str_replace(',', '.', trim($_POST['total'] ?? '')) * 100);
        $ship = (int)round((float)str_replace(',', '.', trim($_POST['shipping'] ?? '')) * 100);
        order_set_price($ref, max(0, $prod), max(0, $ship));
    } else {
        order_update_status($ref, trim($_POST['status'] ?? 'neu'), trim($_POST['payment_status'] ?? 'offen'));
    }
    redirect('/admin/bestellung.php?ref=' . urlencode($ref) . '&saved=1');
}

$adminTitle = 'Bestellung ' . $ref;
include __DIR__ . '/partials/admin-layout-top.php';
$currency  = setting_get('currency') ?: 'CHF';
$addr      = is_array($order['address']) ? $order['address'] : ['raw' => $order['address']];
$isRequest = order_is_request($order);
?>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Bestellung <?= h($ref) ?> <?php if ($isRequest): ?><span class="tag tag-new" style="vertical-align:middle">Produktanfrage</span><?php endif; ?></h1>
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

  <h2 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.1em;opacity:.5;margin-bottom:.5rem"><?= $isRequest ? 'Anfrage' : 'Artikel' ?></h2>
  <table class="data-table" style="margin-bottom:1.5rem">
    <thead><tr><th></th><th>Produkt</th><th>Variante</th><th>Menge</th><th>Preis</th></tr></thead>
    <tbody>
      <?php foreach ($order['items'] as $item): ?>
      <tr>
        <td style="width:64px">
          <?php if (!empty($item['image'])): ?>
            <a href="<?= h($item['image']) ?>" target="_blank" rel="noopener"><img src="<?= h($item['image']) ?>" alt="" style="width:54px;height:54px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,.1)"></a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><?= h($item['name']) ?></td>
        <td><?= h($item['size'] ?: '—') ?></td>
        <td><?= (int)$item['qty'] ?></td>
        <td><?= (int)$item['lineCents'] > 0 ? format_price((int)$item['lineCents'], $currency) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($isRequest): ?>
  <div class="admin-section" style="margin-bottom:1.5rem;border-color:rgba(99,102,241,.3)">
    <h2 style="margin-top:0">Preis festlegen</h2>
    <p class="muted" style="font-size:.84rem;margin-top:-.5rem">Sobald du den Preis setzt, sieht ihn der Kunde im Profil unter „Meine Bestellungen".</p>
    <?php
      $curProduct = 0;
      foreach ($order['items'] as $it) $curProduct += (int)($it['lineCents'] ?? 0);
      $defaultShip = (int)$order['shipping_cents'] > 0 ? (int)$order['shipping_cents'] : (int)(setting_get('shipping_ch_cents') ?: 590);
    ?>
    <form method="post" style="display:flex;gap:.8rem;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="action" value="set_price">
      <label class="field" style="max-width:160px"><span>Produktpreis (<?= h($currency) ?>)</span>
        <input type="text" inputmode="decimal" name="total" value="<?= $curProduct > 0 ? number_format($curProduct / 100, 2, '.', '') : '' ?>" placeholder="z.B. 129.00">
      </label>
      <label class="field" style="max-width:160px"><span>Versand (<?= h($currency) ?>)</span>
        <input type="text" inputmode="decimal" name="shipping" value="<?= number_format($defaultShip / 100, 2, '.', '') ?>" placeholder="z.B. 5.90">
      </label>
      <button class="btn btn-primary" type="submit">Preis speichern</button>
    </form>
  </div>
  <?php endif; ?>

  <p><strong>Versand:</strong> <?= format_price((int)$order['shipping_cents'], $currency) ?></p>
  <p><strong>Gesamt:</strong> <?= (int)$order['total_cents'] > 0 ? format_price((int)$order['total_cents'], $currency) : '<span class="muted">noch offen</span>' ?></p>
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
