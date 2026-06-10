import Link from 'next/link';

const BASE = '';

export default function Footer({ csrfToken = '' }) {
  return (
    <>
      <footer className="site-footer">
        <div className="container newsletter-section">
          <div className="newsletter">
            <div className="newsletter-text">
              <h2>Erster bei den nächsten Drops.</h2>
              <p>Neue Kollektionen & exklusive Rabatte — direkt in dein Postfach.</p>
            </div>
            <form className="newsletter-form" action="/newsletter" method="post" data-newsletter>
              <input type="hidden" name="_csrf" value={csrfToken} />
              <input
                type="email"
                name="email"
                placeholder="deine@email.de"
                required
                maxLength={200}
                aria-label="E-Mail"
              />
              <button className="btn btn-primary" type="submit">Anmelden</button>
            </form>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: '2.5rem', padding: '3rem 0', borderTop: '1px solid rgba(255,255,255,.07)', marginTop: '3rem' }}>
            <div>
              <p style={{ textTransform: 'uppercase', letterSpacing: '.18em', fontSize: '.65rem', fontWeight: 700, color: 'var(--ink-dim)', marginBottom: '.8rem' }}>Shop</p>
              <nav style={{ display: 'flex', flexDirection: 'column', gap: '.4rem' }}>
                <Link href="/shop" style={{ color: 'var(--ink-soft)', fontSize: '.85rem' }}>Alle Produkte</Link>
                <Link href="/shop?category=hoodies" style={{ color: 'var(--ink-soft)', fontSize: '.85rem' }}>Hoodies</Link>
                <Link href="/shop?category=shirts" style={{ color: 'var(--ink-soft)', fontSize: '.85rem' }}>Shirts</Link>
                <Link href="/shop?sale=1" style={{ color: 'var(--ink-soft)', fontSize: '.85rem' }}>Sale</Link>
              </nav>
            </div>
            <div>
              <p style={{ textTransform: 'uppercase', letterSpacing: '.18em', fontSize: '.65rem', fontWeight: 700, color: 'var(--ink-dim)', marginBottom: '.8rem' }}>Info</p>
              <nav style={{ display: 'flex', flexDirection: 'column', gap: '.4rem' }}>
                <Link href="/kontakt" style={{ color: 'var(--ink-soft)', fontSize: '.85rem' }}>Kontakt</Link>
                <Link href="/impressum" style={{ color: 'var(--ink-soft)', fontSize: '.85rem' }}>Impressum</Link>
                <Link href="/datenschutz" style={{ color: 'var(--ink-soft)', fontSize: '.85rem' }}>Datenschutz</Link>
                <Link href="/widerruf" style={{ color: 'var(--ink-soft)', fontSize: '.85rem' }}>Widerruf</Link>
              </nav>
            </div>
            <div style={{ gridColumn: 'span 2' }}>
              <p style={{ textTransform: 'uppercase', letterSpacing: '.18em', fontSize: '.65rem', fontWeight: 700, color: 'var(--ink-dim)', marginBottom: '.8rem' }}>ABJ</p>
              <p style={{ color: 'var(--ink-dim)', fontSize: '.82rem', lineHeight: 1.7, maxWidth: '280px' }}>
                Luxury streetwear — handpicked quality, no compromises.
              </p>
            </div>
          </div>

          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '1.2rem 0', borderTop: '1px solid rgba(255,255,255,.07)', fontSize: '.72rem', color: 'var(--ink-dim)', letterSpacing: '.06em', flexWrap: 'wrap', gap: '.5rem' }}>
            <span>© {new Date().getFullYear()} ABJ Store. Alle Rechte vorbehalten.</span>
            <span>Made with precision.</span>
          </div>
        </div>
      </footer>

      <aside className="drawer" data-cart-drawer aria-hidden="true">
        <div className="drawer-overlay" data-cart-close></div>
        <div className="drawer-panel" role="dialog" aria-label="Warenkorb">
          <div className="drawer-head">
            <h3>Warenkorb</h3>
            <button className="icon-btn" data-cart-close aria-label="Schließen">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M6 6l12 12M18 6 6 18" />
              </svg>
            </button>
          </div>
          <div className="drawer-body" data-drawer-items></div>
          <div className="drawer-foot">
            <div className="drawer-total">
              <span>Gesamt</span>
              <strong data-drawer-total>0,00 €</strong>
            </div>
            <Link className="btn btn-primary btn-block" href="/kasse">Zur Kasse</Link>
            <Link className="btn btn-ghost btn-block" href="/warenkorb">Warenkorb ansehen</Link>
          </div>
        </div>
      </aside>

      <div className="toast-wrap" data-toasts aria-live="polite"></div>

      <button className="to-top" data-to-top aria-label="Nach oben scrollen" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M12 19V5M5 12l7-7 7 7" />
        </svg>
      </button>
    </>
  );
}
