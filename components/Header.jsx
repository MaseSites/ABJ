import Link from 'next/link';

const BASE = '';

export default function Header({ cartCount = 0, currentPath = '' }) {
  return (
    <>
      <a className="skip-link" href="#main">Zum Inhalt springen</a>
      <div className="scroll-progress" data-progress aria-hidden="true"></div>
      <header className="site-header">
        <div className="container header-inner">
          <button
            className="icon-btn nav-toggle"
            data-nav-toggle
            aria-label="Menü öffnen"
            aria-expanded="false"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M3 6h18M3 12h18M3 18h18" />
            </svg>
          </button>

          <Link className="brand brand-logo" href="/" aria-label="Startseite">
            <img src={`${BASE}/img/abj-logo.jpg`} alt="ABJ" width="48" height="48" />
          </Link>

          <nav className="main-nav" aria-label="Hauptnavigation">
            <Link href="/" className={currentPath === '/' ? 'active' : ''}>Start</Link>
            <Link href="/shop" className={currentPath.startsWith('/shop') ? 'active' : ''}>Shop</Link>
            <Link href="/kontakt" className={currentPath === '/kontakt' ? 'active' : ''}>Kontakt</Link>
          </nav>

          <div className="header-actions">
            <button className="icon-btn" data-search-toggle aria-label="Suche" aria-expanded="false">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
              </svg>
            </button>
            <Link className="icon-btn" href="/wunschliste" aria-label="Wunschliste">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 21s-7-4.5-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z" />
              </svg>
              <span className="badge-count" data-wish-count hidden>0</span>
            </Link>
            <Link className="icon-btn cart-toggle" href="/warenkorb" data-cart-toggle aria-label="Warenkorb">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0" />
              </svg>
              <span className="badge-count" data-cart-count hidden={cartCount === 0}>
                {cartCount}
              </span>
            </Link>
          </div>
        </div>

        <div className="search-bar" data-search-bar hidden>
          <div className="container">
            <form className="search-form" action="/shop" method="get" role="search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
              </svg>
              <input
                type="search"
                name="q"
                placeholder="Wonach suchst du?"
                data-search-input
                autoComplete="off"
                maxLength={80}
              />
              <button type="button" className="icon-btn" data-search-close aria-label="Schließen">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M6 6l12 12M18 6 6 18" />
                </svg>
              </button>
            </form>
            <div className="search-results" data-search-results></div>
          </div>
        </div>
      </header>

      <div className="mobile-menu" data-mobile-menu hidden>
        <Link href="/">Start</Link>
        <Link href="/shop">Shop</Link>
        <Link href="/kontakt">Kontakt</Link>
        <Link href="/wunschliste">Wunschliste</Link>
      </div>
    </>
  );
}
