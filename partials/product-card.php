<?php
// $p must be set before including this partial
$_imgSrc = (!empty($p['images'][0]['src'])) ? $p['images'][0]['src'] : null;
$_hasSizes = !empty($p['sizes']) || inv_has_variants((int)$p['id']);
$_priceCents = (int)$p['price_cents'];
$_saleCents  = isset($p['sale_price_cents']) && $p['sale_price_cents'] ? (int)$p['sale_price_cents'] : null;
$_currency   = setting_get('currency') ?: 'CHF';
$_stock      = inv_total_stock((int)$p['id']);
?>
<article class="product-card" data-product-card>
  <div class="product-card-media">
    <a href="<?= url('/produkt.php?slug=' . urlencode($p['slug'])) ?>" class="media-link" aria-label="<?= h($p['name']) ?>">
      <?php if ($_imgSrc): ?>
      <img src="<?= h($_imgSrc) ?>" alt="<?= h($p['name']) ?>" loading="lazy">
      <?php else: ?>
      <img src="<?= placeholder_svg($p['name']) ?>" alt="" loading="lazy">
      <?php endif; ?>
      <span class="card-shine" aria-hidden="true"></span>
    </a>
    <?php if ($_saleCents): ?>
      <span class="badge-sale">-<?= discount_percent($_priceCents, $_saleCents) ?>%</span>
    <?php endif; ?>
    <button class="wish-btn" data-wish="<?= (int)$p['id'] ?>" aria-label="Zur Wunschliste" title="Merken">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/>
      </svg>
    </button>
    <?php if ($_stock > 0): ?>
    <button class="quick-add" data-quick-add data-id="<?= (int)$p['id'] ?>" data-slug="<?= h($p['slug']) ?>" data-has-sizes="<?= $_hasSizes ? '1' : '0' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
      <span>In den Warenkorb</span>
    </button>
    <?php endif; ?>
  </div>
  <div class="product-card-body">
    <span class="product-cat"><?= h($p['category']) ?></span>
    <h3 class="product-name"><a href="<?= url('/produkt.php?slug=' . urlencode($p['slug'])) ?>"><?= h($p['name']) ?></a></h3>
    <div class="product-price">
      <?php if ($_saleCents): ?>
        <span class="price-sale"><?= format_price($_saleCents, $_currency) ?></span>
        <span class="price-old"><?= format_price($_priceCents, $_currency) ?></span>
      <?php else: ?>
        <span class="price-now"><?= format_price($_priceCents, $_currency) ?></span>
      <?php endif; ?>
      <?php if ($_stock <= 0 && !empty($p['back_order'])): ?>
        <span class="badge-backorder inline">Nicht an Lager</span>
      <?php elseif ($_stock <= 0): ?>
        <span class="badge-soldout inline">Ausverkauft</span>
      <?php endif; ?>
    </div>
  </div>
</article>
