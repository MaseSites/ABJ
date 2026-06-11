<?php
require_once __DIR__ . '/lib/bootstrap.php';

$cart = cart_get();
if (empty($cart)) redirect('/warenkorb');

$currency            = setting_get('currency') ?: 'EUR';
$SHIPPING_FREE_ABOVE = 4900;
$SHIPPING_RATE       = 490;

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

if (empty($items)) redirect('/warenkorb');

$shipping = $subtotal >= $SHIPPING_FREE_ABOVE ? 0 : $SHIPPING_RATE;
$total    = $subtotal + $shipping;

$stripeConfigured = stripe_is_configured();
$stripePk         = stripe_publishable_key();

$COUNTRIES = [
    ['DE','Deutschland'],['AT','Österreich'],['CH','Schweiz'],['LI','Liechtenstein'],
    ['LU','Luxemburg'],['BE','Belgien'],['NL','Niederlande'],['FR','Frankreich'],
    ['IT','Italien'],['ES','Spanien'],['PL','Polen'],['CZ','Tschechien'],
    ['DK','Dänemark'],['SE','Schweden'],['NO','Norwegen'],['GB','Großbritannien'],['US','USA'],
];

$currentPath = '/kasse';
$pageTitle   = 'Kasse';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section">

  <a href="/warenkorb" class="checkout-back">
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
      data-stripe-pk="<?= h($stripePk) ?>"
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
              autocomplete="email" placeholder="name@beispiel.de">
          </label>
          <label class="field">
            <span>Telefon <small class="muted">(optional)</small></span>
            <input type="tel" name="phone" maxlength="30"
              autocomplete="tel" placeholder="+49 170 1234567">
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
              autocomplete="given-name" placeholder="Max">
          </label>
          <label class="field">
            <span>Nachname *</span>
            <input type="text" name="lastname" required maxlength="80"
              autocomplete="family-name" placeholder="Mustermann">
          </label>
        </div>
        <div class="form-row-2" style="grid-template-columns:2fr 1fr">
          <label class="field">
            <span>Straße *</span>
            <input type="text" name="street" required maxlength="120"
              autocomplete="address-line1" placeholder="Musterstraße">
          </label>
          <label class="field">
            <span>Nr. *</span>
            <input type="text" name="housenr" required maxlength="20" placeholder="12a">
          </label>
        </div>
        <div class="form-row-2" style="grid-template-columns:1fr 2fr">
          <label class="field">
            <span>PLZ *</span>
            <input type="text" name="zip" required maxlength="10"
              autocomplete="postal-code" placeholder="12345">
          </label>
          <label class="field">
            <span>Stadt *</span>
            <input type="text" name="city" required maxlength="80"
              autocomplete="address-level2" placeholder="Berlin">
          </label>
        </div>
        <label class="field">
          <span>Land *</span>
          <select name="country" autocomplete="country">
            <?php foreach ($COUNTRIES as [$code, $name]): ?>
              <option value="<?= h($code) ?>"<?= $code === 'DE' ? ' selected' : '' ?>><?= h($name) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <!-- 3 Payment -->
      <div class="checkout-section">
        <h2 class="checkout-section-title">
          <span class="checkout-step">3</span> Zahlung
        </h2>

        <?php if ($stripeConfigured): ?>

          <div class="pay-methods">
            <label class="pay-option active" for="pay-stripe">
              <input type="radio" id="pay-stripe" name="payment_method" value="stripe" checked>
              <div class="pay-option-inner">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <div>
                  <strong>Kreditkarte / Debitkarte</strong>
                  <small>Visa, Mastercard, Amex &middot; via Stripe</small>
                </div>
                <div class="pay-check"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="2 8 6 12 14 4"/></svg></div>
              </div>
            </label>
            <label class="pay-option" for="pay-vorkasse">
              <input type="radio" id="pay-vorkasse" name="payment_method" value="vorkasse">
              <div class="pay-option-inner">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>
                <div>
                  <strong>Banküberweisung</strong>
                  <small>Zahlung nach Bestellung</small>
                </div>
                <div class="pay-check"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="2 8 6 12 14 4"/></svg></div>
              </div>
            </label>
          </div>

          <div id="pay-section-stripe" class="pay-section">
            <div class="pay-fields-box">
              <div class="stripe-element-wrap">
                <div id="stripe-loading" class="stripe-element-loading">Zahlungsformular wird geladen…</div>
                <div id="stripe-payment-element"></div>
              </div>
              <p class="pay-hint">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="12" height="9" rx="1.5"/><path d="M5 6V4a3 3 0 016 0v2"/></svg>
                SSL-verschlüsselt &middot; powered by Stripe
              </p>
            </div>
          </div>

          <div id="pay-section-vorkasse" class="pay-section" hidden>
            <div class="pay-fields-box">
              <p class="muted" style="font-size:.85rem;margin:0 0 .8rem">Nach deiner Bestellung erhältst du unsere Bankverbindung per E-Mail.</p>
              <div class="bank-info">
                <div><span>Empfänger</span><strong>ABJ Store GmbH</strong></div>
                <div><span>IBAN</span><strong>DE89 3704 0044 0532 0130 00</strong></div>
                <div><span>BIC</span><strong>COBADEFFXXX</strong></div>
                <div><span>Bank</span><strong>Commerzbank</strong></div>
              </div>
            </div>
          </div>

        <?php else: ?>

          <input type="hidden" name="payment_method" value="vorkasse">
          <div class="stripe-not-configured">
            <strong>Online-Zahlung</strong> wird in Kürze eingerichtet. Derzeit nur per Banküberweisung.
          </div>
          <div class="pay-fields-box" style="margin-top:.8rem">
            <p class="muted" style="font-size:.85rem;margin:0 0 .8rem">Nach deiner Bestellung erhältst du unsere Bankverbindung per E-Mail.</p>
            <div class="bank-info">
              <div><span>Empfänger</span><strong>ABJ Store GmbH</strong></div>
              <div><span>IBAN</span><strong>DE89 3704 0044 0532 0130 00</strong></div>
              <div><span>BIC</span><strong>COBADEFFXXX</strong></div>
              <div><span>Bank</span><strong>Commerzbank</strong></div>
            </div>
          </div>

        <?php endif; ?>

      </div>

      <button class="btn btn-primary btn-block checkout-submit" type="submit">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="12" height="9" rx="1.5"/><path d="M5 6V4a3 3 0 016 0v2"/></svg>
        <span class="checkout-submit-text">Kostenpflichtig bestellen &middot; <?= format_price($total, $currency) ?></span>
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
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?= $shipping === 0 ? 'Kostenloser Versand' : 'Versand: ' . format_price($shipping, $currency) ?>
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
          14 Tage Rückgaberecht
        </li>
      </ul>

    </form>

    <!-- Right: order summary -->
    <div class="order-summary">
      <h2 style="font-size:.72rem;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:var(--ink-dim);margin-bottom:1.2rem">Bestellübersicht</h2>

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

      <div class="summary-row"><span>Zwischensumme</span><span><?= format_price($subtotal, $currency) ?></span></div>
      <div class="summary-row">
        <span>Versand</span>
        <?php if ($shipping === 0): ?>
          <span style="color:#a8e6b8">Kostenlos</span>
        <?php else: ?>
          <span><?= format_price($shipping, $currency) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($shipping > 0): ?>
      <div class="summary-row" style="font-size:.72rem;color:var(--ink-dim);border-bottom:none;padding-top:0">
        <span>Noch <?= format_price($SHIPPING_FREE_ABOVE - $subtotal, $currency) ?> bis kostenlosem Versand</span>
      </div>
      <?php endif; ?>
      <div class="summary-total">
        <strong>Gesamt</strong>
        <strong style="color:var(--gold)"><?= format_price($total, $currency) ?></strong>
      </div>
      <p class="muted" style="font-size:.72rem;margin-top:.6rem">inkl. MwSt.</p>
    </div>

  </div>
</main>

<?php if ($stripePk): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>
<script>
(function () {
  var PK       = <?= json_encode($stripePk) ?>;
  var TOTAL    = <?= (int)$total ?>;
  var CURRENCY = <?= json_encode(strtolower($currency)) ?>;

  var stripe   = (PK && typeof Stripe !== 'undefined') ? Stripe(PK) : null;
  var elements = null;

  var APPEARANCE = {
    theme: 'night',
    variables: {
      colorPrimary:       '#c4a35a',
      colorBackground:    '#0e0e13',
      colorText:          '#e8e8ed',
      colorTextSecondary: '#7b7b8e',
      colorDanger:        '#e05c48',
      fontFamily:         '"Inter", system-ui, sans-serif',
      borderRadius:       '0px',
      spacingUnit:        '3px',
    },
    rules: {
      '.Input': { border: '1px solid #2c2c38', padding: '12px 14px', background: '#0e0e13' },
      '.Input:focus': { border: '1px solid rgba(255,255,255,.3)', boxShadow: '0 0 0 2px rgba(196,163,90,.12)' },
      '.Label': { color: '#7b7b8e', textTransform: 'uppercase', letterSpacing: '.1em', fontSize: '11px', fontWeight: '600' },
      '.Tab': { border: '1px solid #2c2c38', background: '#0e0e13' },
      '.Tab--selected': { border: '1px solid rgba(196,163,90,.6)', background: '#16161d' },
      '.Tab:hover': { background: '#16161d' },
    },
  };

  function mountStripeElements() {
    if (!stripe || elements) return;
    elements = stripe.elements({ mode: 'payment', amount: TOTAL, currency: CURRENCY, appearance: APPEARANCE, paymentMethodTypes: ['card'] });
    var payEl = elements.create('payment');
    payEl.mount('#stripe-payment-element');
    payEl.on('ready', function () {
      var loader = document.getElementById('stripe-loading');
      if (loader) loader.style.display = 'none';
    });
  }

  if (stripe) mountStripeElements();

  document.querySelectorAll('[name="payment_method"]').forEach(function (r) {
    r.addEventListener('change', function () {
      var val = r.value;
      document.querySelectorAll('.pay-option').forEach(function (l) {
        l.classList.toggle('active', l.querySelector('input[type="radio"]').checked);
      });
      var sS = document.getElementById('pay-section-stripe');
      var sV = document.getElementById('pay-section-vorkasse');
      if (sS) sS.hidden = (val !== 'stripe');
      if (sV) sV.hidden = (val !== 'vorkasse');
      if (val === 'stripe') mountStripeElements();
    });
  });

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
      var data;
      try {
        var res = await fetch('/api/checkout', { method: 'POST', body: fd });
        data = await res.json().catch(function () { return { ok: false, error: 'Server-Fehler (ungültige Antwort)' }; });
      } catch (err) {
        showError('Netzwerkfehler. Bitte überprüfe deine Verbindung und versuche es erneut.');
        setLoading(false);
        return;
      }

      if (!data || !data.ok) {
        showError((data && data.error) ? data.error : 'Fehler bei der Bestellung. Bitte versuche es erneut.');
        setLoading(false);
        return;
      }

      if (data.redirect) {
        window.location.href = data.redirect;
        return;
      }

      if (!stripe || !elements) {
        showError('Zahlungsformular nicht geladen. Bitte lade die Seite neu.');
        setLoading(false);
        return;
      }

      if (data.client_secret) {
        var result;
        try {
          result = await stripe.confirmPayment({
            elements:     elements,
            clientSecret: data.client_secret,
            confirmParams: {
              return_url: window.location.origin + '/bestellung?ref=' + encodeURIComponent(data.order_ref),
            },
            redirect: 'if_required',
          });
        } catch (stripeErr) {
          showError((stripeErr && stripeErr.message) ? stripeErr.message : 'Zahlungsfehler. Bitte versuche es erneut.');
          setLoading(false);
          return;
        }

        if (result && result.error) {
          showError(result.error.message || 'Zahlung fehlgeschlagen. Bitte versuche es erneut.');
          setLoading(false);
          return;
        }

        if (result && result.paymentIntent && result.paymentIntent.status === 'succeeded') {
          window.location.href = '/bestellung?ref=' + encodeURIComponent(data.order_ref) + '&redirect_status=succeeded';
          return;
        }

        // Stripe redirected (3DS etc.) — browser navigates away, nothing to do here
        return;
      }

      showError('Fehler: Kein Zahlungstoken erhalten. Bitte versuche es erneut.');
      setLoading(false);
    });
  }
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
