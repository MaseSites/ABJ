<?php
require_once __DIR__ . '/lib/bootstrap.php';

$reference      = trim($_GET['ref'] ?? '');
$redirectStatus = trim($_GET['redirect_status'] ?? '');
$contactEmail   = setting_get('contact_email') ?: '';
$cartCount      = cart_count();
$currentPath    = '/bestellung';

$order = $reference ? order_by_ref($reference) : null;

// Mark order as paid when Stripe confirms — three paths:
// 1. redirect_status=succeeded in URL  (3DS redirect flow)
// 2. JS added redirect_status=succeeded after inline confirmation
// 3. Fallback: query Stripe API directly if order still shows 'offen'
if ($order && $order['payment_status'] !== 'bezahlt') {
    $shouldMarkPaid = ($redirectStatus === 'succeeded');

    if (!$shouldMarkPaid && !empty($order['stripe_payment_intent_id']) && stripe_is_configured()) {
        $pi = stripe_retrieve_payment_intent($order['stripe_payment_intent_id']);
        if ($pi && ($pi['status'] ?? '') === 'succeeded') {
            $shouldMarkPaid = true;
        }
    }

    if ($shouldMarkPaid) {
        inv_deduct_stock($order['items']);
        order_update_status($reference, 'in_bearbeitung', 'bezahlt');
        $order['payment_status'] = 'bezahlt';
        $order['status']         = 'in_bearbeitung';
    }
}

$pageTitle = 'Bestellung eingegangen';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section narrow center">

  <?php if ($redirectStatus === 'requires_payment_method'): ?>

    <div class="confirmation-icon confirmation-icon--fail">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    </div>
    <h1>Zahlung fehlgeschlagen</h1>
    <p class="muted">Deine Zahlung konnte leider nicht verarbeitet werden. Bitte versuche es erneut.</p>
    <a class="btn btn-primary" href="<?= url('/kasse.php') ?>">Erneut versuchen</a>

  <?php elseif ($redirectStatus === 'processing'): ?>

    <div class="confirmation-icon">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h1>Bestellung eingegangen!</h1>
    <?php if ($reference): ?><p class="order-ref"><?= h($reference) ?></p><?php endif; ?>
    <p class="muted">Deine Zahlung wird bestätigt. Du erhältst eine E-Mail sobald alles abgeschlossen ist.</p>
    <?php if ($contactEmail): ?>
    <p class="muted">Fragen? <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a></p>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= url('/shop.php') ?>">Weiter einkaufen</a>

  <?php else: ?>

    <div class="confirmation-icon">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h1>Bestellung eingegangen!</h1>

    <?php if ($reference): ?>
    <p class="order-ref"><?= h($reference) ?></p>
    <?php endif; ?>

    <?php if ($order): ?>
      <?php if ($order['payment_status'] === 'bezahlt'): ?>
        <p class="muted">Zahlung erfolgreich &mdash; deine Bestellung wird bearbeitet.</p>
      <?php else: ?>
        <p class="muted">Wir haben deine Bestellung erhalten und melden uns bald.</p>
      <?php endif; ?>

      <?php
        $addr = is_array($order['address']) ? $order['address'] : [];
        $currency = setting_get('currency') ?: 'CHF';
      ?>
      <div class="order-confirm-box">

        <?php if (!empty($order['items'])): ?>
        <div class="order-confirm-section">
          <div class="order-confirm-label">Artikel</div>
          <?php foreach ($order['items'] as $it): ?>
          <div class="order-confirm-row">
            <span><?= h($it['name'] ?? '') ?><?= !empty($it['size']) ? ' (' . h($it['size']) . ')' : '' ?> &times; <?= (int)($it['qty'] ?? 1) ?></span>
            <span><?= format_price((int)($it['lineCents'] ?? 0), $currency) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="order-confirm-section">
          <div class="order-confirm-label">Summe</div>
          <?php if (!empty($order['discount_cents'])): ?>
          <div class="order-confirm-row"><span>Rabatt<?= !empty($order['discount_code']) ? ' (' . h($order['discount_code']) . ')' : '' ?></span><span style="color:#a8e6b8">&minus;<?= format_price((int)$order['discount_cents'], $currency) ?></span></div>
          <?php endif; ?>
          <?php if ($order['shipping_cents'] > 0): ?>
          <div class="order-confirm-row"><span>Versand</span><span><?= format_price((int)$order['shipping_cents'], $currency) ?></span></div>
          <?php else: ?>
          <div class="order-confirm-row"><span>Versand</span><span style="color:#a8e6b8">Kostenlos</span></div>
          <?php endif; ?>
          <div class="order-confirm-row" style="font-weight:700">
            <span>Gesamt</span>
            <span style="color:var(--gold)"><?= format_price((int)$order['total_cents'], $currency) ?></span>
          </div>
        </div>

        <?php if ($addr): ?>
        <div class="order-confirm-section">
          <div class="order-confirm-label">Lieferadresse</div>
          <div style="font-size:.85rem;color:var(--ink-soft);line-height:1.65">
            <?= h(($addr['firstname'] ?? '') . ' ' . ($addr['lastname'] ?? '')) ?><br>
            <?= h(($addr['street'] ?? '') . ' ' . ($addr['housenr'] ?? '')) ?><br>
            <?= h(($addr['zip'] ?? '') . ' ' . ($addr['city'] ?? '')) ?><br>
            <?= h($addr['country'] ?? '') ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($order['payment_method'] === 'vorkasse'): ?>
        <?php
          $bankRecipient = setting_get('bank_recipient') ?: (setting_get('shop_name') ?: 'ABJ Store');
          $bankIban = setting_get('bank_iban') ?: '';
          $bankBic  = setting_get('bank_bic') ?: '';
          $bankName = setting_get('bank_name') ?: '';
        ?>
        <div class="order-confirm-section">
          <div class="order-confirm-label">Zahlung per Banküberweisung</div>
          <?php if ($bankIban): ?>
          <div class="bank-info">
            <div><span>Empfänger</span><strong><?= h($bankRecipient) ?></strong></div>
            <div><span>IBAN</span><strong><?= h($bankIban) ?></strong></div>
            <?php if ($bankBic): ?><div><span>BIC</span><strong><?= h($bankBic) ?></strong></div><?php endif; ?>
            <?php if ($bankName): ?><div><span>Bank</span><strong><?= h($bankName) ?></strong></div><?php endif; ?>
            <div><span>Verwendungszweck</span><strong><?= h($reference) ?></strong></div>
          </div>
          <?php else: ?>
          <p class="muted" style="font-size:.85rem;margin:0">Du erhältst die Bankverbindung per E-Mail. Verwendungszweck: <strong><?= h($reference) ?></strong></p>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      </div>

    <?php else: ?>
      <p class="muted">Wir haben deine Bestellung erhalten und melden uns bald.</p>
    <?php endif; ?>

    <?php if ($contactEmail): ?>
    <p class="muted" style="margin-top:1rem">Fragen? <a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a></p>
    <?php endif; ?>

    <a class="btn btn-primary" href="<?= url('/shop.php') ?>" style="margin-top:1.5rem">Weiter einkaufen</a>

  <?php endif; ?>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
