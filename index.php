<?php
require_once __DIR__ . '/lib/bootstrap.php';

$currentPath  = '/';
$cartCount    = cart_count();
$saleEndsAt   = setting_get('sale_ends_at') ?: '2026-12-31T23:59:59';
$heroImage    = setting_get('hero_image') ?: '/img/img.png';
if ($heroImage && $heroImage[0] === '/') $heroImage = url($heroImage);
$heroSubtitle = setting_get('hero_subtitle') ?: 'Kuratierte, authentifizierte Pieces der gefragtesten Marken.';
$membersCount = (int)(setting_get('members_count') ?: 20000);
$ratingsCount = (int)(setting_get('ratings_count') ?: 1000);

// Bestseller – Fallback auf neueste Produkte, damit die Startseite nie leer ist
$featured = products_bestsellers(8);
if (count($featured) < 4) {
    $featured = array_slice(products_list_public(), 0, 8);
}
$featured = array_slice($featured, 0, 8);

$categories = products_categories();
// Kategorie-Kacheln mit Bild des neuesten Produkts der Kategorie
$catTiles = [];
foreach (array_slice($categories, 0, 6) as $c) {
    $ps = products_list_public($c);
    $catTiles[] = ['name' => $c, 'image' => $ps[0]['images'][0]['src'] ?? null, 'count' => count($ps)];
}

$BRANDS = ['Nike', 'Adidas', 'Stone Island', 'Moncler', 'C.P. Company', 'Ralph Lauren', 'Carhartt', 'Stussy', 'Trapstar', 'The North Face', 'Lacoste', 'Diesel'];
$REVIEWS = [
    ['n' => 'Lena M.',  't' => 'Sehr sauber verpackt, gute Qualität und die Teile wirkten wirklich handverlesen.', 'r' => 5],
    ['n' => 'Jonas K.', 't' => 'Schneller Versand und die Auswahl hat genau zu meinem Style gepasst.', 'r' => 5],
    ['n' => 'Sara B.',  't' => 'Schöne Vintage-Pieces, gepflegter Zustand und kein komischer Second-Hand-Geruch.', 'r' => 5],
    ['n' => 'Milan R.', 't' => 'Unkomplizierte Bestellung, faire Preise und der Hoodie sass direkt perfekt.', 'r' => 5],
];
$FAQ = [
    ['q' => 'Sind alle Artikel authentisch?', 'a' => 'Ja. Jedes Piece wird vor dem Verkauf von uns geprüft und authentifiziert. Wir verkaufen ausschliesslich Originalware.'],
    ['q' => 'Wie lange dauert der Versand?', 'a' => 'Innerhalb der Schweiz 2–4 Werktage, international 5–10 Werktage. Du erhältst eine Versandbestätigung per E-Mail.'],
    ['q' => 'Kann ich Artikel zurückgeben?', 'a' => 'Ja, du hast 14 Tage Rückgaberecht ab Erhalt der Ware. Die Artikel müssen ungetragen und im Originalzustand sein.'],
    ['q' => 'Welche Zahlungsmethoden gibt es?', 'a' => 'Kredit-/Debitkarte (Visa, Mastercard, Amex) sowie Banküberweisung (Vorkasse).'],
];

include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main">

  <!-- Hero -->
  <section class="hero" id="hero" data-countdown="<?= h($saleEndsAt) ?>">
    <?php if ($heroImage): ?>
    <div class="hero-media" aria-hidden="true">
      <img src="<?= h($heroImage) ?>" alt="" class="hero-bg-img" fetchpriority="high">
    </div>
    <?php endif; ?>
    <div class="hero-overlay" aria-hidden="true"></div>

    <div class="hero-inner">
      <div class="hero-inner-wrap">
        <div>
          <div class="hero-sale-pill">
            <span>Summer Sale</span>
            <span class="hero-sale-pct">bis zu &minus;70&thinsp;%</span>
          </div>
          <h1 class="hero-title">Premium Style<br>zum <em>besten Preis.</em></h1>
          <p class="hero-sub"><?= h($heroSubtitle) ?></p>
          <div class="hero-actions">
            <a href="<?= url('/shop.php?sale=1') ?>" class="btn btn-gold-line">Zum Sale</a>
            <a href="<?= url('/shop.php') ?>" class="btn btn-gold">Alle Produkte</a>
          </div>
          <div class="hero-rating">
            <span class="stars" aria-hidden="true">★★★★★</span>
            <span><?= number_format($ratingsCount, 0, '.', '\'') ?>+ Bewertungen · <?= number_format($membersCount, 0, '.', '\'') ?>+ Kunden</span>
          </div>
        </div>

        <div class="hero-cd-card">
          <p class="hero-cd-label">Angebot endet in</p>
          <div class="hero-countdown">
            <div class="cd-box"><strong data-cd="days">00</strong><span>Tage</span></div>
            <span class="cd-sep">:</span>
            <div class="cd-box"><strong data-cd="hours">00</strong><span>Std</span></div>
            <span class="cd-sep">:</span>
            <div class="cd-box"><strong data-cd="mins">00</strong><span>Min</span></div>
            <span class="cd-sep">:</span>
            <div class="cd-box"><strong data-cd="secs">00</strong><span>Sek</span></div>
          </div>
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

  <!-- Trust / USP -->
  <div class="container">
    <div class="trust-strip">
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
        <div><strong>Authentizität geprüft</strong><span>Jedes Piece wird vor dem Versand verifiziert.</span></div>
      </div>
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="1" y="6" width="15" height="12" rx="2"/><path d="M16 10h4l3 3v5h-7z"/><circle cx="6" cy="18" r="2"/><circle cx="19" cy="18" r="2"/></svg>
        <div><strong>Versicherter Versand</strong><span>Schnell &amp; sicher — in der Schweiz und international.</span></div>
      </div>
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
        <div><strong>14 Tage Rückgabe</strong><span>Unkompliziert zurückgeben, wenn etwas nicht passt.</span></div>
      </div>
      <div class="trust-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <div><strong>Sichere Zahlung</strong><span>SSL-verschlüsselt — Karte oder Überweisung.</span></div>
      </div>
    </div>
  </div>

  <?php if (!empty($catTiles)): ?>
  <!-- Kategorien -->
  <section class="container section" style="padding-bottom:0">
    <span class="section-title-label">Kollektionen</span>
    <div class="section-head-row">
      <h2 class="section-title">Nach Kategorie shoppen</h2>
      <a class="link-arrow" href="<?= url('/shop.php') ?>">Alle ansehen <span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="collection-rail" data-rail>
      <button class="rail-btn rail-prev" type="button" data-rail-prev aria-label="Zurück" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <div class="collection-track" data-rail-track>
        <?php foreach ($catTiles as $tile): ?>
        <a class="collection-card" href="<?= url('/shop.php?category=' . urlencode($tile['name'])) ?>">
          <?php if ($tile['image']): ?><img src="<?= h($tile['image']) ?>" alt="" loading="lazy"><?php endif; ?>
          <span class="collection-name"><?= h($tile['name']) ?></span>
          <span class="collection-go"><?= (int)$tile['count'] ?> Artikel <span aria-hidden="true">&rarr;</span></span>
        </a>
        <?php endforeach; ?>
      </div>
      <button class="rail-btn rail-next" type="button" data-rail-next aria-label="Weiter" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
      </button>
    </div>
  </section>
  <?php endif; ?>

  <!-- Bestseller -->
  <?php if (!empty($featured)): ?>
  <section class="container section">
    <span class="section-title-label">Sortiment</span>
    <div class="section-head-row">
      <h2 class="section-title">Bestseller</h2>
      <a class="link-arrow" href="<?= url('/shop.php') ?>">Alle Produkte <span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $p): ?>
        <?php include __DIR__ . '/partials/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Zuletzt angesehen (per JS aus localStorage) -->
  <section class="container section" data-recent-section hidden style="padding-top:0">
    <span class="section-title-label">Für dich</span>
    <h2 class="section-title">Zuletzt angesehen</h2>
    <div class="product-grid" data-recent-grid></div>
  </section>

  <!-- Reviews -->
  <section class="container section customer-reviews" style="padding-top:0">
    <span class="section-title-label">Kundenstimmen</span>
    <h2 class="section-title">Was unsere Kunden sagen</h2>
    <div class="review-strip">
      <?php foreach ($REVIEWS as $rev): ?>
      <article class="review-card">
        <div class="review-stars" aria-label="<?= $rev['r'] ?> von 5 Sternen"><?= str_repeat('★', $rev['r']) ?></div>
        <p>„<?= h($rev['t']) ?>"</p>
        <div class="review-author">
          <span><?= h(mb_substr($rev['n'], 0, 1)) ?></span>
          <strong><?= h($rev['n']) ?></strong>
          <em>verifiziert</em>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- FAQ -->
  <section class="container section" style="padding-top:0">
    <span class="section-title-label">Hilfe</span>
    <h2 class="section-title">Häufige Fragen</h2>
    <div class="faq">
      <?php foreach ($FAQ as $f): ?>
      <details class="faq-item">
        <summary><?= h($f['q']) ?> <span class="faq-ic" aria-hidden="true"></span></summary>
        <div class="faq-a"><?= h($f['a']) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
