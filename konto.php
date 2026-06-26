<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_customer();

$cust     = current_customer();
$account  = account_by_id((int)$cust['id']);
$currency = setting_get('currency') ?: 'CHF';

// Eigenes Konto löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_account') {
    account_delete((int)$cust['id']);
    customer_logout();
    redirect('/?konto_geloescht=1');
}

$msg = ''; $msgType = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    $new = $_POST['new_password'] ?? '';
    if (account_update_password((int)$cust['id'], $new)) {
        $msg = 'Passwort geändert.';
    } else {
        $msg = 'Das Passwort muss mindestens 8 Zeichen lang sein.'; $msgType = 'error';
    }
}

$orders = orders_by_email($cust['email']);
$totalSpent = array_sum(array_map(fn($o) => $o['payment_status'] === 'bezahlt' ? (int)$o['total_cents'] : 0, $orders));

function ko_status_label(string $s): string {
    return ['neu' => 'Neu', 'in_bearbeitung' => 'In Bearbeitung', 'storniert' => 'Storniert', 'versendet' => 'Versendet'][$s] ?? ucfirst($s);
}

$cartCount   = cart_count();
$currentPath = '/konto';
$pageTitle   = 'Mein Konto';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main id="main" class="container section">
  <div class="konto-head">
    <div>
      <span class="section-title-label">Mein Konto</span>
      <h1 class="section-title" style="margin-bottom:.2rem">Hallo<?= $cust['name'] ? ', ' . h(explode(' ', $cust['name'])[0]) : '' ?>!</h1>
      <p class="muted" style="margin:0"><?= h($cust['email']) ?></p>
    </div>
    <a class="btn btn-ghost" href="<?= url('/abmelden.php') ?>">Abmelden</a>
  </div>

  <div class="konto-stats">
    <div class="konto-stat"><strong><?= count($orders) ?></strong><span>Bestellungen</span></div>
    <div class="konto-stat"><strong><?= format_price($totalSpent, $currency) ?></strong><span>Gesamt ausgegeben</span></div>
    <div class="konto-stat"><strong><?= h(substr($account['created_at'] ?? '', 0, 10)) ?></strong><span>Mitglied seit</span></div>
  </div>

  <h2 style="font-size:1.2rem;margin:2.4rem 0 1.2rem">Meine Bestellungen</h2>
  <?php if (empty($orders)): ?>
    <div class="cart-empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="52" height="52"><path d="M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0"/></svg>
      <p>Du hast noch keine Bestellungen.</p>
      <a class="btn btn-primary" href="<?= url('/shop.php') ?>">Zum Shop</a>
    </div>
  <?php else: ?>
    <div class="konto-orders">
      <?php foreach ($orders as $o): ?>
      <article class="konto-order">
        <div class="konto-order-top">
          <div>
            <span class="konto-order-ref"><?= h($o['reference']) ?></span>
            <span class="muted" style="font-size:.82rem"><?= h(substr($o['created_at'], 0, 10)) ?></span>
          </div>
          <div class="konto-order-tags">
            <span class="tag <?= payment_status_class($o['payment_status']) ?>"><?= h(payment_status_label($o['payment_status'])) ?></span>
            <span class="tag"><?= h(ko_status_label($o['status'])) ?></span>
          </div>
        </div>
        <div class="konto-order-items">
          <?php foreach ($o['items'] as $it): ?>
            <span class="konto-order-item"><?= (int)($it['qty'] ?? 1) ?>× <?= h($it['name'] ?? '') ?><?= !empty($it['size']) ? ' (' . h($it['size']) . ')' : '' ?></span>
          <?php endforeach; ?>
        </div>
        <div class="konto-order-bottom">
          <strong><?= format_price((int)$o['total_cents'], $currency) ?></strong>
          <a class="link-arrow" href="<?= url('/bestellung.php?ref=' . urlencode($o['reference'])) ?>">Details ansehen <span aria-hidden="true">&rarr;</span></a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 style="font-size:1.2rem;margin:2.8rem 0 1.2rem">Passwort ändern</h2>
  <?php if ($msg): ?><div class="alert alert-<?= $msgType === 'ok' ? 'ok' : 'error' ?>" style="max-width:420px"><?= h($msg) ?></div><?php endif; ?>
  <form method="post" action="<?= url('/konto.php') ?>" class="auth-form" style="max-width:420px">
    <input type="hidden" name="action" value="password">
    <label class="field"><span>Neues Passwort <small class="muted">(min. 8 Zeichen)</small></span>
      <input type="password" name="new_password" required minlength="8" autocomplete="new-password" placeholder="••••••••">
    </label>
    <button class="btn btn-line" type="submit" style="align-self:flex-start">Passwort speichern</button>
  </form>

  <h2 style="font-size:1.2rem;margin:2.8rem 0 1.2rem">Konto löschen</h2>
  <div class="danger-zone">
    <div>
      <strong>Konto dauerhaft löschen</strong>
      <p class="muted" style="margin:.25rem 0 0;font-size:.85rem">Dein Zugang wird entfernt und du wirst abgemeldet. Bereits aufgegebene Bestellungen bleiben für die Abwicklung bestehen.</p>
    </div>
    <form method="post" action="<?= url('/konto.php') ?>" onsubmit="return confirm('Möchtest du dein Konto wirklich dauerhaft löschen?')">
      <input type="hidden" name="action" value="delete_account">
      <button class="btn btn-danger" type="submit">Konto löschen</button>
    </form>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
