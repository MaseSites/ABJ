export const dynamic = 'force-dynamic';
import { getSession } from '../../../../../lib/session.js';
import * as products from '../../../../../src/models/products.js';
import * as inventory from '../../../../../src/models/inventory.js';
import { parsePriceToCents } from '../../../../../src/lib/format.js';
import sanitizeHtml from 'sanitize-html';

const SANITIZE_OPTS = {
  allowedTags: ['b', 'i', 'em', 'strong', 'p', 'br', 'ul', 'ol', 'li', 'a'],
  allowedAttributes: { a: ['href', 'target', 'rel'] },
  allowedSchemes: ['https', 'mailto'],
};

async function requireAdmin() {
  const session = await getSession();
  return session.adminId ? session : null;
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
  return [{
    product_id: productId,
    size: '', color: '', sku: '',
    stock:      Math.max(0, stockFallback),
    option_values: [],
    is_default: true,
    title:      productName,
  }];
}

export async function GET(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { id } = await params;
  const p = products.getById(parseInt(id, 10));
  if (!p) return Response.json({ error: 'Nicht gefunden' }, { status: 404 });
  return Response.json({ product: p });
}

export async function PUT(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { id } = await params;
  const productId = parseInt(id, 10);
  const existing = products.getById(productId);
  if (!existing) return Response.json({ error: 'Nicht gefunden.' }, { status: 404 });

  let body;
  try { body = await parseBody(request); } catch { return Response.json({ error: 'Ungültige Eingabe.' }, { status: 400 }); }

  const name = String(body.name || '').trim();
  if (!name) return Response.json({ error: 'Name erforderlich.' }, { status: 400 });

  const priceCents     = parsePriceToCents(body.price || '0') || 0;
  const salePriceCents = body.sale_price ? parsePriceToCents(body.sale_price) : null;
  const description    = body.description ? sanitizeHtml(String(body.description), SANITIZE_OPTS) : '';
  const images         = [...parseExistingImages(body.existing_images), ...parseImageUrls(body.image_urls)];

  let hasVariants = body.has_variants === '1';
  let optionGroups = [];
  let variants = [];
  try { optionGroups = JSON.parse(body.option_groups || '[]'); } catch {}
  try { variants = JSON.parse(body.variants || '[]'); } catch {}

  const sizeGroup = optionGroups.find((g) => g.key === 'size');
  const sizes     = sizeGroup ? (sizeGroup.values || []) : [];

  const totalStock = hasVariants && variants.length
    ? variants.reduce((s, v) => s + Math.max(0, Number(v.stock) || 0), 0)
    : parseInt(body.stock || '0', 10);

  try {
    products.update(productId, {
      name,
      description,
      category:         String(body.category || 'Allgemein').trim() || 'Allgemein',
      price_cents:      priceCents,
      sale_price_cents: salePriceCents,
      stock:            totalStock,
      is_bestseller:    body.is_bestseller === '1' || body.is_bestseller === 'on' ? 1 : 0,
      is_active:        body.is_active === '1' || body.is_active === 'on' ? 1 : 0,
      images,
      sizes,
      option_groups: optionGroups,
    });
  } catch (e) {
    console.error('[products PUT] update error:', e);
    return Response.json({ error: 'Datenbankfehler beim Speichern: ' + (e?.message || e) }, { status: 500 });
  }

  try {
    const invRows = buildInventoryRows(
      productId, name, hasVariants, variants, optionGroups,
      parseInt(body.stock || '0', 10)
    );
    inventory.syncVariants(productId, invRows);
  } catch (e) {
    console.error('[products PUT] inventory sync error:', e);
    return Response.json({ ok: true, warn: 'Lager-Sync fehlgeschlagen: ' + (e?.message || e) });
  }

  return Response.json({ ok: true });
}

export async function DELETE(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { id } = await params;
  products.remove(parseInt(id, 10));
  return Response.json({ ok: true });
}
