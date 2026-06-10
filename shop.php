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

$pageTitle = $q ? 'Suchergebnisse' : 'Shop';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<main id="main" class="container section">
  <span class="section-title-label"><?= $q ? 'Suche' : 'Kollektion' ?></span>
  <h1 class="section-title"><?= $q ? 'Suchergebnisse' : 'Shop' ?></h1>

  <?php if ($q): ?>
  <p class="muted result-info">
    <strong><?= count($all) ?></strong> Treffer für „<?= h($q) ?>" ·
    <a href="/shop">Suche zurücksetzen</a>
  </p>
  <?php endif; ?>

  <div class="shop-toolbar">
    <div class="chip-row">
      <a class="chip<?= !$category ? ' active' : '' ?>" href="/shop">Alle</a>
      <?php foreach ($categories as $c): ?>
        <a class="chip<?= $category === $c ? ' active' : '' ?>" href="/shop?category=<?= urlencode($c) ?>"><?= h($c) ?></a>
      <?php endforeach; ?>
    </div>
    <form class="sort-form" method="get" action="/shop">
      <?php if ($q): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
      <?php if ($category): ?><input type="hidden" name="category" value="<?= h($category) ?>"><?php endif; ?>
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
    <div class="product-grid">
      <?php foreach ($items as $p): ?>
        <?php include __DIR__ . '/partials/product-card.php'; ?>
      <?php endforeach; ?>
      <?php $fillers = (5 - count($items) % 5) % 5; for ($i = 0; $i < $fillers; $i++): ?>
        <div class="product-card-filler"></div>
      <?php endfor; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Seiten">
      <?php for ($i = 1; $i <= $totalPages; $i++):
        $qs = [];
        if ($q) $qs[] = 'q=' . urlencode($q);
        if ($category) $qs[] = 'category=' . urlencode($category);
        if ($sort) $qs[] = 'sort=' . urlencode($sort);
        $qs[] = 'page=' . $i;
        $href = '/shop?' . implode('&', $qs);
      ?>
        <a class="page<?= $i === $page ? ' active' : '' ?>" href="<?= h($href) ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted">Keine Produkte in dieser Kategorie.</p>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
