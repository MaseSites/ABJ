<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_cap('orders.manage');
    $id = (int)($_POST['id'] ?? 0);
    $req = $id ? request_by_id($id) : null;
    if ($req) {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            request_delete($id);
            redirect('/admin/anfragen.php?deleted=1');
        }
        $price = (int)round((float)str_replace(',', '.', trim($_POST['price'] ?? '')) * 100);
        $note  = trim($_POST['note'] ?? '');
        if ($action === 'accept') {
            request_update_status($id, 'angenommen', $price, $note);
            account_message_create([
                'account_id' => (int)$req['account_id'],
                'sender_role' => 'system',
                'subject' => 'Gute Nachricht zu deiner Anfrage 🎉',
                'body' => trim('Wir können deine Anfrage erfüllen!' . ($note !== '' ? "\n\n" . $note : '') . "\n\nPreis: " . number_format($price / 100, 2, '.', '') . ' CHF.' . "\n\nKlicke unten, um das Produkt direkt in deinen Warenkorb zu legen."),
                'is_read' => 0,
                'message_type' => 'request_offer',
                'action_url' => url('/shop.php'),
                'decline_url' => url('/konto.php?tab=inbox'),
                'action_label' => 'Dem Warenkorb hinzufügen',
                'decline_label' => 'Kein Interesse',
            ]);
        } elseif ($action === 'reject') {
            request_update_status($id, 'abgelehnt', 0, $note);
            account_message_create([
                'account_id' => (int)$req['account_id'],
                'sender_role' => 'system',
                'subject' => 'Rückmeldung zu deiner Anfrage',
                'body' => $note !== '' ? $note : 'Leider können wir diese Anfrage diesmal nicht erfüllen. Danke für dein Verständnis – schau gerne wieder bei uns vorbei!',
                'is_read' => 0,
            ]);
        }
    }
    redirect('/admin/anfragen.php?saved=1');
}

$requests = requests_list();
$adminTitle = 'Anfragen';
include __DIR__ . '/partials/admin-layout-top.php';
?>
<p class="admin-kicker">Produktanfragen</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Anfragen (<?= count($requests) ?>)</h1>
</div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Anfrage gelöscht.</div><?php endif; ?>

<div class="admin-section">
  <?php if (empty($requests)): ?>
    <p class="muted" style="margin:0">Keine Anfragen vorhanden.</p>
  <?php else: ?>
    <div class="table-card">
      <table class="data-table">
        <thead><tr><th>ID</th><th>Kunde</th><th>Beschreibung</th><th>Status</th><th>Preis</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($requests as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['customer_name'] ?: $r['email']) ?></td>
            <td><?= h($r['description']) ?></td>
            <td><span class="tag"><?= h($r['status']) ?></span></td>
            <td><?= (int)$r['price_cents'] > 0 ? format_price((int)$r['price_cents'], setting_get('currency') ?: 'CHF') : '—' ?></td>
            <td>
              <form method="post" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="accept">
                <label class="field" style="max-width:160px"><span>Preis</span><input type="text" name="price" inputmode="decimal" placeholder="129.00" value="<?= (int)$r['price_cents'] > 0 ? number_format((int)$r['price_cents']/100, 2, '.', '') : '' ?>"></label>
                <label class="field" style="min-width:220px;flex:1"><span>Nachricht</span><input type="text" name="note" placeholder="Nachricht an den Kunden"></label>
                <button class="btn btn-primary" type="submit">Annehmen</button>
              </form>
              <form method="post" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;margin-top:.5rem">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <label class="field" style="min-width:220px;flex:1"><span>Ablehnungsnachricht</span><input type="text" name="note" placeholder="Optional"></label>
                <button class="btn btn-danger" type="submit">Ablehnen</button>
              </form>
              <form method="post" onsubmit="return confirm('Anfrage wirklich löschen?')" style="margin-top:.5rem">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-ghost btn-sm btn-danger" type="submit">Löschen</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
