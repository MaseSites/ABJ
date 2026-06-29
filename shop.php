<?php
require_once __DIR__ . '/lib/bootstrap.php';

$currentPath = '/shop';
$cartCount = cart_count();

$category = isset($_GET['category']) ? trim($_GET['category']) : null;
$q        = isset($_GET['q'])        ? substr(trim($_GET['q']), 0, 80) : '';
$sort     = isset($_GET['sort'])     ? trim($_GET['sort']) : '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$sale     = !empty($_GET['sale']);

$all = $q ? products_search($q, $category ?: null) : products_list_public($category ?: null);

if ($sale) $all = array_filter($all, function($p) { return $p['sale_price_cents']; });

usort($all, function($a, $b) use ($sort) {
    $ea = $a['sale_price_cents'] ?? $a['price_cents'];
    $eb = $b['sale_price_cents'] ?? $b['price_cents'];
    if ($sort === 'preis-auf') return $ea - $eb;
    if ($sort === 'preis-ab') return $eb - $ea;
    if ($sort === 'name') return strcmp($a['name'], $b['name']);
    return 0;
});
$all = array_values($all);

$totalPages = max(1, (int)ceil(count($all) / $perPage));
$items = array_slice($all, ($page - 1) * $perPage, $perPage);
$categories = products_categories();

// Querystring-Helfer für Filter-Links
function shop_qs(array $overrides = []): string {
    $params = array_merge([
        'q'        => $_GET['q'] ?? null,
        'category' => $_GET['category'] ?? null,
        'sort'     => $_GET['sort'] ?? null,
        'sale'     => $_GET['sale'] ?? null,
    ], $overrides);
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '';
}

$pageTitle = $q ? 'Suchergebnisse' : ($sale ? 'Sale' : 'Shop');
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section">
  <span class="section-title-label"><?= $q ? 'Suche' : ($sale ? 'Reduziert' : 'Kollektion') ?></span>
  <h1 class="section-title"><?= $q ? 'Suchergebnisse' : ($sale ? 'Sale' : 'Shop') ?></h1>

  <?php if ($q): ?>
  <p class="muted result-info">
    <strong><?= count($all) ?></strong> Treffer für „<?= h($q) ?>" ·
    <a href="<?= url('/shop.php') ?>">Suche zurücksetzen</a>
  </p>
  <?php endif; ?>

  <div class="shop-toolbar">
    <div class="shop-filter">
      <label class="sort-label" for="cat">Kategorie</label>
      <select id="cat" class="shop-select" onchange="if(this.value)window.location.href=this.value">
        <option value="<?= h(url('/shop.php' . shop_qs(['category' => null, 'sale' => null, 'page' => null]))) ?>"<?= !$category ? ' selected' : '' ?>>Alle Kategorien</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= h(url('/shop.php' . shop_qs(['category' => $c, 'sale' => null, 'page' => null]))) ?>"<?= $category === $c ? ' selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <form class="sort-form" method="get" action="<?= url('/shop.php') ?>">
      <?php if ($q): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
      <?php if ($category): ?><input type="hidden" name="category" value="<?= h($category) ?>"><?php endif; ?>
      <?php if ($sale): ?><input type="hidden" name="sale" value="1"><?php endif; ?>
      <label class="sort-label" for="sort">Sortieren</label>
      <select id="sort" name="sort" data-sort-select>
        <option value=""          <?= $sort === ''          ? 'selected' : '' ?>>Neueste</option>
        <option value="preis-auf" <?= $sort === 'preis-auf' ? 'selected' : '' ?>>Preis aufsteigend</option>
        <option value="preis-ab"  <?= $sort === 'preis-ab'  ? 'selected' : '' ?>>Preis absteigend</option>
        <option value="name"      <?= $sort === 'name'      ? 'selected' : '' ?>>Name (A–Z)</option>
      </select>
    </form>
  </div>

  <?php if (!empty($items)): ?>
    <p class="muted" style="font-size:.82rem;margin:0 0 1rem"><?= count($all) ?> <?= count($all) === 1 ? 'Produkt' : 'Produkte' ?></p>
    <div class="product-grid">
      <?php foreach ($items as $p): ?>
        <?php include __DIR__ . '/partials/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Seiten">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a class="page<?= $i === $page ? ' active' : '' ?>" href="<?= url('/shop.php' . shop_qs(['page' => $i])) ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="cart-empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56">
        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
      </svg>
      <p>Kein passendes Produkt gefunden.</p>
      <div style="display:flex;gap:.6rem;flex-wrap:wrap;justify-content:center">
        <a class="btn btn-primary" href="<?= url('/anfrage.php') ?>">Produkt anfragen</a>
        <a class="btn btn-line" href="<?= url('/shop.php') ?>">Alle Produkte ansehen</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Produkt nicht gefunden? Anfrage-CTA -->
  <section class="find-cta">
    <div class="find-cta-inner">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="34" height="34"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/><path d="M11 8v3M11 14h.01"/></svg>
      <div class="find-cta-text">
        <h2>Dein Produkt nicht gefunden?</h2>
        <p>Kein Problem — schick uns dein Wunschprodukt und wir schauen, ob wir es noch auf Lager haben.</p>
      </div>
      <a class="btn btn-primary" href="<?= url('/anfrage.php') ?>">Schau ob wir es haben</a>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
