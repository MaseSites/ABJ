<?php
require_once __DIR__ . '/lib/bootstrap.php';

// Bestellen nur mit Konto und eingeloggt.
require_customer();

$cart = cart_get();
if (empty($cart)) redirect('/warenkorb.php?leer=1');

$currency = setting_get('currency') ?: 'CHF';

$items    = [];
$subtotal = 0;
foreach ($cart as $line) {
    $p = product_by_id($line['productId']);
    if (!$p || !$p['is_active']) continue;
    $variantRow = inv_by_variant($line['productId'], $line['size'] ?? '', '');
    $unit = ($variantRow && $variantRow['variant_price_cents'] !== null)
        ? (int)$variantRow['variant_price_cents']
        : (int)($p['sale_price_cents'] ?? $p['price_cents']);
    $avail   = inv_stock_for_variant($line['productId'], $line['size'] ?? '', '');
    $isBO    = ($avail <= 0) && inv_is_back_order($line['productId'], $line['size'] ?? '', '');
    $safeQty = $isBO ? $line['qty'] : min($line['qty'], max(0, $avail));
    if ($safeQty === 0) continue;
    $subtotal += $unit * $safeQty;
    $imgSrc = null;
    if ($variantRow) { $imgs = safe_parse($variantRow['images'] ?? '[]', []); $imgSrc = $imgs[0]['src'] ?? null; }
    $imgSrc = $imgSrc ?: ($p['images'][0]['src'] ?? null);
    $items[] = [
        'name'      => $p['name'],
        'size'      => $line['size'] ?? '',
        'qty'       => $safeQty,
        'lineCents' => $unit * $safeQty,
        'img'       => $imgSrc,
    ];
}

if (empty($items)) redirect('/warenkorb.php?ausverkauft=1');

$defaultCountry = 'CH';
$shipping = shipping_cost_cents($defaultCountry, $subtotal);
$total    = $subtotal + $shipping;

$COUNTRIES = [
    ['CH','Schweiz'],['LI','Liechtenstein'],['AT','Österreich'],['DE','Deutschland'],
    ['LU','Luxemburg'],['BE','Belgien'],['NL','Niederlande'],['FR','Frankreich'],
    ['IT','Italien'],['ES','Spanien'],['PL','Polen'],['CZ','Tschechien'],
    ['DK','Dänemark'],['SE','Schweden'],['NO','Norwegen'],['GB','Grossbritannien'],['US','USA'],
];

$custName = is_customer() ? trim(current_customer()['name'] ?? '') : '';
$custFirst = $custName ? explode(' ', $custName)[0] : '';
$custLast  = ($custName && str_contains($custName, ' ')) ? trim(substr($custName, strpos($custName, ' ') + 1)) : '';

// Gespeicherte Standard-Adresse des Kontos zum Vorausfüllen laden.
$acc       = is_customer() ? account_by_id((int)current_customer()['id']) : null;
$savedAddr = account_address($acc);
$preFirst  = $savedAddr['firstname'] ?? $custFirst;
$preLast   = $savedAddr['lastname']  ?? $custLast;
$prePhone  = $acc['phone']           ?? '';
$preStreet = $savedAddr['street']    ?? '';
$preHouse  = $savedAddr['housenr']   ?? '';
$preZip    = $savedAddr['zip']       ?? '';
$preCity   = $savedAddr['city']      ?? '';
$preCountry = $savedAddr['country']  ?? 'CH';

$currentPath = '/kasse';
$cartCount   = cart_count();
$pageTitle   = 'Kasse';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section">

  <a href="<?= url('/warenkorb.php') ?>" class="checkout-back">
    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="10 2 4 8 10 14"/></svg>
    Zurück zum Warenkorb
  </a>

  <h1 class="checkout-title">Kasse</h1>

  <div id="checkout-error" class="checkout-error" hidden></div>

  <div class="checkout-grid">

    <!-- Left: form -->
    <form
      id="checkout-form"
      class="checkout-form"
      data-total-cents="<?= $total ?>"
      data-currency="<?= h(strtolower($currency)) ?>"
      novalidate
    >

      <!-- 1 Contact -->
      <div class="checkout-section">
        <h2 class="checkout-section-title">
          <span class="checkout-step">1</span> Kontakt
        </h2>
        <div class="form-row-2">
          <label class="field">
            <span>E-Mail *</span>
            <input type="email" name="email" required maxlength="200"
              autocomplete="email" placeholder="name@beispiel.ch"
              value="<?= h(is_customer() ? (current_customer()['email'] ?? '') : '') ?>">
          </label>
          <label class="field">
            <span>Telefon <small class="muted">(optional)</small></span>
            <input type="tel" name="phone" maxlength="30"
              autocomplete="tel" placeholder="+41 79 123 45 67" value="<?= h($prePhone) ?>">
          </label>
        </div>
      </div>

      <!-- 2 Shipping address -->
      <div class="checkout-section">
        <h2 class="checkout-section-title">
          <span class="checkout-step">2</span> Lieferadresse
        </h2>
        <div class="form-row-2">
          <label class="field">
            <span>Vorname *</span>
            <input type="text" name="firstname" required maxlength="80"
              autocomplete="given-name" placeholder="Max" value="<?= h($preFirst) ?>">
          </label>
          <label class="field">
            <span>Nachname *</span>
            <input type="text" name="lastname" required maxlength="80"
              autocomplete="family-name" placeholder="Muster" value="<?= h($preLast) ?>">
          </label>
        </div>
        <div class="form-row-2" style="grid-template-columns:2fr 1fr">
          <label class="field">
            <span>Strasse *</span>
            <input type="text" name="street" required maxlength="120"
              autocomplete="address-line1" placeholder="Musterstrasse" value="<?= h($preStreet) ?>">
          </label>
          <label class="field">
            <span>Nr. *</span>
            <input type="text" name="housenr" required maxlength="20" placeholder="12a" value="<?= h($preHouse) ?>">
          </label>
        </div>
        <div class="form-row-2" style="grid-template-columns:1fr 2fr">
          <label class="field">
            <span>PLZ *</span>
            <input type="text" name="zip" required maxlength="10"
              autocomplete="postal-code" placeholder="8000" value="<?= h($preZip) ?>">
          </label>
          <label class="field">
            <span>Stadt *</span>
            <input type="text" name="city" required maxlength="80"
              autocomplete="address-level2" placeholder="Zürich" value="<?= h($preCity) ?>">
          </label>
        </div>
        <label class="field">
          <span>Land *</span>
          <select name="country" autocomplete="country" data-country-select>
            <?php foreach ($COUNTRIES as [$code, $name]): ?>
              <option value="<?= h($code) ?>"<?= $code === $preCountry ? ' selected' : '' ?>><?= h($name) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <!-- 3 Payment -->
      <div class="checkout-section">
        <h2 class="checkout-section-title">
          <span class="checkout-step">3</span> Zahlung
        </h2>

        <div class="pay-pending-note">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          <div>
            <strong>Zahlung ausstehend</strong>
            <p>Du gibst deine Bestellung ohne sofortige Zahlung auf. Wir melden uns mit den Zahlungsdetails — du findest den Zahlungsstatus jederzeit in deinem Konto.</p>
          </div>
        </div>

      </div>

      <input type="hidden" name="discount_code" value="" data-discount-hidden>

      <button class="btn btn-primary btn-block checkout-submit" type="submit">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="2 8 6 12 14 4"/></svg>
        <span class="checkout-submit-text">Bestellung aufgeben &middot; <span data-total-label><?= format_price($total, $currency) ?></span></span>
      </button>

      <ul class="checkout-trust">
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          SSL-verschlüsselte Übertragung
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Käuferschutz &amp; sichere Zahlung
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
          14 Tage Rückgaberecht
        </li>
      </ul>

    </form>

    <!-- Right: order summary -->
    <div class="order-summary">
      <h2 style="font-size:.74rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-soft);margin-bottom:1.2rem">Bestellübersicht</h2>

      <div style="display:flex;flex-direction:column;margin-bottom:1.2rem">
        <?php foreach ($items as $it): ?>
        <div class="checkout-item">
          <?php if ($it['img']): ?>
            <img src="<?= h($it['img']) ?>" alt="<?= h($it['name']) ?>" class="checkout-item-img">
          <?php else: ?>
            <div class="checkout-item-img" style="background:var(--bg-2)"></div>
          <?php endif; ?>
          <div class="checkout-item-info">
            <span class="checkout-item-name"><?= h($it['name']) ?></span>
            <?php if ($it['size']): ?><span class="muted" style="font-size:.75rem"><?= h($it['size']) ?></span><?php endif; ?>
            <span class="muted" style="font-size:.75rem">&times; <?= $it['qty'] ?></span>
          </div>
          <span class="checkout-item-price"><?= format_price($it['lineCents'], $currency) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Rabattcode -->
      <div style="margin-bottom:1.1rem">
        <div class="discount-row" data-discount-row>
          <input type="text" placeholder="Rabattcode" maxlength="40" data-discount-input aria-label="Rabattcode">
          <button type="button" class="btn btn-line btn-sm" data-discount-apply>Einlösen</button>
        </div>
        <div data-discount-applied hidden style="margin-top:.5rem">
          <span class="discount-applied">
            <span data-discount-code-label></span>
            <button type="button" data-discount-remove aria-label="Rabattcode entfernen">&times;</button>
          </span>
        </div>
        <p class="discount-msg" data-discount-msg hidden></p>
      </div>

      <div class="summary-row"><span>Zwischensumme</span><span data-subtotal-label><?= format_price($subtotal, $currency) ?></span></div>
      <div class="summary-row summary-discount" data-discount-summary hidden><span>Rabatt</span><span data-discount-label></span></div>
      <div class="summary-row">
        <span>Versand</span>
        <span data-shipping-label><?= $shipping === 0 ? 'Kostenlos' : format_price($shipping, $currency) ?></span>
      </div>
      <div class="summary-total">
        <strong>Gesamt</strong>
        <strong style="color:var(--accent-3)" data-summary-total><?= format_price($total, $currency) ?></strong>
      </div>
      <p class="muted" style="font-size:.72rem;margin-top:.6rem">inkl. MwSt.</p>
    </div>

  </div>
</main>

<script>
(function () {
  var BASE = document.documentElement.getAttribute('data-base-path') || '';

  /* ---------------- Rabattcode ---------------- */
  var dInput   = document.querySelector('[data-discount-input]');
  var dApply   = document.querySelector('[data-discount-apply]');
  var dMsg     = document.querySelector('[data-discount-msg]');
  var dHidden  = document.querySelector('[data-discount-hidden]');
  var dApplied = document.querySelector('[data-discount-applied]');
  var dCodeLbl = document.querySelector('[data-discount-code-label]');
  var dRow     = document.querySelector('[data-discount-row]');
  var dSummary = document.querySelector('[data-discount-summary]');
  var dLabel   = document.querySelector('[data-discount-label]');
  var shipLbl  = document.querySelector('[data-shipping-label]');
  var totalLbl = document.querySelector('[data-summary-total]');
  var totalBtn = document.querySelector('[data-total-label]');
  var country  = document.querySelector('[data-country-select]');

  function showDiscountMsg(text, ok) {
    if (!dMsg) return;
    dMsg.hidden = false;
    dMsg.textContent = text;
    dMsg.className = 'discount-msg ' + (ok ? 'ok' : 'err');
  }

  function applyDiscount() {
    var code = (dInput && dInput.value || '').trim();
    if (!code) return;
    var fd = new URLSearchParams();
    fd.set('code', code);
    fd.set('country', country ? country.value : 'CH');
    dApply.disabled = true;
    fetch(BASE + '/api/discount-check.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body: fd.toString(),
    }).then(function (r) { return r.json(); }).then(function (d) {
      dApply.disabled = false;
      if (!d.ok) { showDiscountMsg(d.error || 'Code ungültig.', false); return; }
      if (dHidden) dHidden.value = d.code;
      if (dCodeLbl) dCodeLbl.textContent = d.code + ' · ' + d.discountText;
      if (dApplied) dApplied.hidden = false;
      if (dRow) dRow.hidden = true;
      if (dSummary) { dSummary.hidden = false; if (dLabel) dLabel.textContent = d.discountText; }
      if (shipLbl) shipLbl.textContent = d.shippingText;
      if (totalLbl) totalLbl.textContent = d.totalText;
      if (totalBtn) totalBtn.textContent = d.totalText;
      showDiscountMsg('Code eingelöst.', true);
    }).catch(function () {
      dApply.disabled = false;
      showDiscountMsg('Netzwerkfehler. Bitte erneut versuchen.', false);
    });
  }

  if (dApply) dApply.addEventListener('click', applyDiscount);
  if (dInput) dInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); applyDiscount(); }
  });
  var dRemove = document.querySelector('[data-discount-remove]');
  if (dRemove) dRemove.addEventListener('click', function () {
    window.location.reload();
  });

  /* ---------------- Submit ---------------- */
  var form      = document.getElementById('checkout-form');
  var submitBtn = form ? form.querySelector('.checkout-submit') : null;
  var errBox    = document.getElementById('checkout-error');
  var origText  = submitBtn ? submitBtn.querySelector('.checkout-submit-text').textContent : '';

  function setLoading(on) {
    if (!submitBtn) return;
    submitBtn.disabled = on;
    var span = submitBtn.querySelector('.checkout-submit-text');
    if (span) span.textContent = on ? 'Wird verarbeitet…' : origText;
  }

  function showError(msg) {
    if (!errBox) return;
    errBox.textContent = msg;
    errBox.hidden = false;
    errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  if (form) {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      if (errBox) errBox.hidden = true;
      setLoading(true);

      var fd = new FormData(form);

      // Bestellung ohne Zahlung anlegen
      var data;
      try {
        var res = await fetch(BASE + '/api/checkout.php', { method: 'POST', body: fd });
        if (res.status === 401) {
          window.location.href = BASE + '/anmelden.php?weiter=' + encodeURIComponent(BASE + '/kasse.php');
          return;
        }
        data = await res.json().catch(function () { return { ok: false, error: 'Server-Fehler (ungültige Antwort)' }; });
      } catch (err) {
        showError('Netzwerkfehler. Bitte überprüfe deine Verbindung und versuche es erneut.');
        setLoading(false);
        return;
      }

      if (!data || !data.ok) {
        if (data && data.login_required) {
          window.location.href = BASE + '/anmelden.php?weiter=' + encodeURIComponent(BASE + '/kasse.php');
          return;
        }
        showError((data && data.error) ? data.error : 'Fehler bei der Bestellung. Bitte versuche es erneut.');
        setLoading(false);
        return;
      }

      if (data.redirect) {
        window.location.href = data.redirect;
        return;
      }

      showError('Unerwartete Antwort vom Server. Bitte versuche es erneut.');
      setLoading(false);
    });
  }
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
