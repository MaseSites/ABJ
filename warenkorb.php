<?php
require_once __DIR__ . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $cart  = cart_get();
        $index = (int)($_POST['index'] ?? -1);
        $qty   = (int)($_POST['qty'] ?? 0);
        if ($index >= 0 && $index < count($cart)) {
            if ($qty === 0) {
                array_splice($cart, $index, 1);
            } else {
                $line  = $cart[$index];
                $avail = inv_stock_for_variant($line['productId'], $line['size'] ?? '', '');
                $isBO  = ($avail <= 0) && inv_is_back_order($line['productId'], $line['size'] ?? '', '');
                $cart[$index]['qty'] = $isBO ? $qty : min($avail, $qty);
            }
        }
        cart_set($cart);
    } elseif ($action === 'clear') {
        cart_set([]);
    }
    redirect('/warenkorb.php');
}

$currency = setting_get('currency') ?: 'CHF';
$rawCart  = cart_get();
$items    = [];
$total    = 0;
$warnings = [];

foreach ($rawCart as $line) {
    $p = product_by_id($line['productId']);
    if (!$p || !$p['is_active']) { $warnings[] = 'Ein Artikel ist nicht mehr verfügbar.'; continue; }
    $variantRow = inv_by_variant($line['productId'], $line['size'] ?? '', '');
    $unit = ($variantRow && $variantRow['variant_price_cents'] !== null)
        ? (int)$variantRow['variant_price_cents']
        : (int)($p['sale_price_cents'] ?? $p['price_cents']);
    $avail   = inv_stock_for_variant($line['productId'], $line['size'] ?? '', '');
    $isBO    = ($avail <= 0) && inv_is_back_order($line['productId'], $line['size'] ?? '', '');
    $safeQty = $isBO ? $line['qty'] : min($line['qty'], max(0, $avail));
    if ($safeQty === 0 && !$isBO) $warnings[] = '"' . $p['name'] . '"' . ($line['size'] ? ' (' . $line['size'] . ')' : '') . ' ist ausverkauft.';
    $imgSrc = null;
    if ($variantRow) { $imgs = safe_parse($variantRow['images'] ?? '[]', []); $imgSrc = $imgs[0]['src'] ?? null; }
    $imgSrc = $imgSrc ?: ($p['images'][0]['src'] ?? null);
    $total += $unit * $safeQty;
    $items[] = [
        'productId' => $p['id'], 'slug' => $p['slug'], 'name' => $p['name'],
        'size'      => $variantRow ? ($variantRow['title'] ?: $line['size']) : ($line['size'] ?? ''),
        'qty' => $safeQty, 'originalQty' => $line['qty'],
        'unitCents' => $unit, 'lineCents' => $unit * $safeQty,
        'image'     => $imgSrc ?: placeholder_svg($p['name']),
        'maxQty' => $avail, 'isSoldOut' => $safeQty === 0 && !$isBO, 'isBackOrder' => $isBO,
        'wasReduced' => !$isBO && $safeQty < $line['qty'],
    ];
}

$cartCount   = array_sum(array_column($items, 'qty'));
$currentPath = '/warenkorb';
$pageTitle   = 'Warenkorb';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section">
  <h1 class="section-title">Warenkorb</h1>

  <?php if (!empty($_GET['ausverkauft'])): ?>
  <div class="alert alert-error" style="margin-bottom:1.4rem">
    <div>Die Artikel in deinem Warenkorb sind leider ausverkauft oder nicht mehr verfügbar. Bitte passe deinen Warenkorb an.</div>
  </div>
  <?php elseif (!empty($_GET['leer'])): ?>
  <div class="alert alert-error" style="margin-bottom:1.4rem">
    <div>Dein Warenkorb ist leer. Bitte füge zuerst Artikel hinzu.</div>
  </div>
  <?php endif; ?>

  <?php if ($warnings): ?>
  <div class="alert alert-error" style="margin-bottom:1.4rem">
    <?php foreach ($warnings as $w): ?><div><?= h($w) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
    $freeFrom = shipping_free_from_cents();
    if ($freeFrom > 0 && $total > 0 && $total < $freeFrom):
  ?>
  <div class="free-shipping-hint">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="6" width="15" height="12" rx="2"/><path d="M16 10h4l3 3v5h-7z"/><circle cx="6" cy="18" r="2"/><circle cx="19" cy="18" r="2"/></svg>
    <span>Noch <strong><?= format_price($freeFrom - $total, $currency) ?></strong> bis zum kostenlosen Versand.</span>
  </div>
  <?php elseif ($freeFrom > 0 && $total >= $freeFrom): ?>
  <div class="free-shipping-hint">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="20 6 9 17 4 12"/></svg>
    <span><strong>Kostenloser Versand</strong> — dein Bestellwert qualifiziert sich.</span>
  </div>
  <?php endif; ?>

  <?php $activeItems = array_filter($items, function($it) { return $it['qty'] > 0; }); ?>
  <?php if (empty($activeItems)): ?>
    <div class="cart-empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56">
        <path d="M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0"/>
      </svg>
      <p>Dein Warenkorb ist leer.</p>
      <a class="btn btn-primary" href="<?= url('/shop.php') ?>">Zum Shop</a>
    </div>
  <?php else: ?>
    <div class="cart-table">
      <?php foreach ($items as $i => $it): if ($it['qty'] === 0 && !$it['isSoldOut']) continue; ?>
        <div class="cart-row<?= $it['isSoldOut'] ? ' is-soldout' : '' ?>">
          <div class="cart-media"><img src="<?= h($it['image']) ?>" alt="<?= h($it['name']) ?>"></div>
          <div class="cart-info">
            <a href="<?= url('/produkt.php?slug=' . urlencode($it['slug'])) ?>"><strong><?= h($it['name']) ?></strong></a>
            <?php if ($it['size']): ?><span class="muted">Grösse: <?= h($it['size']) ?></span><?php endif; ?>
            <span class="muted"><?= format_price($it['unitCents'], $currency) ?> / Stück</span>
            <?php if ($it['isSoldOut']): ?>
              <span class="cart-soldout-label">Ausverkauft – wird beim Checkout entfernt</span>
            <?php elseif ($it['isBackOrder']): ?>
              <span class="cart-backorder-label">Nicht an Lager – ca. 2 Wochen Lieferzeit</span>
            <?php elseif ($it['wasReduced']): ?>
              <span class="cart-reduced-warning">Menge auf <?= $it['qty'] ?> reduziert (max. verfügbar)</span>
            <?php endif; ?>
          </div>
          <?php if (!$it['isSoldOut']): ?>
          <form class="cart-qty" method="post" action="<?= url('/warenkorb.php') ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="index" value="<?= $i ?>">
            <input type="number" name="qty" value="<?= $it['qty'] ?>" min="1" max="<?= $it['maxQty'] ?>" aria-label="Menge">
            <button class="btn btn-ghost btn-sm" type="submit">OK</button>
          </form>
          <div class="cart-line-total"><?= format_price($it['lineCents'], $currency) ?></div>
          <?php endif; ?>
          <form method="post" action="<?= url('/warenkorb.php') ?>" class="cart-remove-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="index" value="<?= $i ?>">
            <input type="hidden" name="qty" value="0">
            <button class="cart-remove-btn" type="submit" title="Artikel entfernen" aria-label="Entfernen">
              <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="2" y1="2" x2="14" y2="14"/><line x1="14" y1="2" x2="2" y2="14"/></svg>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="cart-summary">
      <form method="post" action="<?= url('/warenkorb.php') ?>">
        <input type="hidden" name="action" value="clear">
        <button class="btn btn-ghost" type="submit">Leeren</button>
      </form>
      <div class="cart-total">
        <span>Gesamt</span>
        <strong><?= format_price($total, $currency) ?></strong>
      </div>
      <a class="btn btn-primary" href="<?= url('/kasse.php') ?>">Zur Kasse</a>
    </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
