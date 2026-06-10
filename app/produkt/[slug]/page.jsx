export const dynamic = 'force-dynamic';
import { notFound } from 'next/navigation';
import Header from '../../../components/Header.jsx';
import Footer from '../../../components/Footer.jsx';
import { getSession } from '../../../lib/session.js';
import * as products from '../../../src/models/products.js';
import * as inventory from '../../../src/models/inventory.js';
import { parseOptionGroups } from '../../../src/lib/variant-options.js';

function formatPrice(cents) {
  try {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(cents / 100);
  } catch { return `${(cents / 100).toFixed(2)} €`; }
}

function discountPercent(priceCents, salePriceCents) {
  if (!priceCents || !salePriceCents) return 0;
  return Math.round((1 - salePriceCents / priceCents) * 100);
}

function placeholder(name) {
  const n = (name || '?').trim().charAt(0).toUpperCase();
  return `data:image/svg+xml,${encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='600' height='750'><rect width='600' height='750' fill='%2316161a'/><text x='300' y='430' font-size='300' font-weight='800' fill='%23a5b4fc' text-anchor='middle' font-family='Arial'>${n}</text></svg>`)}`;
}

async function addToCartAction(formData) {
  'use server';
  const { redirect } = await import('next/navigation');
  const { getSession } = await import('../../../lib/session.js');
  const inv = await import('../../../src/models/inventory.js');
  const prods = await import('../../../src/models/products.js');

  const productId = parseInt(formData.get('productId'), 10);
  const size = String(formData.get('size') || '');
  const qty = Math.max(1, parseInt(formData.get('qty') || '1', 10));
  const slug = String(formData.get('slug') || '');

  const p = prods.getById(productId);
  if (!p || !p.is_active) return redirect(`/produkt/${slug}?error=unavailable`);
  if (inv.hasVariants(productId) && !size) return redirect(`/produkt/${slug}?error=variant`);
  const avail = inv.stockForVariant(productId, size, '');
  if (avail <= 0) return redirect(`/produkt/${slug}?error=soldout`);

  const session = await getSession();
  const cart = session.cart || [];
  const existing = cart.find(l => l.productId === productId && l.size === size);
  if (existing) existing.qty = Math.min(avail, existing.qty + qty);
  else cart.push({ productId, size, qty: Math.min(avail, qty) });
  session.cart = cart;
  await session.save();
  redirect('/warenkorb');
}

export default async function ProductPage({ params, searchParams }) {
  const { slug } = await params;
  const sp = await searchParams;

  const session = await getSession();
  const cartCount = (session.cart || []).reduce((n, l) => n + (l.qty || 0), 0);

  const product = products.getBySlug(slug);
  if (!product || !product.is_active) return notFound();

  const invRows = inventory.byProduct(product.id);
  const inventoryMap = {};
  for (const row of invRows) {
    const key = row.size || '__nosize__';
    inventoryMap[key] = (inventoryMap[key] || 0) + Math.max(0, row.stock - row.reserved);
  }

  const variants = invRows.map(r => ({
    id: r.id,
    key: r.size || String(r.id),
    title: r.title || r.size || '',
    images: (() => { try { return JSON.parse(r.images || '[]'); } catch { return []; } })(),
    stock: Math.max(0, r.stock - r.reserved),
    variant_price_cents: r.variant_price_cents ?? null,
    is_default: !!r.is_default,
    option_values: (() => { try { return JSON.parse(r.option_values || '[]'); } catch { return []; } })(),
  }));

  const optionGroups = parseOptionGroups(product.option_groups || []);
  const related = products.related(product.category, product.id, 4);
  const mainImg = product.images?.[0]?.src || placeholder(product.name);
  const totalAvail = Object.values(inventoryMap).reduce((s, v) => s + v, 0);

  const errorMsg = sp?.error === 'soldout'
    ? 'Dieses Produkt ist leider ausverkauft.'
    : sp?.error === 'variant'
    ? 'Bitte wähle eine Variante.'
    : null;

  return (
    <>
      <Header cartCount={cartCount} currentPath={`/produkt/${slug}`} />
      <main id="main" className="container section">
        <nav className="breadcrumb">
          <a href="/">Start</a> {' / '}
          <a href="/shop">Shop</a> {' / '}
          {product.name}
        </nav>

        <div className="product-detail">
          <div className="product-gallery" data-gallery>
            <div className="gallery-main">
              <img src={mainImg} alt={product.name} data-gallery-main data-zoomable />
            </div>
            {product.images.length > 1 && (
              <div className="gallery-thumbs">
                {product.images.map((img, i) => (
                  <button key={i} className={`thumb${i === 0 ? ' active' : ''}`} data-src={img.src}>
                    <img src={img.src} alt="" loading="lazy" />
                  </button>
                ))}
              </div>
            )}
          </div>

          <div className="product-info">
            <span className="product-cat-label">{product.category}</span>
            <h1 className="product-detail-name">{product.name}</h1>

            <div className="product-price big" suppressHydrationWarning>
              {product.sale_price_cents ? (
                <>
                  <span className="price-sale">{formatPrice(product.sale_price_cents)}</span>
                  <span className="price-old">{formatPrice(product.price_cents)}</span>
                  <span className="badge-sale">-{discountPercent(product.price_cents, product.sale_price_cents)}%</span>
                </>
              ) : (
                <span className="price-now">{formatPrice(product.price_cents)}</span>
              )}
            </div>

            {totalAvail > 0 && totalAvail <= 5 && (
              <span className="stock-urgency">Nur noch {totalAvail} verfügbar</span>
            )}

            {errorMsg && <div className="alert alert-error" style={{ marginBottom: '1rem' }}>{errorMsg}</div>}

            <form action={addToCartAction} data-ajax-add data-product-id={product.id}>
              <input type="hidden" name="productId" value={product.id} />
              <input type="hidden" name="slug" value={product.slug} />

              {variants.length > 0 && optionGroups.length > 0 && (
                <div id="variant-row" suppressHydrationWarning></div>
              )}
              <input type="hidden" name="size" id="size-hidden" />

              <label className="field-label" htmlFor="qty">Menge</label>
              <input className="qty-input" type="number" id="qty" name="qty" defaultValue="1" min="1" max="99" />

              {product.stock > 0 ? (
                <button className="btn btn-primary btn-block" type="submit" style={{ maxWidth: '100%' }}>
                  In den Warenkorb
                </button>
              ) : (
                <button className="btn btn-primary btn-block" disabled style={{ maxWidth: '100%' }}>
                  Ausverkauft
                </button>
              )}
            </form>

            <div className="product-trust-row">
              <span>
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M8 1l1.8 3.6L14 5.5l-3 2.9.7 4.1L8 10.4 4.3 12.5l.7-4.1L2 5.5l4.2-.9z"/></svg>
                Kostenloser Versand
              </span>
              <span>
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6"><rect x="2" y="6" width="12" height="9" rx="1.5"/><path d="M5 6V4a3 3 0 016 0v2"/></svg>
                Sicherer Checkout
              </span>
              <span>
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6"><path d="M2 8a6 6 0 1012 0A6 6 0 002 8z"/><path d="M8 5v3l2 2"/></svg>
                14 Tage Rückgabe
              </span>
            </div>

            {product.description && (
              <div className="product-description" dangerouslySetInnerHTML={{ __html: product.description }} />
            )}
          </div>
        </div>

        <script
          id="product-variants-data"
          type="application/json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(variants) }}
        />
        <script
          id="product-option-groups-data"
          type="application/json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(optionGroups) }}
        />
        <script
          id="product-price-data"
          type="application/json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify({ price_cents: product.price_cents, sale_price_cents: product.sale_price_cents }) }}
        />
        <span data-track-view={product.id} hidden></span>

        {related.length > 0 && (
          <section className="section">
            <h2 className="section-title">Ähnliche Produkte</h2>
            <div className="product-grid">
              {related.map(p => (
                <article key={p.id} className="product-card">
                  <a href={`/produkt/${p.slug}`}>
                    <img src={p.images?.[0]?.src || placeholder(p.name)} alt={p.name} loading="lazy" />
                    <div>{p.name}</div>
                    <div>{formatPrice(p.sale_price_cents ?? p.price_cents)}</div>
                  </a>
                </article>
              ))}
            </div>
          </section>
        )}
      </main>
      <Footer />
    </>
  );
}
