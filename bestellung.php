<?php
require_once __DIR__ . '/lib/bootstrap.php';

$reference      = trim($_GET['ref'] ?? '');
$redirectStatus = trim($_GET['redirect_status'] ?? '');
$contactEmail   = setting_get('contact_email') ?: '';
$cartCount      = cart_count();
$currentPath    = '/bestellung';

$order = $reference ? order_by_ref($reference) : null;
$isAnfrage = ($order && order_is_request($order));
$messages = $order ? order_messages_by_ref($reference) : [];

$pageTitle = $isAnfrage ? 'Anfrage erhalten' : 'Bestellung eingegangen';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section narrow center" style="position:relative">

  <?php if ($order): ?>
    <?php if ($isAnfrage): ?>
      <span class="confirm-pay-badge<?= (int)$order['total_cents'] > 0 ? ' is-paid' : '' ?>"><?= (int)$order['total_cents'] > 0 ? 'Preis steht' : 'In Prüfung' ?></span>
    <?php else: ?>
      <span class="confirm-pay-badge<?= $order['payment_status'] === 'bezahlt' ? ' is-paid' : '' ?>"><?= h(order_payment_label($order)) ?></span>
    <?php endif; ?>
  <?php endif; ?>

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
    <h1><?= $isAnfrage ? 'Anfrage erhalten!' : 'Bestellung eingegangen!' ?></h1>

    <?php if ($reference): ?>
    <p class="order-ref"><?= h($reference) ?></p>
    <?php endif; ?>

    <?php if ($order): ?>
      <?php if ($isAnfrage): ?>
        <p class="muted">Wir prüfen die Verfügbarkeit und setzen den Preis. Du findest die Anfrage in deinem <a href="<?= url('/konto.php?tab=orders') ?>" style="color:var(--accent-3)">Profil</a> - sobald der Preis steht, kannst du bestellen.</p>
      <?php elseif ($order['payment_status'] === 'bezahlt'): ?>
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
          <div class="order-confirm-label"><?= $isAnfrage ? 'Deine Anfrage' : 'Artikel' ?></div>
          <?php foreach ($order['items'] as $it): ?>
          <div class="order-confirm-row">
            <span style="display:flex;align-items:center;gap:.6rem">
              <?php if (!empty($it['image'])): ?><img src="<?= h($it['image']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:7px;border:1px solid var(--line)"><?php endif; ?>
              <span><?= h($it['name'] ?? '') ?><?= !empty($it['size']) ? ' (' . h($it['size']) . ')' : '' ?><?= (int)($it['qty'] ?? 1) > 1 ? ' &times; ' . (int)$it['qty'] : '' ?></span>
            </span>
            <span><?= (int)($it['lineCents'] ?? 0) > 0 ? format_price((int)$it['lineCents'], $currency) : '<span class="muted">Preis folgt</span>' ?></span>
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
            <span style="color:var(--gold)"><?= (int)$order['total_cents'] > 0 ? format_price((int)$order['total_cents'], $currency) : 'Preis folgt' ?></span>
          </div>
          <?php if ((int)($order['amount_paid_cents'] ?? 0) > 0 && $order['payment_status'] !== 'bezahlt'): ?>
          <div class="order-confirm-row"><span>Bereits bezahlt</span><span style="color:#a8e6b8">&minus;<?= format_price((int)$order['amount_paid_cents'], $currency) ?></span></div>
          <div class="order-confirm-row" style="font-weight:700"><span>Noch offen</span><span style="color:#e6c37e"><?= format_price(order_amount_due($order), $currency) ?></span></div>
          <?php endif; ?>
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

        <div class="order-confirm-section">
          <div class="order-confirm-label">Posteingang</div>
          <?php if (empty($messages)): ?>
            <p class="muted" style="margin:0;font-size:.9rem">Sobald wir die Bestellung aktualisieren oder Bemerkungen hinterlegen, erscheinen sie hier.</p>
          <?php else: foreach ($messages as $m): ?>
            <div class="order-confirm-row" style="align-items:flex-start;gap:1rem">
              <div style="flex:1">
                <strong><?= h($m['subject'] ?: ($m['is_system'] ? 'System' : 'Nachricht')) ?></strong>
                <div class="muted" style="font-size:.78rem;margin:.2rem 0 .4rem">
                  <?= h($m['author_name'] ?: $m['author_role']) ?> - <?= h(substr($m['created_at'], 0, 16)) ?>
                </div>
                <div style="white-space:pre-line;font-size:.9rem;color:var(--ink-soft)"><?= h($m['body']) ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

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
