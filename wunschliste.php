<?php
require_once __DIR__ . '/lib/bootstrap.php';
$cartCount   = cart_count();
$currentPath = '/wunschliste';
$pageTitle   = 'Wunschliste';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main id="main" class="container section">
  <span class="section-title-label">Gemerkte Produkte</span>
  <h1 class="section-title">Wunschliste</h1>
  <div class="product-grid" id="wishlist-grid"></div>
  <p class="muted" id="wishlist-empty" style="display:none">Deine Wunschliste ist leer.</p>
</main>
<script>
(function(){
  var ids = JSON.parse(localStorage.getItem('wishlist') || '[]');
  var grid = document.getElementById('wishlist-grid');
  var empty = document.getElementById('wishlist-empty');
  if (!ids.length) { empty.style.display=''; return; }
  fetch('/api/produkte.php?ids=' + ids.join(','))
    .then(function(r){ return r.json(); })
    .then(function(products){
      if (!products.length) { empty.style.display=''; return; }
      products.forEach(function(p){
        var img = p.image ? '<img src="'+p.image+'" alt="'+p.name+'" loading="lazy">' : '';
        var price = p.sale_price_cents
          ? '<span class="price-sale">'+fmt(p.sale_price_cents)+'</span><span class="price-old">'+fmt(p.price_cents)+'</span>'
          : '<span class="price-now">'+fmt(p.price_cents)+'</span>';
        grid.innerHTML += '<article class="product-card"><div class="product-card-media"><a href="/produkt/'+p.slug+'" class="media-link">'+img+'</a></div><div class="product-card-body"><span class="product-cat">'+p.category+'</span><h3 class="product-name"><a href="/produkt/'+p.slug+'">'+p.name+'</a></h3><div class="product-price">'+price+'</div></div></article>';
      });
    });
  function fmt(c){ return 'CHF '+((c/100).toFixed(2)); }
})();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
