export const dynamic = 'force-dynamic';
import { redirect } from 'next/navigation';
import Header from '../../components/Header.jsx';
import Footer from '../../components/Footer.jsx';
import { getSession } from '../../lib/session.js';
import * as products from '../../src/models/products.js';
import * as inventory from '../../src/models/inventory.js';
import * as settings from '../../src/models/settings.js';

function formatPrice(cents) {
  try {
    const currency = settings.get('currency') || 'EUR';
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(cents / 100);
  } catch {
    return `${(cents / 100).toFixed(2)} €`;
  }
}

function placeholder(name) {
  const n = (name || '?').trim().charAt(0).toUpperCase();
  return `data:image/svg+xml,${encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200'><rect width='200' height='200' fill='%2316161a'/><text x='100' y='120' font-size='80' font-weight='800' fill='%23a5b4fc' text-anchor='middle' font-family='Arial'>${n}</text></svg>`)}`;
}

async function updateCartAction(formData) {
  'use server';
  const session = await (await import('../../lib/session.js')).getSession();
  const index = parseInt(formData.get('index'), 10);
  const qty = parseInt(formData.get('qty'), 10);
  const cart = session.cart || [];
  if (index >= 0 && index < cart.length) {
    if (qty === 0) cart.splice(index, 1);
    else {
      const line = cart[index];
      const avail = inventory.stockForVariant(line.productId, line.size || '', '');
      cart[index] = { ...line, qty: Math.min(avail, qty) };
    }
  }
  session.cart = cart;
  await session.save();
  redirect('/warenkorb');
}

async function clearCartAction() {
  'use server';
  const session = await (await import('../../lib/session.js')).getSession();
  session.cart = [];
  await session.save();
  redirect('/warenkorb');
}

export default async function CartPage() {
  const session = await getSession();
  const cart = session.cart || [];

  const items = [];
  let total = 0;
  const warnings = [];

  for (const line of cart) {
    const p = products.getById(line.productId);
    if (!p || !p.is_active) {
      warnings.push({ type: 'unavailable' });
      continue;
    }
    const variantRow = inventory.byVariant(line.productId, line.size || '', '');
    const unit = variantRow?.variant_price_cents != null
      ? variantRow.variant_price_cents
      : (p.sale_price_cents ?? p.price_cents);
    const avail = inventory.stockForVariant(line.productId, line.size || '', '');
    const safeQty = Math.min(line.qty, Math.max(0, avail));
    if (safeQty === 0) {
      warnings.push({ message: `"${p.name}"${line.size ? ` (${line.size})` : ''} ist ausverkauft.` });
    }
    let imgSrc = null;
    try { const imgs = JSON.parse(variantRow?.images || '[]'); imgSrc = imgs[0]?.src; } catch {}
    imgSrc = imgSrc || p.images?.[0]?.src;
    total += unit * safeQty;
    items.push({
      productId: p.id, slug: p.slug, name: p.name,
      size: variantRow ? (variantRow.title || line.size) : line.size,
      qty: safeQty, originalQty: line.qty,
      unitCents: unit, lineCents: unit * safeQty,
      image: imgSrc || placeholder(p.name),
      stock: avail, maxQty: avail,
      isSoldOut: safeQty === 0, wasReduced: safeQty < line.qty,
    });
  }

  const cartCount = items.reduce((n, it) => n + it.qty, 0);

  return (
    <>
      <Header cartCount={cartCount} currentPath="/warenkorb" />
      <main id="main" className="container section">
        <h1 className="section-title">Warenkorb</h1>

        {warnings.length > 0 && (
          <div className="alert alert-error" style={{ marginBottom: '1.4rem' }}>
            {warnings.map((w, i) => <div key={i}>{w.message || 'Ein Artikel ist nicht mehr verfügbar.'}</div>)}
          </div>
        )}

        {items.length === 0 || items.every(it => it.qty === 0) ? (
          <div className="cart-empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" width="56" height="56">
              <path d="M6 7h12l-1 13H7zM9 7a3 3 0 0 1 6 0" />
            </svg>
            <p>Dein Warenkorb ist leer.</p>
            <a className="btn btn-primary" href="/shop">Zum Shop</a>
          </div>
        ) : (
          <>
            <div className="cart-table">
              {items.map((it, i) => it.qty === 0 && !it.isSoldOut ? null : (
                <div key={i} className={`cart-row${it.isSoldOut ? ' is-soldout' : ''}`}>
                  <div className="cart-media">
                    <img src={it.image} alt={it.name} />
                  </div>
                  <div className="cart-info">
                    <a href={`/produkt/${it.slug}`}><strong>{it.name}</strong></a>
                    {it.size && <span className="muted">Grösse: {it.size}</span>}
                    <span className="muted">{formatPrice(it.unitCents)} / Stück</span>
                    {it.isSoldOut && <span className="cart-soldout-label">Ausverkauft – wird beim Checkout entfernt</span>}
                    {!it.isSoldOut && it.wasReduced && <span className="cart-reduced-warning">Menge auf {it.qty} reduziert (max. verfügbar)</span>}
                  </div>
                  {!it.isSoldOut && (
                    <>
                      <form className="cart-qty" action={updateCartAction}>
                        <input type="hidden" name="index" value={i} />
                        <input type="number" name="qty" defaultValue={it.qty} min="0" max={it.maxQty} aria-label="Menge" />
                        <button className="btn btn-ghost btn-sm" type="submit">OK</button>
                      </form>
                      <div className="cart-line-total">{formatPrice(it.lineCents)}</div>
                    </>
                  )}
                </div>
              ))}
            </div>

            <div className="cart-summary">
              <form action={clearCartAction}>
                <button className="btn btn-ghost" type="submit">Leeren</button>
              </form>
              <div className="cart-total">
                <span>Gesamt</span>
                <strong>{formatPrice(total)}</strong>
              </div>
              <a className="btn btn-primary" href="/kasse">Zur Kasse</a>
            </div>
          </>
        )}
      </main>
      <Footer />
    </>
  );
}
