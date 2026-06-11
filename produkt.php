<?php
require_once __DIR__ . '/lib/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { http_response_code(404); include __DIR__ . '/404.php'; exit; }

$product = product_by_slug($slug);
if (!$product || !$product['is_active']) { http_response_code(404); include __DIR__ . '/404.php'; exit; }

$invRows = inv_by_product($product['id']);
$inventoryMap = [];
foreach ($invRows as $row) {
    $key = $row['size'] ?: '__nosize__';
    $inventoryMap[$key] = ($inventoryMap[$key] ?? 0) + max(0, $row['stock'] - $row['reserved']);
}

$variants = array_map(function($r) {
    return [
        'id'                  => $r['id'],
        'key'                 => $r['size'] ?: (string)$r['id'],
        'title'               => $r['title'] ?: $r['size'] ?: '',
        'images'              => safe_parse($r['images'] ?? '[]', []),
        'stock'               => max(0, $r['stock'] - $r['reserved']),
        'variant_price_cents' => $r['variant_price_cents'] ?? null,
        'is_default'          => (bool)$r['is_default'],
        'option_values'       => safe_parse($r['option_values'] ?? '[]', []),
    ];
}, $invRows);

// Reconstruct option_groups from inventory if not stored on the product
if (empty($product['option_groups']) && !empty($variants)) {
    $groupsMap = [];
    foreach ($variants as $v) {
        foreach ($v['option_values'] as $ov) {
            $k = $ov['key'] ?? '';
            $val = $ov['value'] ?? '';
            if (!$k || !$val) continue;
            if (!isset($groupsMap[$k])) $groupsMap[$k] = ['key' => $k, 'label' => $ov['label'] ?? $k, 'values' => []];
            if (!in_array($val, $groupsMap[$k]['values'])) $groupsMap[$k]['values'][] = $val;
        }
    }
    $product['option_groups'] = array_values($groupsMap);
}

$related    = products_related($product['category'], $product['id'], 4);
$mainImg    = $product['images'][0]['src'] ?? placeholder_svg($product['name']);
$totalAvail = array_sum($inventoryMap);
$availableStock = empty($invRows) ? (int)$product['stock'] : $totalAvail;
$currency   = setting_get('currency') ?: 'CHF';

$errorMsg = null;
if (($_GET['error'] ?? '') === 'soldout') $errorMsg = 'Dieses Produkt ist leider ausverkauft.';
if (($_GET['error'] ?? '') === 'variant')  $errorMsg = 'Bitte wähle eine Variante.';

$currentPath = '/produkt/' . $slug;
$cartCount   = cart_count();
$pageTitle   = $product['name'];
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section">
  <nav class="breadcrumb">
    <a href="/">Start</a> / <a href="/shop">Shop</a> / <?= h($product['name']) ?>
  </nav>

  <div class="product-detail">
    <div class="product-gallery" data-gallery>
      <div class="gallery-main">
        <img src="<?= h($mainImg) ?>" alt="<?= h($product['name']) ?>" data-gallery-main data-zoomable>
      </div>
      <?php if (count($product['images']) > 1): ?>
      <div class="gallery-thumbs">
        <?php foreach ($product['images'] as $i => $img): ?>
          <button class="thumb<?= $i === 0 ? ' active' : '' ?>" data-src="<?= h($img['src']) ?>">
            <img src="<?= h($img['src']) ?>" alt="" loading="lazy">
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="product-info">
      <span class="product-cat-label"><?= h($product['category']) ?></span>
      <h1 class="product-detail-name"><?= h($product['name']) ?></h1>

      <div class="product-price big">
        <?php if ($product['sale_price_cents']): ?>
          <span class="price-sale"><?= format_price((int)$product['sale_price_cents'], $currency) ?></span>
          <span class="price-old"><?= format_price((int)$product['price_cents'], $currency) ?></span>
          <span class="badge-sale">-<?= discount_percent((int)$product['price_cents'], (int)$product['sale_price_cents']) ?>%</span>
        <?php else: ?>
          <span class="price-now"><?= format_price((int)$product['price_cents'], $currency) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($totalAvail > 0 && $totalAvail <= 5): ?>
        <span class="stock-urgency">Nur noch <?= $totalAvail ?> verfügbar</span>
      <?php endif; ?>

      <?php if ($errorMsg): ?>
        <div class="alert alert-error" style="margin-bottom:1rem"><?= h($errorMsg) ?></div>
      <?php endif; ?>

      <form action="/api/cart-add.php" method="post" data-ajax-add data-product-id="<?= (int)$product['id'] ?>">
        <input type="hidden" name="productId" value="<?= (int)$product['id'] ?>">
        <input type="hidden" name="slug" value="<?= h($product['slug']) ?>">

        <?php if (!empty($variants)): ?>
          <div id="variant-row"></div>
        <?php endif; ?>
        <input type="hidden" name="size" id="size-hidden">

        <label class="field-label" for="qty">Menge</label>
        <input class="qty-input" type="number" id="qty" name="qty" value="1" min="1" max="99">

        <?php if ($availableStock > 0): ?>
          <button class="btn btn-primary btn-block" type="submit" style="max-width:100%">In den Warenkorb</button>
        <?php else: ?>
          <button class="btn btn-primary btn-block" disabled style="max-width:100%">Ausverkauft</button>
        <?php endif; ?>
      </form>

      <div class="product-trust-row">
        <span><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 1l1.8 3.6L14 5.5l-3 2.9.7 4.1L8 10.4 4.3 12.5l.7-4.1L2 5.5l4.2-.9z"/></svg> Kostenloser Versand</span>
        <span><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="6" width="12" height="9" rx="1.5"/><path d="M5 6V4a3 3 0 016 0v2"/></svg> Sicherer Checkout</span>
        <span><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 8a6 6 0 1012 0A6 6 0 002 8z"/><path d="M8 5v3l2 2"/></svg> 14 Tage Rückgabe</span>
      </div>

      <?php if ($product['description']): ?>
        <div class="product-description"><?= $product['description'] ?></div>
      <?php endif; ?>
    </div>
  </div>

  <script id="product-variants-data" type="application/json"><?= json_encode($variants) ?></script>
  <script id="product-option-groups-data" type="application/json"><?= json_encode($product['option_groups']) ?></script>
  <script id="product-price-data" type="application/json"><?= json_encode(['price_cents' => $product['price_cents'], 'sale_price_cents' => $product['sale_price_cents']]) ?></script>
  <span data-track-view="<?= (int)$product['id'] ?>" hidden></span>

  <?php if (!empty($related)): ?>
  <section class="section">
    <h2 class="section-title">Ähnliche Produkte</h2>
    <div class="product-grid">
      <?php foreach ($related as $rp): ?>
        <article class="product-card">
          <a href="/produkt/<?= h($rp['slug']) ?>">
            <img src="<?= h($rp['images'][0]['src'] ?? placeholder_svg($rp['name'])) ?>" alt="<?= h($rp['name']) ?>" loading="lazy">
            <div><?= h($rp['name']) ?></div>
            <div><?= format_price((int)($rp['sale_price_cents'] ?? $rp['price_cents']), $currency) ?></div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
