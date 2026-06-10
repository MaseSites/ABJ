export const dynamic = 'force-dynamic';
import { getSession } from '../../../../lib/session.js';
import * as products from '../../../../src/models/products.js';
import * as inventory from '../../../../src/models/inventory.js';
import * as settings from '../../../../src/models/settings.js';

const BASE = '';

function formatPrice(cents, currency) {
  try {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(cents / 100);
  } catch { return `${(cents / 100).toFixed(2)} €`; }
}

function placeholder(name) {
  const n = (name || '?').trim().charAt(0).toUpperCase();
  return `data:image/svg+xml,${encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200'><rect width='200' height='200' fill='%2316161a'/><text x='100' y='120' font-size='80' font-weight='800' fill='%23a5b4fc' text-anchor='middle' font-family='Arial'>${n}</text></svg>`)}`;
}

export async function GET() {
  const session = await getSession();
  const cart = session.cart || [];
  const currency = settings.get('currency') || 'EUR';

  const items = [];
  let total = 0;

  for (const line of cart) {
    const p = products.getById(line.productId);
    if (!p || !p.is_active) continue;
    const variantRow = inventory.byVariant(line.productId, line.size || '', '');
    const unit = variantRow?.variant_price_cents != null
      ? variantRow.variant_price_cents
      : (p.sale_price_cents ?? p.price_cents);
    const avail = inventory.stockForVariant(line.productId, line.size || '', '');
    const safeQty = Math.min(line.qty, Math.max(0, avail));
    let imgSrc = null;
    try { const imgs = JSON.parse(variantRow?.images || '[]'); imgSrc = imgs[0]?.src; } catch {}
    total += unit * safeQty;
    items.push({
      productId: p.id, slug: p.slug, name: p.name,
      size: variantRow ? (variantRow.title || line.size) : line.size,
      qty: safeQty, maxQty: avail,
      image: imgSrc || p.images?.[0]?.src || placeholder(p.name),
      lineText: formatPrice(unit * safeQty, currency),
      url: `${BASE}/produkt/${p.slug}`,
      isSoldOut: safeQty === 0, wasReduced: safeQty < line.qty,
    });
  }

  return Response.json({
    count: items.reduce((n, it) => n + it.qty, 0),
    totalText: formatPrice(total, currency),
    items,
  });
}
