export const dynamic = 'force-dynamic';
import Header from '../components/Header.jsx';
import Footer from '../components/Footer.jsx';
import ProductCard from '../components/ProductCard.jsx';
import Countdown from '../components/Countdown.jsx';
import { getSession } from '../lib/session.js';
import * as products from '../src/models/products.js';
import * as settings from '../src/models/settings.js';

function shuffle(list) {
  const items = [...list];
  for (let i = items.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [items[i], items[j]] = [items[j], items[i]];
  }
  return items;
}

const REVIEWS = [
  { n: 'Lena M.', t: 'Sehr sauber verpackt, gute Qualität und die Teile wirkten wirklich handverlesen.', r: 5 },
  { n: 'Jonas K.', t: 'Schneller Versand und die Auswahl hat genau zu meinem Style gepasst.', r: 5 },
  { n: 'Sara B.', t: 'Schöne Vintage-Pieces, gepflegter Zustand und kein komischer Second-Hand-Geruch.', r: 5 },
  { n: 'Milan R.', t: 'Unkomplizierte Bestellung, faire Preise und der Hoodie saß direkt perfekt.', r: 5 },
];

const BRANDS = ['Nike', 'Adidas', 'Stone Island', 'Moncler', 'C.P. Company', 'Ralph Lauren', 'Carhartt', 'Stussy', 'Trapstar', 'The North Face', 'Lacoste', 'Diesel'];

export default async function HomePage({ searchParams }) {
  const session = await getSession();
  const cartCount = (session.cart || []).reduce((n, l) => n + (l.qty || 0), 0);

  const saleEndsAt = settings.get('sale_ends_at') || '2026-12-31T23:59:59';
  const heroImage = settings.get('hero_image') || '/img/img.png';
  const featured = shuffle(products.bestsellers(12)).slice(0, 4);

  return (
    <>
      <Header cartCount={cartCount} currentPath="/" />
      <main id="main">
        <Countdown saleEndsAt={saleEndsAt} heroImage={heroImage} />

        <section className="brand-logo-marquee" aria-label="Marken">
          <div className="brand-logo-track">
            {[0, 1].flatMap(() => BRANDS).map((brand, i) => (
              <span key={i} className="brand-logo-word">{brand}</span>
            ))}
          </div>
        </section>

        {featured.length > 0 && (
          <section className="container section">
            <span className="section-title-label">Sortiment</span>
            <h2 className="section-title">Bestseller</h2>
            <div className="product-grid">
              {featured.map((p) => <ProductCard key={p.id} p={p} />)}
            </div>
          </section>
        )}

        <section className="container section customer-reviews">
          <span className="section-title-label">Kundenstimmen</span>
          <h2 className="section-title">Was unsere Kunden sagen</h2>
          <div className="review-strip">
            {REVIEWS.map((rev) => (
              <article key={rev.n} className="review-card">
                <div className="review-stars" aria-label={`${rev.r} von 5 Sternen`}>
                  {'★'.repeat(rev.r)}
                </div>
                <p>{rev.t}</p>
                <div className="review-author">
                  <span>{rev.n[0]}</span>
                  <strong>{rev.n}</strong>
                  <em>verifiziert</em>
                </div>
              </article>
            ))}
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
