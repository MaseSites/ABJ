export const dynamic = 'force-dynamic';
import { getSession } from '../../../../lib/session.js';
import * as inventory from '../../../../src/models/inventory.js';

async function requireAdmin() {
  const session = await getSession();
  return session.adminId ? session : null;
}

export async function GET(request) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { searchParams } = new URL(request.url);
  const productId = searchParams.get('productId');
  const rows = productId ? inventory.byProduct(parseInt(productId, 10)) : inventory.allInventory();
  return Response.json({ inventory: rows });
}

export async function POST(request) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  let body;
  try { body = await request.json(); } catch { return Response.json({ error: 'Ungültige Eingabe.' }, { status: 400 }); }
  if (!body.product_id) return Response.json({ error: 'product_id erforderlich.' }, { status: 400 });
  inventory.upsert(body);
  return Response.json({ ok: true });
}
