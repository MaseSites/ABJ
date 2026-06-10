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
                $cart[$index]['qty'] = min($avail, $qty);
            }
        }
        cart_set($cart);
    } elseif ($action === 'clear') {
        cart_set([]);
    }
    redirect('/warenkorb');
}

$currency = setting_get('currency') ?: 'EUR';
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
    $safeQty = min($line['qty'], max(0, $avail));
    if ($safeQty === 0) $warnings[] = '"' . $p['name'] . '"' . ($line['size'] ? ' (' . $line['size'] . ')' : '') . ' ist ausverkauft.';
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
        'maxQty' => $avail, 'isSoldOut' => $safeQty === 0, 'wasReduced' => $safeQty < $line['qty'],
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

  <?php if ($warnings): ?>
  <div class="alert alert-error" style="margin-bottom:1.4rem">
    <?php foreach ($warnings as $w): ?><div><?= h($w) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php $activeItems = array_filter($items, function($it) { return $it['qty'] > 0; }); ?>
  <?php if (empty($activeItems)): ?>
    <div class="cart-empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56">
        <path d="M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0"/>
      </svg>
      <p>Dein Warenkorb ist leer.</p>
      <a class="btn btn-primary" href="/shop">Zum Shop</a>
    </div>
  <?php else: ?>
    <div class="cart-table">
      <?php foreach ($items as $i => $it): if ($it['qty'] === 0 && !$it['isSoldOut']) continue; ?>
        <div class="cart-row<?= $it['isSoldOut'] ? ' is-soldout' : '' ?>">
          <div class="cart-media"><img src="<?= h($it['image']) ?>" alt="<?= h($it['name']) ?>"></div>
          <div class="cart-info">
            <a href="/produkt/<?= h($it['slug']) ?>"><strong><?= h($it['name']) ?></strong></a>
            <?php if ($it['size']): ?><span class="muted">Grösse: <?= h($it['size']) ?></span><?php endif; ?>
            <span class="muted"><?= format_price($it['unitCents'], $currency) ?> / Stück</span>
            <?php if ($it['isSoldOut']): ?>
              <span class="cart-soldout-label">Ausverkauft – wird beim Checkout entfernt</span>
            <?php elseif ($it['wasReduced']): ?>
              <span class="cart-reduced-warning">Menge auf <?= $it['qty'] ?> reduziert (max. verfügbar)</span>
            <?php endif; ?>
          </div>
          <?php if (!$it['isSoldOut']): ?>
          <form class="cart-qty" method="post" action="/warenkorb">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="index" value="<?= $i ?>">
            <input type="number" name="qty" value="<?= $it['qty'] ?>" min="0" max="<?= $it['maxQty'] ?>" aria-label="Menge">
            <button class="btn btn-ghost btn-sm" type="submit">OK</button>
          </form>
          <div class="cart-line-total"><?= format_price($it['lineCents'], $currency) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="cart-summary">
      <form method="post" action="/warenkorb">
        <input type="hidden" name="action" value="clear">
        <button class="btn btn-ghost" type="submit">Leeren</button>
      </form>
      <div class="cart-total">
        <span>Gesamt</span>
        <strong><?= format_price($total, $currency) ?></strong>
      </div>
      <a class="btn btn-primary" href="/kasse">Zur Kasse</a>
    </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
