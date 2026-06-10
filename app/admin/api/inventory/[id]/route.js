export const dynamic = 'force-dynamic';
import { getSession } from '../../../../../lib/session.js';
import * as inventory from '../../../../../src/models/inventory.js';

async function requireAdmin() {
  const session = await getSession();
  return session.adminId ? session : null;
}

export async function GET(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { id } = await params;
  const rows = inventory.byProduct(parseInt(id, 10));
  return Response.json({ rows });
}

export async function PUT(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { id } = await params;
  let body;
  try { body = await request.json(); } catch { return Response.json({ error: 'Ungültige Eingabe.' }, { status: 400 }); }
  inventory.upsert({ ...body, product_id: body.product_id || parseInt(id, 10) });
  return Response.json({ ok: true });
}

export async function DELETE(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { id } = await params;
  inventory.deleteByProduct(parseInt(id, 10));
  return Response.json({ ok: true });
}
