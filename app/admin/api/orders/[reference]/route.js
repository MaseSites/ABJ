export const dynamic = 'force-dynamic';
import { getSession } from '../../../../../lib/session.js';
import * as orders from '../../../../../src/models/orders.js';

async function requireAdmin() {
  const session = await getSession();
  return session.adminId ? session : null;
}

export async function GET(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { reference } = await params;
  const order = orders.getByReference ? orders.getByReference(reference) : null;
  if (!order) return Response.json({ error: 'Nicht gefunden' }, { status: 404 });
  return Response.json({ order });
}

export async function PUT(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { reference } = await params;
  let body;
  try { body = await request.json(); } catch { return Response.json({ error: 'Ungültige Eingabe.' }, { status: 400 }); }
  if (body.status || body.payment_status) {
    orders.updateStatus(reference, body.status || '', body.payment_status || '');
  }
  return Response.json({ ok: true });
}

export async function DELETE(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { reference } = await params;
  const ok = orders.deleteOrder(reference);
  if (!ok) return Response.json({ error: 'Nicht gefunden.' }, { status: 404 });
  return Response.json({ ok: true });
}
