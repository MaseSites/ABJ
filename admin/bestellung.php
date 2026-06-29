<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$ref   = trim($_GET['ref'] ?? '');
$order = $ref ? order_by_ref($ref) : null;
if (!$order) redirect('/admin/bestellungen.php');

try { order_mark_seen($ref); } catch (Throwable $e) { /* column may not exist yet */ }
try { order_messages_mark_read($ref); } catch (Throwable $e) { /* table may not exist yet */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_cap('orders.manage');
    if (($_POST['action'] ?? '') === 'merge') {
        $source = trim($_POST['source_ref'] ?? '');
        if ($source) {
            order_merge($ref, $source);
        }
        redirect('/admin/bestellung.php?ref=' . urlencode($ref) . '&merged=1');
    }
    if (($_POST['action'] ?? '') === 'set_price') {
        $prod = (int)round((float)str_replace(',', '.', trim($_POST['total'] ?? '')) * 100);
        $ship = (int)round((float)str_replace(',', '.', trim($_POST['shipping'] ?? '')) * 100);
        order_set_price($ref, max(0, $prod), max(0, $ship));
        $acc = account_by_email($order['email'] ?? '');
        if ($acc) {
            account_message_create([
                'account_id' => (int)$acc['id'],
                'order_reference' => $ref,
                'sender_role' => 'system',
                'subject' => 'Preisänderung',
                'body' => 'Neuer Produktpreis: ' . number_format($prod / 100, 2, '.', '') . "\nVersand: " . number_format($ship / 100, 2, '.', ''),
                'is_read' => 0,
            ]);
        }
        order_message_create([
            'order_reference' => $ref,
            'author_role' => 'system',
            'author_name' => 'System',
            'subject' => 'Preisänderung',
            'body' => 'Neuer Produktpreis: ' . number_format($prod / 100, 2, '.', '') . "\nVersand: " . number_format($ship / 100, 2, '.', ''),
            'is_system' => 1,
            'is_read' => 0,
        ]);
    } else {
        $newStatus = trim($_POST['status'] ?? 'neu');
        $newPay = trim($_POST['payment_status'] ?? 'offen');
        order_update_status($ref, $newStatus, $newPay);
    }
    if (!empty($_POST['note'])) {
        $note = trim($_POST['note']);
        order_message_create([
            'order_reference' => $ref,
            'author_role' => 'admin',
            'author_name' => 'ABJ Team',
            'subject' => 'Bemerkung',
            'body' => $note,
            'is_system' => 0,
            'is_read' => 0,
        ]);
        $acc = account_by_email($order['email'] ?? '');
        if ($acc) {
            account_message_create([
                'account_id' => (int)$acc['id'],
                'order_reference' => $ref,
                'sender_role' => 'admin',
                'subject' => 'Nachricht zur Bestellung',
                'body' => $note,
                'is_read' => 0,
            ]);
        }
    }
    redirect('/admin/bestellung.php?ref=' . urlencode($ref) . '&saved=1');
}

$adminTitle = 'Bestellung ' . $ref;
include __DIR__ . '/partials/admin-layout-top.php';
$currency  = setting_get('currency') ?: 'CHF';
$addr      = is_array($order['address']) ? $order['address'] : ['raw' => $order['address']];
$isRequest = order_is_request($order);
$msgs      = order_messages_by_ref($ref);
$otherOrders = array_values(array_filter(orders_by_email($order['email'] ?? ''), fn($o) => $o['reference'] !== $ref));
?>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Bestellung <?= h($ref) ?> <?php if ($isRequest): ?><span class="tag tag-new" style="vertical-align:middle">Produktanfrage</span><?php endif; ?></h1>
  <a href="<?= url('/admin/bestellungen.php') ?>" class="btn btn-ghost">← Zurück</a>
</div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>
<?php if (!empty($_GET['merged'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Bestellungen wurden zusammengeführt.</div><?php endif; ?>

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

  <?php
    $curShip = (int)$order['shipping_cents'];
    if ($isRequest) {
        $curProduct = 0;
        foreach ($order['items'] as $it) $curProduct += (int)($it['lineCents'] ?? 0);
        $defaultShip = $curShip > 0 ? $curShip : (int)(setting_get('shipping_ch_cents') ?: 590);
    } else {
        // Bei normalen Bestellungen: Produktpreis = Gesamt minus Versand.
        $curProduct  = max(0, (int)$order['total_cents'] - $curShip);
        $defaultShip = $curShip;
    }
  ?>
  <div class="admin-section" style="margin-bottom:1.5rem<?= $isRequest ? ';border-color:rgba(99,102,241,.3)' : '' ?>">
    <h2 style="margin-top:0"><?= $isRequest ? 'Preis festlegen' : 'Preis anpassen' ?></h2>
    <p class="muted" style="font-size:.84rem;margin-top:-.5rem"><?= $isRequest
        ? 'Sobald du den Preis setzt, sieht ihn der Kunde im Profil unter „Meine Bestellungen".'
        : 'Passe Produktpreis und Versand an. Der neue Gesamtbetrag wird sofort übernommen.' ?></p>
    <form method="post" data-cap="orders.manage" style="display:flex;gap:.8rem;align-items:flex-end;flex-wrap:wrap">
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

  <p><strong>Versand:</strong> <?= format_price((int)$order['shipping_cents'], $currency) ?></p>
  <p><strong>Gesamt:</strong> <?= (int)$order['total_cents'] > 0 ? format_price((int)$order['total_cents'], $currency) : '<span class="muted">noch offen</span>' ?></p>
  <p><strong>Zahlung:</strong> <span class="tag <?= payment_status_class($order['payment_status']) ?>"><?= h(payment_status_label($order['payment_status'])) ?></span></p>

  <form method="post" data-cap="orders.manage" style="margin-top:1.5rem;display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
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
    <label class="field" style="min-width:280px;flex:1">
      <span>Bemerkung für den Kunden</span>
      <textarea name="note" rows="3" placeholder="z. B. Preisänderung, Lieferhinweis, Rückfrage"></textarea>
    </label>
    <?php if (!empty($otherOrders)): ?>
    <label class="field" style="min-width:280px;flex:1">
      <span>Mit anderer Bestellung zusammenführen</span>
      <select name="source_ref">
        <?php foreach ($otherOrders as $o): ?>
          <option value="<?= h($o['reference']) ?>"><?= h($o['reference']) ?> · <?= h(substr($o['created_at'], 0, 16)) ?> · <?= format_price((int)$o['total_cents'], $currency) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn-line" type="submit" name="action" value="merge">Zusammenführen</button>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit">Speichern</button>
  </form>

  <div class="admin-section" style="margin-top:1.5rem">
    <h2>Posteingang</h2>
    <?php if (empty($msgs)): ?>
      <p class="muted">Noch keine Nachrichten zu dieser Bestellung.</p>
    <?php else: foreach ($msgs as $m): ?>
      <div class="message-card <?= !empty($m['is_read']) ? '' : 'message-unread' ?>">
        <div class="message-meta">
          <strong><?= h($m['subject'] ?: ($m['is_system'] ? 'System' : 'Nachricht')) ?></strong>
          <span class="muted"><?= h($m['author_name'] ?: $m['author_role']) ?></span>
          <span class="muted"><?= h(substr($m['created_at'], 0, 16)) ?></span>
        </div>
        <p><?= nl2br(h($m['body'])) ?></p>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
