export const dynamic = 'force-dynamic';
import { getSession } from '../../../../lib/session.js';
import * as products from '../../../../src/models/products.js';
import * as inventory from '../../../../src/models/inventory.js';

export async function POST(request) {
  const session = await getSession();
  if (!session.adminId) return Response.json({ error: 'Unauthorized' }, { status: 401 });

  let productId, variantsRaw;
  try {
    const ct = request.headers.get('content-type') || '';
    if (ct.includes('application/x-www-form-urlencoded')) {
      const text = await request.text();
      const params = new URLSearchParams(text);
      productId = parseInt(params.get('product_id') || '0', 10);
      variantsRaw = params.get('variants') || '[]';
    } else {
      const fd = await request.formData();
      productId = parseInt(String(fd.get('product_id') || '0'), 10);
      variantsRaw = String(fd.get('variants') || '[]');
    }
  } catch {
    return Response.json({ error: 'Ungültige Anfrage.' }, { status: 400 });
  }

  if (!productId) return Response.json({ error: 'product_id fehlt.' }, { status: 400 });
  const product = products.getById(productId);
  if (!product) return Response.json({ error: 'Produkt nicht gefunden.' }, { status: 404 });

  let variants;
  try {
    variants = JSON.parse(variantsRaw);
    if (!Array.isArray(variants)) throw new Error();
  } catch {
    return Response.json({ error: 'Ungültige Variantendaten.' }, { status: 400 });
  }

  const rows = variants.map((v) => ({
    product_id: productId,
    size: String(v.size || '').slice(0, 50),
    color: String(v.color || '').slice(0, 50),
    sku: String(v.sku || '').slice(0, 100),
    stock: Math.max(0, parseInt(v.stock, 10) || 0),
    min_stock: Math.max(0, parseInt(v.min_stock, 10) || 3),
    next_delivery: String(v.next_delivery || '').slice(0, 30),
    notes: String(v.notes || '').slice(0, 500),
  }));

  inventory.upsertMany(rows);

  const totalStock = rows.reduce((s, r) => s + (r.stock || 0), 0);
  products.update(productId, { ...product, stock: totalStock });

  return Response.json({ ok: true });
}
