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

<?php
$currency = setting_get('currency') ?: 'CHF';
function anfrage_status_tag(string $s): string {
    return ['neu' => 'tag-new', 'angenommen' => 'tag-ok', 'abgelehnt' => 'tag-pending'][$s] ?? '';
}
?>
<?php if (empty($requests)): ?>
  <div class="admin-section"><p class="muted" style="margin:0">Keine Anfragen vorhanden.</p></div>
<?php else: ?>
  <div class="req-list">
    <?php foreach ($requests as $r): $done = in_array($r['status'], ['angenommen', 'abgelehnt'], true); ?>
    <article class="req-card<?= $done ? ' req-done' : '' ?>">
      <div class="req-top">
        <?php if (!empty($r['screenshot'])): ?>
          <a class="req-thumb" href="<?= h($r['screenshot']) ?>" target="_blank" rel="noopener"><img src="<?= h($r['screenshot']) ?>" alt="Screenshot"></a>
        <?php else: ?>
          <div class="req-thumb req-thumb-empty" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
          </div>
        <?php endif; ?>
        <div class="req-info">
          <div class="req-meta">
            <span class="req-id">#<?= (int)$r['id'] ?></span>
            <span class="tag <?= anfrage_status_tag($r['status']) ?>"><?= h($r['status']) ?></span>
            <span class="req-cust"><?= h($r['customer_name'] ?: $r['email']) ?></span>
            <?php if (!empty($r['created_at'])): ?><span class="muted req-date"><?= h(substr($r['created_at'], 0, 16)) ?></span><?php endif; ?>
            <?php if ((int)$r['price_cents'] > 0): ?><span class="req-price">Preis: <?= format_price((int)$r['price_cents'], $currency) ?></span><?php endif; ?>
          </div>
          <?php if (!empty($r['description'])): ?><p class="req-desc"><?= h($r['description']) ?></p><?php endif; ?>
          <?php if (!empty($r['link'])): ?><a class="req-link" href="<?= h(secure_url($r['link'])) ?>" target="_blank" rel="noopener"><?= h($r['link']) ?></a><?php endif; ?>
        </div>
      </div>

      <div class="req-actions">
        <form method="post" class="req-form">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="action" value="accept">
          <label class="field req-field-price"><span>Preis (<?= h($currency) ?>)</span><input type="text" name="price" inputmode="decimal" placeholder="129.00" value="<?= (int)$r['price_cents'] > 0 ? number_format((int)$r['price_cents']/100, 2, '.', '') : '' ?>"></label>
          <label class="field req-field-note"><span>Nachricht an den Kunden</span><input type="text" name="note" placeholder="Optionaler Text"></label>
          <button class="btn btn-primary" type="submit">Annehmen</button>
        </form>
        <div class="req-form-row">
          <form method="post" class="req-form req-form-reject">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <label class="field req-field-note"><span>Ablehnungsnachricht</span><input type="text" name="note" placeholder="Optional"></label>
            <button class="btn btn-line" type="submit">Ablehnen</button>
          </form>
          <form method="post" class="req-del-form" onsubmit="return confirm('Anfrage wirklich löschen?')">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button class="btn btn-ghost btn-sm btn-danger" type="submit">Löschen</button>
          </form>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
