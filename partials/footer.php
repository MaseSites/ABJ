<footer class="site-footer">
  <div class="footer-newsletter">
    <div class="container">
      <div class="newsletter-wrap">
        <div class="newsletter-badge">Newsletter</div>
        <h2 class="newsletter-heading">Erster bei den nächsten Drops.</h2>
        <p class="newsletter-sub">Neue Kollektionen &amp; exklusive Rabatte — direkt in dein Postfach. Kein Spam.</p>
        <form class="newsletter-form" action="/newsletter.php" method="post" data-newsletter>
          <input type="email" name="email" placeholder="deine@email.de" required maxlength="200" aria-label="E-Mail-Adresse">
          <button class="btn btn-primary" type="submit">Anmelden</button>
        </form>
        <p class="newsletter-done" hidden></p>
      </div>
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
      <a class="btn btn-primary btn-block" href="/kasse">Zur Kasse</a>
      <a class="btn btn-ghost btn-block" href="/warenkorb">Warenkorb ansehen</a>
    </div>
  </div>
</aside>

<div class="toast-wrap" data-toasts aria-live="polite"></div>
<button class="to-top" data-to-top aria-label="Nach oben scrollen" hidden>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>

<script src="/js/shop.js"></script>
</body>
</html>
