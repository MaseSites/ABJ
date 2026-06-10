export const dynamic = 'force-dynamic';
import * as products from '../../../src/models/products.js';
import * as settings from '../../../src/models/settings.js';

const BASE = '';

function formatPrice(cents, currency) {
  try {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(cents / 100);
  } catch { return `${(cents / 100).toFixed(2)} €`; }
}

function placeholder(name) {
  const n = (name || '?').trim().charAt(0).toUpperCase();
  return `data:image/svg+xml,${encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' width='600' height='750'><rect width='600' height='750' fill='%2316161a'/><text x='300' y='430' font-size='300' font-weight='800' fill='%23a5b4fc' text-anchor='middle' font-family='Arial'>${n}</text></svg>`)}`;
}

export function GET() {
  const currency = settings.get('currency') || 'EUR';
  const all = products.listPublic();
  const items = all.map(p => ({
    id: p.id,
    slug: p.slug,
    name: p.name,
    category: p.category,
    price_cents: p.price_cents,
    sale_price_cents: p.sale_price_cents,
    stock: p.stock,
    image: p.images?.[0]?.src || null,
    priceText: formatPrice(p.sale_price_cents ?? p.price_cents, currency),
    oldPriceText: p.sale_price_cents ? formatPrice(p.price_cents, currency) : null,
    url: `${BASE}/produkt/${p.slug}`,
  }));
  return Response.json({ items });
}
