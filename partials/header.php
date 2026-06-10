<?php
$cartCount = isset($cartCount) ? (int)$cartCount : cart_count();
$currentPath = isset($currentPath) ? $currentPath : current_path();
?>
<a class="skip-link" href="#main">Zum Inhalt springen</a>
<div class="scroll-progress" data-progress aria-hidden="true"></div>
<header class="site-header">
  <div class="container header-inner">
    <button class="icon-btn nav-toggle" data-nav-toggle aria-label="Menü öffnen" aria-expanded="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 6h18M3 12h18M3 18h18"/>
      </svg>
    </button>

    <a class="brand brand-logo" href="/" aria-label="Startseite">
      <img src="/img/abj-logo.jpg" alt="ABJ" width="48" height="48">
    </a>

    <nav class="main-nav" aria-label="Hauptnavigation">
      <a href="/"      <?= $currentPath === '/'             ? 'class="active"' : '' ?>>Start</a>
      <a href="/shop"  <?= str_starts_with($currentPath, '/shop') ? 'class="active"' : '' ?>>Shop</a>
      <a href="/kontakt" <?= $currentPath === '/kontakt'    ? 'class="active"' : '' ?>>Kontakt</a>
    </nav>

    <div class="header-actions">
      <button class="icon-btn" data-search-toggle aria-label="Suche" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
      </button>
      <a class="icon-btn" href="/wunschliste" aria-label="Wunschliste">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/>
        </svg>
        <span class="badge-count" data-wish-count hidden>0</span>
      </a>
      <a class="icon-btn cart-toggle" href="/warenkorb" data-cart-toggle aria-label="Warenkorb">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0"/>
        </svg>
        <span class="badge-count" data-cart-count <?= $cartCount === 0 ? 'hidden' : '' ?>><?= $cartCount ?></span>
      </a>
    </div>
  </div>

  <div class="search-bar" data-search-bar hidden>
    <div class="container">
      <form class="search-form" action="/shop" method="get" role="search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <input type="search" name="q" placeholder="Wonach suchst du?" data-search-input autocomplete="off" maxlength="80">
        <button type="button" class="icon-btn" data-search-close aria-label="Schließen">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M6 6l12 12M18 6 6 18"/>
          </svg>
        </button>
      </form>
      <div class="search-results" data-search-results></div>
    </div>
  </div>
</header>

<div class="mobile-menu" data-mobile-menu hidden>
  <a href="/">Start</a>
  <a href="/shop">Shop</a>
  <a href="/kontakt">Kontakt</a>
  <a href="/wunschliste">Wunschliste</a>
</div>
