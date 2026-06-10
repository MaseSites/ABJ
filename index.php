<?php
require_once __DIR__ . '/lib/bootstrap.php';

$currentPath = '/';
$cartCount = cart_count();
$saleEndsAt = setting_get('sale_ends_at') ?: '2026-12-31T23:59:59';
$heroImage = setting_get('hero_image') ?: '/img/img.png';
$heroTitle = setting_get('hero_title') ?: 'Premium Streetwear &amp; Designer';
$heroSubtitle = setting_get('hero_subtitle') ?: 'Kuratierte, authentifizierte Pieces der gefragtesten Marken.';
$announcement = setting_get('announcement') ?: '';
$membersCount = setting_get('members_count') ?: '20000';
$ratingsCount = setting_get('ratings_count') ?: '1000';

$featured = array_slice(products_shuffle(products_bestsellers(12)), 0, 5);

$BRANDS = ['Nike', 'Adidas', 'Stone Island', 'Moncler', 'C.P. Company', 'Ralph Lauren', 'Carhartt', 'Stussy', 'Trapstar', 'The North Face', 'Lacoste', 'Diesel'];
$REVIEWS = [
    ['n' => 'Lena M.',  't' => 'Sehr sauber verpackt, gute Qualität und die Teile wirkten wirklich handverlesen.', 'r' => 5],
    ['n' => 'Jonas K.', 't' => 'Schneller Versand und die Auswahl hat genau zu meinem Style gepasst.', 'r' => 5],
    ['n' => 'Sara B.',  't' => 'Schöne Vintage-Pieces, gepflegter Zustand und kein komischer Second-Hand-Geruch.', 'r' => 5],
    ['n' => 'Milan R.', 't' => 'Unkomplizierte Bestellung, faire Preise und der Hoodie saß direkt perfekt.', 'r' => 5],
];

include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main">
  <!-- Hero / Countdown -->
  <section class="hero" id="hero" data-countdown="<?= h($saleEndsAt) ?>">
    <?php if ($heroImage): ?>
    <div class="hero-media" aria-hidden="true">
      <img src="<?= h($heroImage) ?>" alt="" class="hero-bg-img">
    </div>
    <?php endif; ?>
    <div class="hero-overlay" aria-hidden="true"></div>

    <div class="hero-inner">
      <div class="hero-inner-wrap">
        <div>
          <span class="hero-sale-pill">ABJ Collection</span>
          <h1 class="hero-title">Defined<br>by the<br><em>Detail.</em></h1>
          <div class="hero-countdown">
            <div><strong data-cd="days">00</strong><span>Tage</span></div>
            <div><strong data-cd="hours">00</strong><span>Std</span></div>
            <div><strong data-cd="mins">00</strong><span>Min</span></div>
            <div><strong data-cd="secs">00</strong><span>Sek</span></div>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.6rem;padding-bottom:.4rem">
          <a href="/shop" class="btn btn-line">Shop Now</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Brand Marquee -->
  <section class="brand-logo-marquee" aria-label="Marken">
    <div class="brand-logo-track">
      <?php for ($i = 0; $i < 2; $i++): foreach ($BRANDS as $brand): ?>
        <span class="brand-logo-word"><?= h($brand) ?></span>
      <?php endforeach; endfor; ?>
    </div>
  </section>

  <!-- Bestseller -->
  <?php if (!empty($featured)): ?>
  <section class="container section">
    <span class="section-title-label">Sortiment</span>
    <h2 class="section-title">Bestseller</h2>
    <div class="product-grid">
      <?php foreach ($featured as $p): ?>
        <?php include __DIR__ . '/partials/product-card.php'; ?>
      <?php endforeach; ?>
      <?php $fillers = (5 - count($featured) % 5) % 5; for ($i = 0; $i < $fillers; $i++): ?>
        <div class="product-card-filler"></div>
      <?php endfor; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Reviews -->
  <section class="container section customer-reviews">
    <span class="section-title-label">Kundenstimmen</span>
    <h2 class="section-title">Was unsere Kunden sagen</h2>
    <div class="review-strip">
      <?php foreach ($REVIEWS as $rev): ?>
      <article class="review-card">
        <div class="review-stars" aria-label="<?= $rev['r'] ?> von 5 Sternen"><?= str_repeat('★', $rev['r']) ?></div>
        <p><?= h($rev['t']) ?></p>
        <div class="review-author">
          <span><?= h(mb_substr($rev['n'], 0, 1)) ?></span>
          <strong><?= h($rev['n']) ?></strong>
          <em>verifiziert</em>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
