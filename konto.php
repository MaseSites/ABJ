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

// Daten neu laden (nach evtl. Profiländerung)
$account    = account_by_id((int)$cust['id']);
$savedAddr  = account_address($account);
$orders     = orders_by_email($cust['email']);
$totalSpent = array_sum(array_map(fn($o) => $o['payment_status'] === 'bezahlt' ? (int)$o['total_cents'] : 0, $orders));
$openPay    = array_sum(array_map(fn($o) => $o['payment_status'] !== 'bezahlt' && $o['status'] !== 'storniert' ? (int)$o['total_cents'] : 0, $orders));

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

$cartCount   = cart_count();
$currentPath = '/konto';
$pageTitle   = 'Mein Konto';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';

// Hilfsfunktion: Bestellkarte rendern
function ko_render_order(array $o, string $currency): void {
  $isReq   = order_is_request($o);
  $hasPrice = (int)$o['total_cents'] > 0;
?>
  <article class="acc-order">
    <div class="acc-order-head">
      <div>
        <span class="acc-order-ref"><?= h($o['reference']) ?></span>
        <span class="acc-order-date"><?= h(substr($o['created_at'], 0, 10)) ?></span>
      </div>
      <div class="acc-order-tags">
        <?php if ($isReq): ?><span class="tag tag-anfrage">Anfrage</span><?php endif; ?>
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
      <div class="acc-order-actions">
        <?php if (!$isReq): ?>
        <form method="post" action="<?= url('/konto.php') ?>" style="display:inline">
          <input type="hidden" name="action" value="reorder">
          <input type="hidden" name="ref" value="<?= h($o['reference']) ?>">
          <button class="btn btn-line btn-sm" type="submit">Erneut bestellen</button>
        </form>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= url('/bestellung.php?ref=' . urlencode($o['reference'])) ?>">Details</a>
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

      <!-- Übersicht -->
      <section class="acc-panel<?= $activeTab === 'overview' ? ' active' : '' ?>" data-panel="overview">
        <div class="acc-panel-head">
          <h1>Hallo<?= $firstName ? ', ' . h($firstName) : '' ?> 👋</h1>
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
            <div class="form-row-2" style="grid-template-columns:2fr 1fr">
              <label class="field"><span>Strasse</span><input type="text" name="street" maxlength="120" value="<?= h($savedAddr['street'] ?? '') ?>"></label>
              <label class="field"><span>Nr.</span><input type="text" name="housenr" maxlength="20" value="<?= h($savedAddr['housenr'] ?? '') ?>"></label>
            </div>
            <div class="form-row-2" style="grid-template-columns:1fr 2fr">
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
})();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
