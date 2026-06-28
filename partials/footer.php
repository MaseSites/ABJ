<?php
$shopName     = setting_get('shop_name') ?: 'ABJ Store';
$contactEmail = setting_get('contact_email') ?: '';
$instagram    = setting_get('instagram_url') ?: '';
$tiktok       = setting_get('tiktok_url') ?: '';
?>
<footer class="site-footer">

  <div class="footer-newsletter">
    <div class="container">
      <div class="newsletter-wrap">
        <span class="newsletter-badge">Newsletter</span>
        <h2 class="newsletter-heading">Erster bei den nächsten Drops.</h2>
        <p class="newsletter-sub">Neue Kollektionen &amp; exklusive Rabatte — direkt in dein Postfach. Kein Spam, jederzeit abbestellbar.</p>
        <form class="newsletter-form" action="<?= url('/newsletter.php') ?>" method="post" data-newsletter>
          <input type="email" name="email" placeholder="deine@email.ch" required maxlength="200" aria-label="E-Mail-Adresse">
          <button class="btn btn-primary" type="submit">Anmelden</button>
        </form>
        <p class="newsletter-done" hidden></p>
      </div>
    </div>
  </div>

  <div class="footer-main">
    <div class="container footer-grid">
      <div class="footer-col footer-brand-col">
        <a class="footer-brand" href="<?= url('/') ?>">
          <img src="<?= url('/img/abj-logo.jpg') ?>" alt="" width="40" height="40">
          <strong><?= h($shopName) ?></strong>
        </a>
        <p class="footer-tagline"><?= h(setting_get('tagline') ?: '') ?></p>
        <?php if ($instagram || $tiktok): ?>
        <div class="footer-social">
          <?php if ($instagram): ?>
          <a href="<?= h($instagram) ?>" target="_blank" rel="noopener" aria-label="Instagram">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4.5"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
          </a>
          <?php endif; ?>
          <?php if ($tiktok): ?>
          <a href="<?= h($tiktok) ?>" target="_blank" rel="noopener" aria-label="TikTok">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.6 6.7a4.8 4.8 0 0 1-3.5-1.6v7.6a5.6 5.6 0 1 1-5.6-5.6c.2 0 .5 0 .7.1v2.9a2.7 2.7 0 1 0 1.9 2.6V2h2.9a4.8 4.8 0 0 0 3.6 4.6z"/></svg>
          </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="footer-col">
        <h4>Shop</h4>
        <a href="<?= url('/shop.php') ?>">Alle Produkte</a>
        <a href="<?= url('/shop.php?sale=1') ?>">Sale</a>
        <a href="<?= url('/wunschliste.php') ?>">Wunschliste</a>
        <a href="<?= url('/warenkorb.php') ?>">Warenkorb</a>
      </div>

      <div class="footer-col">
        <h4>Service</h4>
        <a href="<?= url('/kontakt.php') ?>">Kontakt</a>
        <?php if ($contactEmail): ?><a href="mailto:<?= h($contactEmail) ?>"><?= h($contactEmail) ?></a><?php endif; ?>
      </div>
    </div>

    <div class="container footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= h($shopName) ?>. Alle Rechte vorbehalten.</span>
    </div>
  </div>
</footer>

<aside class="drawer" data-cart-drawer aria-hidden="true">
  <div class="drawer-overlay" data-cart-close></div>
  <div class="drawer-panel" role="dialog" aria-label="Warenkorb">
    <div class="drawer-head">
      <h3>Warenkorb</h3>
      <button class="icon-btn" data-cart-close aria-label="Schließen">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>
    <div class="drawer-body" data-drawer-items></div>
    <div class="drawer-foot">
      <div class="drawer-total">
        <span>Gesamt</span>
        <strong data-drawer-total>CHF 0.00</strong>
      </div>
      <a class="btn btn-primary btn-block" href="<?= url('/kasse.php') ?>">Zur Kasse</a>
      <a class="btn btn-ghost btn-block" href="<?= url('/warenkorb.php') ?>">Warenkorb ansehen</a>
    </div>
  </div>
</aside>

<div class="toast-wrap" data-toasts aria-live="polite"></div>
<button class="to-top" data-to-top aria-label="Nach oben scrollen" hidden>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>

<script src="<?= url('/js/shop.js') ?>?v=33"></script>
</body>
</html>
