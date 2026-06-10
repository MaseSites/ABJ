export const dynamic = 'force-dynamic';
import { getSession } from '../../../../lib/session.js';
import * as products from '../../../../src/models/products.js';
import * as inventory from '../../../../src/models/inventory.js';
import { parsePriceToCents } from '../../../../src/lib/format.js';
import sanitizeHtml from 'sanitize-html';

const SANITIZE_OPTS = {
  allowedTags: ['b', 'i', 'em', 'strong', 'p', 'br', 'ul', 'ol', 'li', 'a'],
  allowedAttributes: { a: ['href', 'target', 'rel'] },
  allowedSchemes: ['https', 'mailto'],
};

async function requireAdmin() {
  const session = await getSession();
  if (!session.adminId) return null;
  return session;
}

async function parseBody(request) {
  const ct = request.headers.get('content-type') || '';
  if (ct.includes('multipart/form-data') || ct.includes('application/x-www-form-urlencoded')) {
    const fd = await request.formData();
    const body = {};
    for (const [k, v] of fd.entries()) {
      if (v instanceof File) continue;
      body[k] = v;
    }
    return body;
  }
  return request.json();
}

function parseImageUrls(input) {
  return String(input || '').split(/\r?\n/).map((s) => s.trim()).filter(Boolean)
    .filter((u) => /^https:\/\//i.test(u)).slice(0, 12).map((src) => ({ type: 'url', src }));
}

function parseExistingImages(input) {
  try {
    const arr = JSON.parse(input || '[]');
    if (!Array.isArray(arr)) return [];
    return arr.filter((i) => i && typeof i.src === 'string').slice(0, 12);
  } catch { return []; }
}

function parseVariants(body) {
  let hasVariants = body.has_variants === '1';
  let optionGroups = [];
  let variants = [];
  try { optionGroups = JSON.parse(body.option_groups || '[]'); } catch {}
  try { variants = JSON.parse(body.variants || '[]'); } catch {}
  return { hasVariants, optionGroups, variants };
}

function buildInventoryRows(productId, productName, hasVariants, variants, optionGroups, stockFallback) {
  if (hasVariants && variants.length > 0) {
    return variants.map((v) => ({
      product_id: productId,
      size:  (v.option_values || []).find((ov) => ov.key === 'size')?.value  || '',
      color: (v.option_values || []).find((ov) => ov.key === 'color')?.value || '',
      sku:   v.sku || '',
      stock: Math.max(0, Number(v.stock) || 0),
      option_values:       v.option_values || [],
      is_default:          !!v.is_default,
      variant_price_cents: v.variant_price_cents ?? null,
      title:               productName,
    }));
  }
  // Kein Varianten-Modus: eine einzelne Inventory-Zeile
  return [{
    product_id: productId,
    size: '', color: '', sku: '',
    stock:      Math.max(0, stockFallback),
    option_values: [],
    is_default: true,
    title:      productName,
  }];
}

export async function GET() {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  return Response.json({ products: products.listAll() });
}

export async function POST(request) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });

  let body;
  try { body = await parseBody(request); } catch { return Response.json({ error: 'Ungültige Eingabe.' }, { status: 400 }); }

  const name = String(body.name || '').trim();
  if (!name) return Response.json({ error: 'Name erforderlich.' }, { status: 400 });

  const priceCents    = parsePriceToCents(body.price || '0') || 0;
  const salePriceCents = body.sale_price ? parsePriceToCents(body.sale_price) : null;
  const description   = body.description ? sanitizeHtml(String(body.description), SANITIZE_OPTS) : '';
  const images        = [...parseExistingImages(body.existing_images), ...parseImageUrls(body.image_urls)];

  const { hasVariants, optionGroups, variants } = parseVariants(body);

  // Größen aus option_groups extrahieren
  const sizeGroup = optionGroups.find((g) => g.key === 'size');
  const sizes     = sizeGroup ? (sizeGroup.values || []) : [];

  // Gesamtbestand aus Varianten berechnen
  const totalStock = hasVariants && variants.length
    ? variants.reduce((s, v) => s + Math.max(0, Number(v.stock) || 0), 0)
    : parseInt(body.stock || '0', 10);

  let product;
  try {
    product = products.create({
      name,
      description,
      category:         String(body.category || 'Allgemein').trim() || 'Allgemein',
      price_cents:      priceCents,
      sale_price_cents: salePriceCents,
      stock:            totalStock,
      is_bestseller:    body.is_bestseller === '1' || body.is_bestseller === 'on' ? 1 : 0,
      is_active:        body.is_active === '1' || body.is_active === 'on' || body.is_active === undefined ? 1 : 0,
      images,
      sizes,
      option_groups: optionGroups,
    });
  } catch (e) {
    console.error('[products POST] create error:', e);
    return Response.json({ error: 'Datenbankfehler beim Speichern: ' + (e?.message || e) }, { status: 500 });
  }

  try {
    const invRows = buildInventoryRows(
      product.id, name, hasVariants, variants, optionGroups,
      parseInt(body.stock || '0', 10)
    );
    inventory.syncVariants(product.id, invRows);
  } catch (e) {
    console.error('[products POST] inventory sync error:', e);
    // Produkt wurde gespeichert – nur Lager-Sync fehlgeschlagen
    return Response.json({ ok: true, id: product.id, warn: 'Lager-Sync fehlgeschlagen: ' + (e?.message || e) });
  }

  return Response.json({ ok: true, id: product.id });
}
