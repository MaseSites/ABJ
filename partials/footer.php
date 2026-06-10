<footer class="site-footer">
  <div class="container newsletter-section">
    <div class="newsletter">
      <div class="newsletter-text">
        <h2>Erster bei den nächsten Drops.</h2>
        <p>Neue Kollektionen &amp; exklusive Rabatte — direkt in dein Postfach.</p>
      </div>
      <form class="newsletter-form" action="/newsletter.php" method="post" data-newsletter>
        <input type="email" name="email" placeholder="deine@email.de" required maxlength="200" aria-label="E-Mail">
        <button class="btn btn-primary" type="submit">Anmelden</button>
      </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:2.5rem;padding:3rem 0;border-top:1px solid rgba(255,255,255,.07);margin-top:3rem">
      <div>
        <p style="text-transform:uppercase;letter-spacing:.18em;font-size:.65rem;font-weight:700;color:var(--ink-dim);margin-bottom:.8rem">Shop</p>
        <nav style="display:flex;flex-direction:column;gap:.4rem">
          <a href="/shop" style="color:var(--ink-soft);font-size:.85rem">Alle Produkte</a>
          <a href="/shop?category=hoodies" style="color:var(--ink-soft);font-size:.85rem">Hoodies</a>
          <a href="/shop?category=shirts" style="color:var(--ink-soft);font-size:.85rem">Shirts</a>
          <a href="/shop?sale=1" style="color:var(--ink-soft);font-size:.85rem">Sale</a>
        </nav>
      </div>
      <div>
        <p style="text-transform:uppercase;letter-spacing:.18em;font-size:.65rem;font-weight:700;color:var(--ink-dim);margin-bottom:.8rem">Info</p>
        <nav style="display:flex;flex-direction:column;gap:.4rem">
          <a href="/kontakt" style="color:var(--ink-soft);font-size:.85rem">Kontakt</a>
          <a href="/impressum" style="color:var(--ink-soft);font-size:.85rem">Impressum</a>
          <a href="/datenschutz" style="color:var(--ink-soft);font-size:.85rem">Datenschutz</a>
          <a href="/widerruf" style="color:var(--ink-soft);font-size:.85rem">Widerruf</a>
        </nav>
      </div>
      <div style="grid-column:span 2">
        <p style="text-transform:uppercase;letter-spacing:.18em;font-size:.65rem;font-weight:700;color:var(--ink-dim);margin-bottom:.8rem">ABJ</p>
        <p style="color:var(--ink-dim);font-size:.82rem;line-height:1.7;max-width:280px">Luxury streetwear — handpicked quality, no compromises.</p>
      </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;padding:1.2rem 0;border-top:1px solid rgba(255,255,255,.07);font-size:.72rem;color:var(--ink-dim);letter-spacing:.06em;flex-wrap:wrap;gap:.5rem">
      <span>© <?= date('Y') ?> ABJ Store. Alle Rechte vorbehalten.</span>
      <span>Made with precision.</span>
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
        <strong data-drawer-total>0,00 €</strong>
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
