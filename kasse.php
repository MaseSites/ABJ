<?php
require_once __DIR__ . '/lib/bootstrap.php';

$currency = setting_get('currency') ?: 'EUR';
$SHIPPING_FREE_ABOVE = 4900;
$SHIPPING_RATE       = 490;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname      = trim($_POST['firstname']      ?? '');
    $lastname       = trim($_POST['lastname']       ?? '');
    $email          = trim($_POST['email']          ?? '');
    $phone          = trim($_POST['phone']          ?? '');
    $street         = trim($_POST['street']         ?? '');
    $housenr        = trim($_POST['housenr']        ?? '');
    $zip            = trim($_POST['zip']            ?? '');
    $city           = trim($_POST['city']           ?? '');
    $country        = trim($_POST['country']        ?? 'DE');
    $payment_method = trim($_POST['payment_method'] ?? 'vorkasse');

    if (!$firstname || !$lastname || !str_has($email, '@') || !$street || !$zip || !$city) {
        redirect('/kasse?error=validation');
    }
    if ($payment_method === 'kreditkarte') {
        $cardNr = preg_replace('/\s/', '', $_POST['card_number'] ?? '');
        $expiry = $_POST['card_expiry'] ?? '';
        $cvc    = $_POST['card_cvc']    ?? '';
        if (strlen($cardNr) < 13 || !$expiry || strlen($cvc) < 3) redirect('/kasse?error=card');
    }

    $cart = cart_get();
    if (empty($cart)) redirect('/warenkorb');

    $lineItems = [];
    $subtotal  = 0;
    foreach ($cart as $line) {
        $p = product_by_id($line['productId']);
        if (!$p || !$p['is_active']) continue;
        $variantRow = inv_by_variant($line['productId'], $line['size'] ?? '', '');
        $unit = ($variantRow && $variantRow['variant_price_cents'] !== null)
            ? (int)$variantRow['variant_price_cents']
            : (int)($p['sale_price_cents'] ?? $p['price_cents']);
        $avail   = inv_stock_for_variant($line['productId'], $line['size'] ?? '', '');
        $safeQty = min($line['qty'], max(0, $avail));
        if ($safeQty === 0) continue;
        $subtotal += $unit * $safeQty;
        $lineItems[] = ['productId' => $p['id'], 'slug' => $p['slug'], 'name' => $p['name'], 'size' => $line['size'] ?? '', 'qty' => $safeQty, 'unitCents' => $unit, 'lineCents' => $unit * $safeQty];
    }
    if (empty($lineItems)) redirect('/warenkorb');

    $shipping_cents = $subtotal >= $SHIPPING_FREE_ABOVE ? 0 : $SHIPPING_RATE;
    inv_deduct_stock($lineItems);

    $reference = order_create([
        'customer_name'  => "$firstname $lastname",
        'email' => $email, 'phone' => $phone,
        'address' => ['firstname' => $firstname, 'lastname' => $lastname, 'street' => $street, 'housenr' => $housenr, 'zip' => $zip, 'city' => $city, 'country' => $country],
        'items' => $lineItems, 'total_cents' => $subtotal, 'shipping_cents' => $shipping_cents, 'payment_method' => $payment_method,
    ]);

    cart_set([]);
    last_order_set($reference);
    redirect('/bestellung?ref=' . urlencode($reference));
}

$cart = cart_get();
if (empty($cart)) redirect('/warenkorb');

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
    $safeQty = min($line['qty'], max(0, $avail));
    if ($safeQty === 0) continue;
    $subtotal += $unit * $safeQty;
    $items[] = ['name' => $p['name'], 'size' => $line['size'] ?? '', 'qty' => $safeQty, 'unitCents' => $unit, 'lineCents' => $unit * $safeQty, 'img' => $p['images'][0]['src'] ?? null];
}
$shipping  = $subtotal >= $SHIPPING_FREE_ABOVE ? 0 : $SHIPPING_RATE;
$total     = $subtotal + $shipping;
$cartCount = array_sum(array_column($items, 'qty'));

$COUNTRIES = [
    ['DE','Deutschland'],['AT','Österreich'],['CH','Schweiz'],['LI','Liechtenstein'],
    ['LU','Luxemburg'],['BE','Belgien'],['NL','Niederlande'],['FR','Frankreich'],
    ['IT','Italien'],['ES','Spanien'],['PL','Polen'],['CZ','Tschechien'],
    ['DK','Dänemark'],['SE','Schweden'],['NO','Norwegen'],['GB','Großbritannien'],['US','USA'],
];

$errorParam = $_GET['error'] ?? '';
$currentPath = '/kasse';
$pageTitle = 'Kasse';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section">
  <h1 class="checkout-title">Kasse</h1>

  <?php if ($errorParam === 'validation'): ?>
    <div class="alert alert-error" style="max-width:680px">Bitte fülle alle Pflichtfelder korrekt aus.</div>
  <?php elseif ($errorParam === 'card'): ?>
    <div class="alert alert-error" style="max-width:680px">Bitte gib gültige Kreditkartendaten ein.</div>
  <?php endif; ?>

  <div class="checkout-grid">
    <form action="/kasse" method="post" class="checkout-form" id="checkout-form">

      <div class="checkout-section">
        <h2 class="checkout-section-title"><span class="checkout-step">1</span> Kontakt</h2>
        <div class="form-row-2">
          <label class="field"><span>E-Mail *</span><input type="email" name="email" required maxlength="200" autocomplete="email" placeholder="name@beispiel.de"></label>
          <label class="field"><span>Telefon <small class="muted">(optional)</small></span><input type="tel" name="phone" maxlength="30" autocomplete="tel" placeholder="+49 170 1234567"></label>
        </div>
      </div>

      <div class="checkout-section">
        <h2 class="checkout-section-title"><span class="checkout-step">2</span> Lieferadresse</h2>
        <div class="form-row-2">
          <label class="field"><span>Vorname *</span><input type="text" name="firstname" required maxlength="80" autocomplete="given-name" placeholder="Max"></label>
          <label class="field"><span>Nachname *</span><input type="text" name="lastname" required maxlength="80" autocomplete="family-name" placeholder="Mustermann"></label>
        </div>
        <div class="form-row-2" style="grid-template-columns:2fr 1fr">
          <label class="field"><span>Straße *</span><input type="text" name="street" required maxlength="120" autocomplete="address-line1" placeholder="Musterstraße"></label>
          <label class="field"><span>Nr. *</span><input type="text" name="housenr" required maxlength="20" placeholder="12a"></label>
        </div>
        <div class="form-row-2" style="grid-template-columns:1fr 2fr">
          <label class="field"><span>PLZ *</span><input type="text" name="zip" required maxlength="10" autocomplete="postal-code" placeholder="12345"></label>
          <label class="field"><span>Stadt *</span><input type="text" name="city" required maxlength="80" autocomplete="address-level2" placeholder="Berlin"></label>
        </div>
        <label class="field"><span>Land *</span>
          <select name="country" autocomplete="country">
            <?php foreach ($COUNTRIES as [$code, $name]): ?>
              <option value="<?= h($code) ?>"<?= $code === 'DE' ? ' selected' : '' ?>><?= h($name) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="checkout-section">
        <h2 class="checkout-section-title"><span class="checkout-step">3</span> Zahlungsmethode</h2>
        <div class="pay-methods">
          <label class="pay-option" for="pay-card">
            <input type="radio" id="pay-card" name="payment_method" value="kreditkarte" checked>
            <div class="pay-option-inner">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              <div><strong>Kreditkarte</strong><small>Visa, Mastercard, Amex</small></div>
              <div class="pay-check"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 8 6 12 14 4"/></svg></div>
            </div>
          </label>
          <label class="pay-option" for="pay-paypal">
            <input type="radio" id="pay-paypal" name="payment_method" value="paypal">
            <div class="pay-option-inner">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11c0 4 3 6 6 6h1c2 0 4-1.5 4-4s-2-4-4-4H9C7 9 7 7 9 7h5c1 0 2 .5 2.5 1.5"/></svg>
              <div><strong>PayPal</strong><small>Schnell &amp; sicher</small></div>
              <div class="pay-check"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 8 6 12 14 4"/></svg></div>
            </div>
          </label>
          <label class="pay-option" for="pay-vorkasse">
            <input type="radio" id="pay-vorkasse" name="payment_method" value="vorkasse">
            <div class="pay-option-inner">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
              <div><strong>Banküberweisung</strong><small>Zahlung nach Bestellung</small></div>
              <div class="pay-check"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="2 8 6 12 14 4"/></svg></div>
            </div>
          </label>
        </div>

        <div id="pay-kreditkarte" class="pay-section">
          <div class="pay-fields-box">
            <label class="field"><span>Kartennummer *</span><input type="text" name="card_number" maxlength="19" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" data-card-format></label>
            <label class="field"><span>Karteninhaber *</span><input type="text" name="card_name" maxlength="80" autocomplete="cc-name" placeholder="MAX MUSTERMANN" style="text-transform:uppercase"></label>
            <div class="form-row-2">
              <label class="field"><span>Ablaufdatum *</span><input type="text" name="card_expiry" maxlength="5" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/JJ" data-expiry-format></label>
              <label class="field"><span>Sicherheitscode *</span><input type="password" name="card_cvc" maxlength="4" inputmode="numeric" autocomplete="cc-csc" placeholder="CVC"></label>
            </div>
            <p class="pay-hint muted"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="12" height="9" rx="1.5"/><path d="M5 6V4a3 3 0 016 0v2"/></svg> Deine Kartendaten werden verschlüsselt übertragen.</p>
          </div>
        </div>
        <div id="pay-paypal" class="pay-section" hidden>
          <div class="pay-fields-box"><p class="muted" style="font-size:.88rem;line-height:1.6">Nach dem Absenden wirst du zur PayPal-Zahlung weitergeleitet.</p></div>
        </div>
        <div id="pay-vorkasse" class="pay-section" hidden>
          <div class="pay-fields-box">
            <p class="muted" style="font-size:.88rem;line-height:1.6">Nach deiner Bestellung erhältst du unsere Bankverbindung per E-Mail.</p>
            <div class="bank-info">
              <div><span>Empfänger:</span><strong>ABJ Store GmbH</strong></div>
              <div><span>IBAN:</span><strong>DE89 3704 0044 0532 0130 00</strong></div>
              <div><span>BIC:</span><strong>COBADEFFXXX</strong></div>
              <div><span>Bank:</span><strong>Commerzbank</strong></div>
            </div>
          </div>
        </div>
      </div>

      <button class="btn btn-primary btn-block checkout-submit" type="submit">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 12h20"/><path d="M7 16h4"/></svg>
        Kostenpflichtig bestellen · <?= format_price($total, $currency) ?>
      </button>

      <ul class="checkout-trust">
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> SSL-verschlüsselte Übertragung</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Käuferschutz &amp; sichere Zahlung</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg> <?= $shipping === 0 ? 'Kostenloser Versand' : 'Versand: ' . format_price($shipping, $currency) ?></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg> 14 Tage Rückgaberecht</li>
      </ul>
    </form>

    <div class="order-summary">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1.2rem;letter-spacing:-.01em">Bestellübersicht</h2>
      <div style="display:flex;flex-direction:column;gap:.8rem;margin-bottom:1.2rem">
        <?php foreach ($items as $it): ?>
        <div class="checkout-item">
          <?php if ($it['img']): ?><img src="<?= h($it['img']) ?>" alt="<?= h($it['name']) ?>" class="checkout-item-img"><?php endif; ?>
          <div class="checkout-item-info">
            <span class="checkout-item-name"><?= h($it['name']) ?><?= $it['size'] ? ' (' . h($it['size']) . ')' : '' ?></span>
            <span class="muted" style="font-size:.78rem">× <?= $it['qty'] ?></span>
          </div>
          <span class="checkout-item-price"><?= format_price($it['lineCents'], $currency) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="summary-row"><span>Zwischensumme</span><span><?= format_price($subtotal, $currency) ?></span></div>
      <div class="summary-row"><span>Versand</span><span><?= $shipping === 0 ? '<span style="color:#a8e6b8">Kostenlos</span>' : format_price($shipping, $currency) ?></span></div>
      <?php if ($shipping > 0): ?>
      <div class="summary-row" style="font-size:.75rem;color:var(--ink-dim);border-bottom:none;padding-top:0">
        <span>Noch <?= format_price($SHIPPING_FREE_ABOVE - $subtotal, $currency) ?> bis kostenloser Versand</span>
      </div>
      <?php endif; ?>
      <div class="summary-total"><strong>Gesamt</strong><strong><?= format_price($total, $currency) ?></strong></div>
      <p class="muted" style="font-size:.72rem;margin-top:.8rem">inkl. MwSt.</p>
    </div>
  </div>
</main>

<script>
(function(){
  function updatePay(){
    var val=(document.querySelector('[name="payment_method"]:checked')||{}).value||'';
    document.querySelectorAll('.pay-section').forEach(function(s){s.hidden=s.id!=='pay-'+val;});
    document.querySelectorAll('.pay-option').forEach(function(l){var i=l.querySelector('input[type="radio"]');l.classList.toggle('active',i&&i.checked);});
  }
  document.querySelectorAll('[name="payment_method"]').forEach(function(r){r.addEventListener('change',updatePay);});
  updatePay();
  var c=document.querySelector('[data-card-format]');
  if(c)c.addEventListener('input',function(){var v=c.value.replace(/\D/g,'').slice(0,16);c.value=v.replace(/(\d{4})(?=\d)/g,'$1 ');});
  var e=document.querySelector('[data-expiry-format]');
  if(e)e.addEventListener('input',function(){var v=e.value.replace(/\D/g,'').slice(0,4);if(v.length>=3)v=v.slice(0,2)+'/'+v.slice(2);e.value=v;});
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
