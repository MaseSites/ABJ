<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_customer();

$cust     = current_customer();
$account  = account_by_id((int)$cust['id']);
$currency = setting_get('currency') ?: 'CHF';

$msg = ''; $msgType = 'ok'; $activeTab = $_GET['tab'] ?? 'overview';

// ── Konto löschen ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_account') {
    account_delete((int)$cust['id']);
    customer_logout();
    redirect('/?konto_geloescht=1');
}

// ── Passwort ändern ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    $activeTab = 'security';
    if (account_update_password((int)$cust['id'], $_POST['new_password'] ?? '')) {
        $msg = 'Passwort geändert.';
    } else { $msg = 'Das Passwort muss mindestens 8 Zeichen lang sein.'; $msgType = 'error'; }
}

// ── Profil & Adresse speichern ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {
    $activeTab = 'profile';
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = [
        'firstname' => trim($_POST['firstname'] ?? ''),
        'lastname'  => trim($_POST['lastname'] ?? ''),
        'street'    => trim($_POST['street'] ?? ''),
        'housenr'   => trim($_POST['housenr'] ?? ''),
        'zip'       => trim($_POST['zip'] ?? ''),
        'city'      => trim($_POST['city'] ?? ''),
        'country'   => trim($_POST['country'] ?? 'CH'),
    ];
    account_update_profile((int)$cust['id'], $name, $phone, $address);
    // Session-Name aktualisieren (für Begrüßung/Header)
    session_start_once();
    $_SESSION['customer']['name'] = $name;
    session_write_close();
    redirect('/konto.php?tab=profile&saved=1');
}

// ── Erneut bestellen (Artikel in den Warenkorb) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    $ref   = trim($_POST['ref'] ?? '');
    $order = $ref ? order_by_ref($ref) : null;
    if ($order && strtolower(trim($order['email'])) === strtolower(trim($cust['email']))) {
        $cart = cart_get();
        foreach ($order['items'] as $it) {
            $pid = (int)($it['productId'] ?? 0);
            if (!$pid) continue;
            $size = (string)($it['size'] ?? '');
            $qty  = max(1, (int)($it['qty'] ?? 1));
            $found = false;
            foreach ($cart as &$line) {
                if ((int)$line['productId'] === $pid && (string)($line['size'] ?? '') === $size) {
                    $line['qty'] += $qty; $found = true; break;
                }
            }
            unset($line);
            if (!$found) $cart[] = ['productId' => $pid, 'size' => $size, 'qty' => $qty];
        }
        cart_set($cart);
    }
    redirect('/warenkorb.php');
}

// ── Bestellung stornieren ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_order') {
    $ref = trim($_POST['ref'] ?? '');
    $ok  = $ref !== '' && order_cancel_by_customer($ref, $cust['email'] ?? '');
    redirect('/konto.php?tab=orders&' . ($ok ? 'cancelled=1' : 'cancel_failed=1'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_inbox_message') {
    $messageId = (int)($_POST['message_id'] ?? 0);
    if ($messageId > 0) {
        account_message_delete((int)$cust['id'], $messageId);
    }
    redirect('/konto.php?tab=inbox&deleted=1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'decline_offer') {
    $messageId = (int)($_POST['message_id'] ?? 0);
    $reason    = trim($_POST['reason'] ?? '');
    $offer     = $messageId > 0 ? account_message_by_id((int)$cust['id'], $messageId) : null;
    if ($offer) {
        $ref = trim((string)($offer['order_reference'] ?? ''));
        message_create([
            'name'    => $cust['name'] ?: 'Kunde',
            'email'   => $cust['email'] ?? '',
            'subject' => 'Angebot abgelehnt' . ($ref !== '' ? ' (' . $ref . ')' : ''),
            'message' => 'Der Kunde hat das Angebot zu seiner Produktanfrage abgelehnt.'
                . "\n\nBetreff: " . ($offer['subject'] ?: 'Anfrage')
                . "\n\nBegründung:\n" . ($reason !== '' ? $reason : '(Keine Begründung angegeben.)'),
        ]);
        account_message_delete((int)$cust['id'], $messageId);
    }
    redirect('/konto.php?tab=inbox&declined=1');
}

// ── Promo: Code generieren / Prämie einlösen ──
$promoFlash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'promo_gen') {
    promo_code_generate((int)$cust['id']);
    redirect('/konto.php?tab=promo&pg=1');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'promo_redeem') {
    $res = promo_redeem((int)$cust['id'], $_POST['reward'] ?? '');
    if (!empty($res['ok'])) {
        session_start_once(); $_SESSION['promo_flash'] = 'Eingelöst! Dein Code: ' . $res['code']; session_write_close();
        redirect('/konto.php?tab=promo&pr=1');
    }
    $activeTab = 'promo'; $promoFlash = $res['error'] ?? 'Einlösen fehlgeschlagen.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'activate_code') {
  $code = trim($_POST['access_code'] ?? '');
  $row  = $code !== '' ? access_code_find($code) : null;
  if ($row && access_code_is_usable($row)) {
    access_code_mark_used($code, (int)$cust['id']);
    account_confirm((int)$cust['id'], 'code');
    redirect('/konto.php?tab=overview&activated=1');
  }
  $activeTab = 'overview';
  $msg = 'Der Freigabecode ist ungültig oder bereits verwendet.';
  $msgType = 'error';
}
session_start_once();
if (!empty($_SESSION['promo_flash'])) { $promoFlash = $_SESSION['promo_flash']; unset($_SESSION['promo_flash']); }
session_write_close();

// Daten neu laden (nach evtl. Profiländerung)
$account    = account_by_id((int)$cust['id']);
$accountConfirmed = account_is_confirmed($account);
$savedAddr  = account_address($account);
$orders     = orders_by_email($cust['email']);
$inbox      = account_messages_by_account((int)$cust['id']);
$activeTab  = $_GET['tab'] ?? 'overview';
$activeTab  = in_array($activeTab, ['overview','orders','promo','profile','security','inbox'], true) ? $activeTab : 'overview';
if ($activeTab === 'inbox') {
    account_messages_mark_read((int)$cust['id']);
    $inbox = account_messages_by_account((int)$cust['id']);
}
$totalSpent = array_sum(array_map(fn($o) => (int)($o['paid_cents'] ?? 0), $orders));
$openPay    = array_sum(array_map(fn($o) => max(0, (int)$o['total_cents'] - (int)($o['paid_cents'] ?? 0)), array_filter($orders, fn($o) => ($o['status'] ?? '') !== 'storniert')));

// Promo-Daten
$promoPoints   = promo_points((int)$cust['id']);
$promoCodes    = promo_codes_for((int)$cust['id']);
$promoStats    = promo_referral_stats((int)$cust['id']);
$promoRewards  = promo_rewards();
$promoRedeemed = promo_redemptions_for((int)$cust['id']);
$promoPer100   = promo_points_per_100();
$siteBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$firstName = trim(explode(' ', $cust['name'] ?? '')[0] ?? '');
$initials  = mb_strtoupper(mb_substr($cust['name'] ?: $cust['email'], 0, 1));
if ($cust['name'] && str_contains($cust['name'], ' ')) {
    $p = explode(' ', $cust['name']);
    $initials = mb_strtoupper(mb_substr($p[0], 0, 1) . mb_substr(end($p), 0, 1));
}

function ko_status_label(string $s): string {
    return ['neu' => 'Neu', 'in_bearbeitung' => 'In Bearbeitung', 'storniert' => 'Storniert', 'versendet' => 'Versendet', 'abgeschlossen' => 'Abgeschlossen'][$s] ?? ucfirst($s);
}

$COUNTRIES = [
    ['CH','Schweiz'],['LI','Liechtenstein'],['AT','Österreich'],['DE','Deutschland'],
    ['FR','Frankreich'],['IT','Italien'],['ES','Spanien'],['NL','Niederlande'],['GB','Grossbritannien'],['US','USA'],
];

if (!empty($_GET['saved'])) { $msg = 'Profil gespeichert.'; $msgType = 'ok'; }
if (!empty($_GET['activated'])) { $msg = 'Konto freigeschaltet.'; $msgType = 'ok'; }

$cartCount   = cart_count();
$currentPath = '/konto';
$pageTitle   = 'Mein Konto';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';

// Hilfsfunktion: Bestellkarte rendern
function ko_render_order(array $o, string $currency): void {
  $isReq   = order_is_request($o);
  $hasPrice = (int)$o['total_cents'] > 0;
  $paidCents = (int)($o['paid_cents'] ?? 0);
  $openCents = max(0, (int)$o['total_cents'] - $paidCents);
?>
  <article class="acc-order">
    <div class="acc-order-head">
      <div>
        <span class="acc-order-ref"><?= h($o['reference']) ?></span>
        <span class="acc-order-date"><?= h(substr($o['created_at'], 0, 10)) ?></span>
        <?php if ($isReq && !$hasPrice): ?>
          <span class="tag tag-pending">In Prüfung</span>
        <?php else: ?>
          <span class="tag <?= payment_status_class($o['payment_status']) ?>"><?= h(payment_status_label($o['payment_status'])) ?></span>
        <?php endif; ?>
        <span class="tag"><?= h(ko_status_label($o['status'])) ?></span>
      </div>
    </div>
    <div class="acc-order-items">
      <?php foreach ($o['items'] as $it): ?>
        <span class="acc-order-item"><?= (int)($it['qty'] ?? 1) > 1 ? (int)$it['qty'] . '× ' : '' ?><?= h($it['name'] ?? '') ?><?= !empty($it['size']) ? ' · ' . h($it['size']) : '' ?></span>
      <?php endforeach; ?>
    </div>
    <div class="acc-order-foot">
      <strong><?= $hasPrice ? format_price((int)$o['total_cents'], $currency) : '<span class="muted" style="font-weight:500;font-size:.9rem">' . ($isReq ? 'Preis folgt' : '–') . '</span>' ?></strong>
      <?php if ($paidCents > 0): ?><span class="muted" style="display:block;font-size:.82rem">Bezahlt: <?= format_price($paidCents, $currency) ?> · Offen: <?= format_price($openCents, $currency) ?></span><?php endif; ?>
      <div class="acc-order-actions">
        <?php if (!$isReq): ?>
        <form method="post" action="<?= url('/konto.php') ?>" style="display:inline">
          <input type="hidden" name="action" value="reorder">
          <input type="hidden" name="ref" value="<?= h($o['reference']) ?>">
          <button class="btn btn-line btn-sm" type="submit">Erneut bestellen</button>
        </form>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= url('/bestellung.php?ref=' . urlencode($o['reference'])) ?>">Details</a>
        <?php if (!$isReq && in_array($o['status'], ['neu', 'in_bearbeitung'], true)): ?>
        <form method="post" action="<?= url('/konto.php') ?>" style="display:inline" onsubmit="return confirm('Diese Bestellung wirklich stornieren?')">
          <input type="hidden" name="action" value="cancel_order">
          <input type="hidden" name="ref" value="<?= h($o['reference']) ?>">
          <button class="btn btn-ghost btn-sm" type="submit" style="color:#e2604c">Stornieren</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </article>
<?php }
?>
<main id="main" class="container section">

  <div class="acc-shell" data-account>

    <!-- Sidebar -->
    <aside class="acc-side">
      <div class="acc-profile">
        <div class="acc-avatar"><?= h($initials) ?></div>
        <div class="acc-profile-info">
          <strong><?= h($cust['name'] ?: 'Willkommen') ?></strong>
          <span><?= h($cust['email']) ?></span>
        </div>
      </div>
      <nav class="acc-nav">
        <button type="button" class="acc-nav-item<?= $activeTab === 'overview' ? ' active' : '' ?>" data-tab-btn="overview">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
          Übersicht
        </button>
        <button type="button" class="acc-nav-item<?= $activeTab === 'orders' ? ' active' : '' ?>" data-tab-btn="orders">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0"/></svg>
          Bestellungen <?php if ($orders): ?><span class="acc-nav-count"><?= count($orders) ?></span><?php endif; ?>
        </button>
        <button type="button" class="acc-nav-item<?= $activeTab === 'inbox' ? ' active' : '' ?>" data-tab-btn="inbox">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6h18v9z"/><path d="M3 8l9 6 9-6"/></svg>
          Posteingang <?php if (account_messages_unread_count((int)$cust['id']) > 0): ?><span class="acc-nav-count"><?= account_messages_unread_count((int)$cust['id']) ?></span><?php endif; ?>
        </button>
        <button type="button" class="acc-nav-item<?= $activeTab === 'promo' ? ' active' : '' ?>" data-tab-btn="promo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 12v9H4v-9"/><path d="M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
          Promo Code <span class="acc-nav-count"><?= $promoPoints ?></span>
        </button>
        <button type="button" class="acc-nav-item<?= $activeTab === 'profile' ? ' active' : '' ?>" data-tab-btn="profile">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/></svg>
          Profil &amp; Adresse
        </button>
        <button type="button" class="acc-nav-item<?= $activeTab === 'security' ? ' active' : '' ?>" data-tab-btn="security">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          Sicherheit
        </button>
        <a class="acc-nav-item" href="<?= url('/abmelden.php') ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9"/><path d="M18 16l4-4-4-4M22 12H10"/></svg>
          Abmelden
        </a>
      </nav>
    </aside>

    <!-- Content -->
    <div class="acc-content">

      <?php if ($msg): ?><div class="alert alert-<?= $msgType === 'ok' ? 'ok' : 'error' ?>" style="margin-bottom:1.4rem"><?= h($msg) ?></div><?php endif; ?>

      <?php if ($promoFlash): $toastOk = str_starts_with($promoFlash, 'Eingelöst'); ?>
      <div class="promo-toast <?= $toastOk ? 'is-ok' : 'is-err' ?>" data-promo-toast role="status">
        <span class="promo-toast-dot"></span>
        <span class="promo-toast-text"><?= h($promoFlash) ?></span>
        <button type="button" class="promo-toast-x" data-toast-close aria-label="Schließen">&times;</button>
      </div>
      <?php endif; ?>

      <?php if (!$accountConfirmed): ?>
        <div class="alert alert-warn" style="margin-bottom:1rem">
          Achtung! Konto noch nicht aktiviert. Du kannst dich bereits bewegen, aber die Nutzung bleibt eingeschränkt, bis du einen Aktivierungscode eingibst oder wir dein Konto freischalten.
        </div>
        <form method="post" action="<?= url('/konto.php') ?>" class="acc-form" style="margin-bottom:1.4rem">
          <input type="hidden" name="action" value="activate_code">
          <div class="acc-card">
            <h3>Konto freischalten</h3>
            <label class="field" style="max-width:320px"><span>Aktivierungscode</span><input type="text" name="access_code" maxlength="20" autocomplete="off" placeholder="Code eingeben" style="letter-spacing:.06em"></label>
            <button class="btn btn-primary btn-sm" type="submit" style="align-self:flex-start">Freischalten</button>
          </div>
        </form>
      <?php endif; ?>

      <!-- Übersicht -->
      <section class="acc-panel<?= $activeTab === 'overview' ? ' active' : '' ?>" data-panel="overview">
        <div class="acc-panel-head">
          <h1>Hallo<?= $firstName ? ', ' . h($firstName) : '' ?></h1>
          <p class="muted">Schön, dass du da bist. Hier hast du alles im Blick.</p>
        </div>
        <div class="acc-stats">
          <div class="acc-stat"><strong><?= count($orders) ?></strong><span>Bestellungen</span></div>
          <div class="acc-stat"><strong><?= format_price($totalSpent, $currency) ?></strong><span>Ausgegeben</span></div>
          <div class="acc-stat"><strong style="color:<?= $openPay > 0 ? '#ffb0a4' : 'inherit' ?>"><?= format_price($openPay, $currency) ?></strong><span>Offen zu zahlen</span></div>
          <div class="acc-stat"><strong><?= h(substr($account['created_at'] ?? '', 0, 10)) ?></strong><span>Mitglied seit</span></div>
        </div>

        <div class="acc-quick">
          <a class="acc-quick-card" href="<?= url('/shop.php') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 3h2l2.4 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
            <span>Weiter einkaufen</span>
          </a>
          <a class="acc-quick-card" href="<?= url('/wunschliste.php') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/></svg>
            <span>Wunschliste</span>
          </a>
          <a class="acc-quick-card" href="<?= url('/kontakt.php') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span>Hilfe &amp; Kontakt</span>
          </a>
        </div>

        <?php if ($orders): ?>
        <div class="acc-section-head">
          <h2>Letzte Bestellungen</h2>
          <button type="button" class="link-arrow" data-tab-btn="orders">Alle ansehen <span aria-hidden="true">&rarr;</span></button>
        </div>
        <div class="acc-orders">
          <?php foreach (array_slice($orders, 0, 2) as $o) ko_render_order($o, $currency); ?>
        </div>
        <?php else: ?>
        <div class="cart-empty-state" style="margin-top:1.5rem">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="52" height="52"><path d="M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0"/></svg>
          <p>Du hast noch keine Bestellungen.</p>
          <a class="btn btn-primary" href="<?= url('/shop.php') ?>">Zum Shop</a>
        </div>
        <?php endif; ?>
      </section>

      <!-- Bestellungen -->
      <section class="acc-panel<?= $activeTab === 'orders' ? ' active' : '' ?>" data-panel="orders">
        <div class="acc-panel-head"><h1>Meine Bestellungen</h1></div>
        <?php if (!empty($_GET['cancelled'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Deine Bestellung wurde storniert.</div><?php endif; ?>
        <?php if (!empty($_GET['cancel_failed'])): ?><div class="alert alert-error" style="margin-bottom:1rem">Diese Bestellung kann nicht mehr storniert werden (bereits versendet, abgeschlossen oder storniert).</div><?php endif; ?>
        <?php if (empty($orders)): ?>
          <div class="cart-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="52" height="52"><path d="M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0"/></svg>
            <p>Du hast noch keine Bestellungen.</p>
            <a class="btn btn-primary" href="<?= url('/shop.php') ?>">Zum Shop</a>
          </div>
        <?php else: ?>
          <div class="acc-orders">
            <?php foreach ($orders as $o) ko_render_order($o, $currency); ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="acc-panel<?= $activeTab === 'inbox' ? ' active' : '' ?>" data-panel="inbox">
        <div class="acc-panel-head">
          <h1>Posteingang</h1>
          <p class="muted">Alle Nachrichten zu deinem Konto und deinen Bestellungen an einem Ort.</p>
        </div>
        <?php if (!empty($_GET['deleted'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Nachricht gelöscht.</div><?php endif; ?>
        <?php if (!empty($_GET['declined'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Danke für deine Rückmeldung – wir haben sie erhalten.</div><?php endif; ?>
        <?php if (empty($inbox)): ?>
          <div class="cart-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="52" height="52"><path d="M21 15a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6h18v9z"/><path d="M3 8l9 6 9-6"/></svg>
            <p>Noch keine Nachrichten.</p>
          </div>
        <?php else: ?>
          <div class="acc-orders">
            <?php foreach ($inbox as $msg): ?>
              <article class="acc-order">
                <div class="acc-order-head">
                  <div>
                    <span class="acc-order-ref"><?= h($msg['subject'] ?: 'Nachricht') ?></span>
                    <span class="acc-order-date"><?= h(substr($msg['created_at'], 0, 16)) ?></span>
                  </div>
                  <div class="acc-order-tags">
                    <?php if (!empty($msg['order_reference'])): ?><span class="tag tag-anfrage"><?= h($msg['order_reference']) ?></span><?php endif; ?>
                    <span class="tag"><?= h($msg['sender_role'] ?: 'admin') ?></span>
                  </div>
                </div>
                <div class="acc-order-items">
                  <span class="acc-order-item" style="white-space:pre-line"><?= h($msg['body']) ?></span>
                </div>
                <?php $isOffer = !empty($msg['message_type']) && $msg['message_type'] === 'request_offer'; ?>
                <div class="acc-order-actions" style="margin-top:.8rem">
                  <?php if ($isOffer): ?>
                    <?php if (!empty($msg['action_url'])): ?><a class="btn btn-primary btn-sm" href="<?= h($msg['action_url']) ?>"><?= h($msg['action_label'] ?: 'Dem Warenkorb hinzufügen') ?></a><?php endif; ?>
                    <button type="button" class="btn btn-danger btn-sm" onclick="var f=document.getElementById('decline-<?= (int)$msg['id'] ?>');f.style.display=f.style.display==='block'?'none':'block';"><?= h($msg['decline_label'] ?: 'Kein Interesse') ?></button>
                  <?php endif; ?>
                  <form method="post" action="<?= url('/konto.php') ?>" onsubmit="return confirm('Nachricht wirklich löschen?')" style="margin:0">
                    <input type="hidden" name="action" value="delete_inbox_message">
                    <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">Löschen</button>
                  </form>
                </div>
                <?php if ($isOffer): ?>
                <form method="post" action="<?= url('/konto.php') ?>" id="decline-<?= (int)$msg['id'] ?>" style="display:none;margin-top:.8rem">
                  <input type="hidden" name="action" value="decline_offer">
                  <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                  <label class="field" style="margin:0"><span>Warum kein Interesse? (optional)</span><textarea name="reason" rows="3" placeholder="Deine kurze Begründung hilft uns weiter."></textarea></label>
                  <button class="btn btn-danger btn-sm" type="submit" style="margin-top:.5rem">Ablehnung absenden</button>
                </form>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Promo Code -->
      <section class="acc-panel<?= $activeTab === 'promo' ? ' active' : '' ?>" data-panel="promo">
        <div class="acc-panel-head">
          <h1>Promo Code</h1>
          <p class="muted">Lade Freunde mit deinem Code ein. Für jede bezahlte Bestellung eines geworbenen Freundes bekommst du Punkte (<?= (int)$promoPer100 ?> je 100&nbsp;CHF) und löst sie unten gegen Gutscheine ein.</p>
        </div>

        <!-- Hero: Punktestand -->
        <div class="promo-hero">
          <div class="promo-hero-main">
            <span class="promo-hero-num"><?= $promoPoints ?></span>
            <span class="promo-hero-label">Promo Punkte</span>
          </div>
          <div class="promo-hero-stats">
            <div><strong><?= (int)$promoStats['referrals'] ?></strong><span>Geworbene Freunde</span></div>
            <div><strong><?= (int)$promoStats['orders'] ?></strong><span>Bestellungen</span></div>
            <div><strong><?= count($promoRedeemed) ?></strong><span>Eingelöste Prämien</span></div>
          </div>
        </div>

        <!-- Deine Codes (weit oben) -->
        <div class="promo-block">
          <div class="promo-block-head">
            <h2>Deine Codes</h2>
            <form method="post" action="<?= url('/konto.php') ?>"><input type="hidden" name="action" value="promo_gen"><button class="btn btn-primary btn-sm" type="submit">+ Neuen Code</button></form>
          </div>
          <?php if (empty($promoCodes)): ?>
            <div class="promo-empty">Du hast noch keinen Code. Generiere einen und teile ihn mit einem Freund.</div>
          <?php else: ?>
            <div class="promo-codes">
              <?php foreach ($promoCodes as $pc):
                $used = !empty($pc['used_by']);
                $who  = trim((string)($pc['used_name'] ?? '')) ?: ($pc['used_email'] ?? '');
                $shareUrl = $siteBase . url('/registrieren.php?promo=' . urlencode($pc['code'])); ?>
              <div class="promo-code-row<?= $used ? ' is-used' : '' ?>">
                <div class="promo-code-main">
                  <span class="promo-code-val"<?= $used ? '' : ' data-copy="' . h($pc['code']) . '"' ?>><?= h($pc['code']) ?></span>
                  <?php if ($used): ?>
                    <span class="promo-code-status used">Eingelöst<?= $who ? ' von ' . h($who) : '' ?></span>
                  <?php else: ?>
                    <span class="promo-code-status free">Frei · einmal verwendbar</span>
                  <?php endif; ?>
                </div>
                <?php if ($used): ?>
                  <span class="promo-code-badge">Vergeben</span>
                <?php else: ?>
                  <div class="promo-code-actions">
                    <button type="button" class="btn btn-ghost btn-sm" data-copy-btn="<?= h($pc['code']) ?>">Code kopieren</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-copy-btn="<?= h($shareUrl) ?>">Link</button>
                  </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Prämien -->
        <div class="promo-block">
          <div class="promo-block-head">
            <h2>Prämien einlösen</h2>
            <span class="promo-balance"><?= $promoPoints ?> Punkte verfügbar</span>
          </div>
          <div class="promo-shop">
            <?php foreach ($promoRewards as $key => $r): $can = $promoPoints >= $r['cost']; $missing = $r['cost'] - $promoPoints; ?>
            <div class="promo-reward<?= $can ? ' is-ready' : ' is-locked' ?>">
              <div class="promo-reward-head">
                <span class="promo-reward-kicker">Prämie</span>
                <span class="promo-reward-cost"><?= (int)$r['cost'] ?> Pkt.</span>
              </div>
              <div class="promo-reward-value"><?= h($r['short'] ?? $r['label']) ?></div>
              <p class="promo-reward-desc"><?= h($r['desc']) ?></p>
              <form method="post" action="<?= url('/konto.php') ?>" onsubmit="return confirm('<?= (int)$r['cost'] ?> Punkte für „<?= h($r['label']) ?>" einlösen?')">
                <input type="hidden" name="action" value="promo_redeem">
                <input type="hidden" name="reward" value="<?= h($key) ?>">
                <button class="btn btn-block btn-sm <?= $can ? 'btn-primary' : 'btn-line' ?>" type="submit"<?= $can ? '' : ' disabled' ?>><?= $can ? 'Einlösen' : ('Noch ' . $missing . ' Punkte') ?></button>
              </form>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if (!empty($promoRedeemed)): ?>
        <!-- Eingelöste Gutscheine -->
        <div class="promo-block">
          <div class="promo-block-head"><h2>Deine Gutscheine</h2></div>
          <div class="promo-vouchers">
            <?php foreach ($promoRedeemed as $rd): ?>
            <div class="promo-voucher">
              <div class="promo-voucher-info"><strong><?= h($rd['reward']) ?></strong><span><?= h(substr($rd['created_at'],0,10)) ?></span></div>
              <code class="promo-voucher-code" data-copy="<?= h($rd['code']) ?>"><?= h($rd['code']) ?></code>
            </div>
            <?php endforeach; ?>
          </div>
          <p class="muted" style="font-size:.8rem;margin:.7rem 0 0">Diese Codes gibst du beim Checkout im Feld „Rabattcode" ein.</p>
        </div>
        <?php endif; ?>

        <!-- So funktioniert's -->
        <div class="promo-steps">
          <div class="promo-step"><span class="promo-step-no">1</span><div><strong>Code teilen</strong><p>Generiere einen Code und schick ihn einem Freund.</p></div></div>
          <div class="promo-step"><span class="promo-step-no">2</span><div><strong>Freund registriert sich</strong><p>Mit deinem Code wird er deine Empfehlung.</p></div></div>
          <div class="promo-step"><span class="promo-step-no">3</span><div><strong>Punkte sammeln</strong><p>Pro bezahlter Bestellung deines Freundes.</p></div></div>
        </div>
      </section>

      <!-- Profil & Adresse -->
      <section class="acc-panel<?= $activeTab === 'profile' ? ' active' : '' ?>" data-panel="profile">
        <div class="acc-panel-head">
          <h1>Profil &amp; Adresse</h1>
          <p class="muted">Deine Standard-Lieferadresse wird beim Checkout automatisch ausgefüllt.</p>
        </div>
        <form method="post" action="<?= url('/konto.php') ?>" class="acc-form">
          <input type="hidden" name="action" value="profile">
          <div class="acc-card">
            <h3>Persönliche Daten</h3>
            <div class="form-row-2">
              <label class="field"><span>Name</span><input type="text" name="name" maxlength="120" value="<?= h($cust['name'] ?? '') ?>" placeholder="Max Muster"></label>
              <label class="field"><span>Telefon</span><input type="tel" name="phone" maxlength="40" value="<?= h($account['phone'] ?? '') ?>" placeholder="+41 79 …"></label>
            </div>
            <label class="field"><span>E-Mail</span><input type="email" value="<?= h($cust['email']) ?>" disabled><small class="muted" style="font-size:.75rem">Die E-Mail-Adresse kann nicht geändert werden.</small></label>
          </div>

          <div class="acc-card">
            <h3>Standard-Lieferadresse</h3>
            <div class="form-row-2">
              <label class="field"><span>Vorname</span><input type="text" name="firstname" maxlength="80" value="<?= h($savedAddr['firstname'] ?? $firstName) ?>"></label>
              <label class="field"><span>Nachname</span><input type="text" name="lastname" maxlength="80" value="<?= h($savedAddr['lastname'] ?? '') ?>"></label>
            </div>
            <div class="form-row-2 form-row-street">
              <label class="field"><span>Strasse</span><input type="text" name="street" maxlength="120" value="<?= h($savedAddr['street'] ?? '') ?>"></label>
              <label class="field"><span>Nr.</span><input type="text" name="housenr" maxlength="20" value="<?= h($savedAddr['housenr'] ?? '') ?>"></label>
            </div>
            <div class="form-row-2 form-row-zip">
              <label class="field"><span>PLZ</span><input type="text" name="zip" maxlength="10" value="<?= h($savedAddr['zip'] ?? '') ?>"></label>
              <label class="field"><span>Stadt</span><input type="text" name="city" maxlength="80" value="<?= h($savedAddr['city'] ?? '') ?>"></label>
            </div>
            <label class="field"><span>Land</span>
              <select name="country">
                <?php foreach ($COUNTRIES as [$code, $cname]): ?>
                  <option value="<?= h($code) ?>"<?= ($savedAddr['country'] ?? 'CH') === $code ? ' selected' : '' ?>><?= h($cname) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <button class="btn btn-primary" type="submit" style="align-self:flex-start">Speichern</button>
        </form>
      </section>

      <!-- Sicherheit -->
      <section class="acc-panel<?= $activeTab === 'security' ? ' active' : '' ?>" data-panel="security">
        <div class="acc-panel-head"><h1>Sicherheit</h1></div>
        <form method="post" action="<?= url('/konto.php') ?>" class="acc-form">
          <input type="hidden" name="action" value="password">
          <div class="acc-card">
            <h3>Passwort ändern</h3>
            <label class="field" style="max-width:420px"><span>Neues Passwort <small class="muted">(min. 8 Zeichen)</small></span>
              <input type="password" name="new_password" required minlength="8" autocomplete="new-password" placeholder="••••••••">
            </label>
            <button class="btn btn-line" type="submit" style="align-self:flex-start">Passwort speichern</button>
          </div>
        </form>

        <div class="danger-zone" style="margin-top:1.6rem">
          <div>
            <strong>Konto dauerhaft löschen</strong>
            <p class="muted" style="margin:.25rem 0 0;font-size:.85rem">Dein Zugang wird entfernt und du wirst abgemeldet. Bereits aufgegebene Bestellungen bleiben für die Abwicklung bestehen.</p>
          </div>
          <form method="post" action="<?= url('/konto.php') ?>" onsubmit="return confirm('Möchtest du dein Konto wirklich dauerhaft löschen?')">
            <input type="hidden" name="action" value="delete_account">
            <button class="btn btn-danger" type="submit">Konto löschen</button>
          </form>
        </div>
      </section>

    </div>
  </div>
</main>

<script>
(function () {
  var root = document.querySelector('[data-account]');
  if (!root) return;
  function activate(tab) {
    root.querySelectorAll('[data-tab-btn]').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-tab-btn') === tab);
    });
    root.querySelectorAll('[data-panel]').forEach(function (p) {
      p.classList.toggle('active', p.getAttribute('data-panel') === tab);
    });
    try { history.replaceState(null, '', '?tab=' + tab); } catch (e) {}
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  root.querySelectorAll('[data-tab-btn]').forEach(function (b) {
    b.addEventListener('click', function () { activate(b.getAttribute('data-tab-btn')); });
  });
  // Promo: Code/Link kopieren
  function copy(text, btn, label) {
    var done = function () { var t = btn.textContent; btn.textContent = label || 'Kopiert'; setTimeout(function(){ btn.textContent = t; }, 1500); };
    if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done, done); }
    else { var ta=document.createElement('textarea'); ta.value=text; document.body.appendChild(ta); ta.select(); try{document.execCommand('copy');}catch(e){} ta.remove(); done(); }
  }
  root.querySelectorAll('[data-copy-btn]').forEach(function (b) {
    b.addEventListener('click', function () { copy(b.getAttribute('data-copy-btn'), b, 'Kopiert'); });
  });
  root.querySelectorAll('[data-copy]').forEach(function (el) {
    el.style.cursor = 'pointer'; el.title = 'Klicken zum Kopieren';
    el.addEventListener('click', function () { copy(el.getAttribute('data-copy'), el, el.getAttribute('data-copy')); });
  });
  // Promo: Einlöse-Popup (Toast) oben einblenden und automatisch ausblenden
  var toast = document.querySelector('[data-promo-toast]');
  if (toast) {
    requestAnimationFrame(function(){ toast.classList.add('show'); });
    var hide = function () { toast.classList.remove('show'); setTimeout(function(){ if(toast.parentNode) toast.parentNode.removeChild(toast); }, 350); };
    var timer = setTimeout(hide, 5000);
    var x = toast.querySelector('[data-toast-close]');
    if (x) x.addEventListener('click', function(){ clearTimeout(timer); hide(); });
  }
})();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
