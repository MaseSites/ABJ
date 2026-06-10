export const dynamic = 'force-dynamic';
import Header from '../../components/Header.jsx';
import Footer from '../../components/Footer.jsx';
import ProductCard from '../../components/ProductCard.jsx';
import { getSession } from '../../lib/session.js';
import * as products from '../../src/models/products.js';

export default async function ShopPage({ searchParams }) {
  const session = await getSession();
  const cartCount = (session.cart || []).reduce((n, l) => n + (l.qty || 0), 0);

  const params = await searchParams;
  const category = typeof params?.category === 'string' ? params.category : null;
  const q = typeof params?.q === 'string' ? params.q.slice(0, 80) : '';
  const sort = typeof params?.sort === 'string' ? params.sort : '';
  const page = Math.max(1, parseInt(params?.page, 10) || 1);
  const perPage = 12;

  const all = q.trim() ? products.search({ q, category }) : products.listPublic({ category });

  const eff = (p) => p.sale_price_cents ?? p.price_cents;
  if (sort === 'preis-auf') all.sort((a, b) => eff(a) - eff(b));
  else if (sort === 'preis-ab') all.sort((a, b) => eff(b) - eff(a));
  else if (sort === 'name') all.sort((a, b) => a.name.localeCompare(b.name, 'de'));

  const totalPages = Math.max(1, Math.ceil(all.length / perPage));
  const items = all.slice((page - 1) * perPage, page * perPage);
  const categories = products.categories();

  return (
    <>
      <Header cartCount={cartCount} currentPath="/shop" />
      <main id="main" className="container section">
        <span className="section-title-label">{q.trim() ? 'Suche' : 'Kollektion'}</span>
        <h1 className="section-title">{q.trim() ? 'Suchergebnisse' : 'Shop'}</h1>
        {q.trim() && (
          <p className="muted result-info">
            <strong>{all.length}</strong> Treffer für „{q}" ·{' '}
            <a href="/shop">Suche zurücksetzen</a>
          </p>
        )}

        <div className="shop-toolbar">
          <div className="chip-row">
            <a className={`chip${!category ? ' active' : ''}`} href="/shop">Alle</a>
            {categories.map((c) => (
              <a
                key={c}
                className={`chip${category === c ? ' active' : ''}`}
                href={`/shop?category=${encodeURIComponent(c)}`}
              >{c}</a>
            ))}
          </div>
          <form className="sort-form" method="get" action="/shop">
            {q && <input type="hidden" name="q" value={q} />}
            {category && <input type="hidden" name="category" value={category} />}
            <label className="sort-label" htmlFor="sort">Sortieren</label>
            <select id="sort" name="sort" data-sort-select defaultValue={sort}>
              <option value="">Neueste</option>
              <option value="preis-auf">Preis aufsteigend</option>
              <option value="preis-ab">Preis absteigend</option>
              <option value="name">Name (A–Z)</option>
            </select>
          </form>
        </div>

        {items.length > 0 ? (
          <>
            <div className="product-grid">
              {items.map((p) => <ProductCard key={p.id} p={p} />)}
            </div>
            {totalPages > 1 && (
              <nav className="pagination" aria-label="Seiten">
                {Array.from({ length: totalPages }, (_, i) => i + 1).map((i) => {
                  const qs = new URLSearchParams();
                  if (q) qs.set('q', q);
                  if (category) qs.set('category', category);
                  if (sort) qs.set('sort', sort);
                  qs.set('page', String(i));
                  return (
                    <a key={i} className={`page${i === page ? ' active' : ''}`} href={`/shop?${qs}`}>{i}</a>
                  );
                })}
              </nav>
            )}
          </>
        ) : (
          <p className="muted">Keine Produkte in dieser Kategorie.</p>
        )}
      </main>
      <Footer />
    </>
  );
}
