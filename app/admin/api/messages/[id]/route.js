export const dynamic = 'force-dynamic';
import { getSession } from '../../../../../lib/session.js';
import * as messages from '../../../../../src/models/messages.js';

async function requireAdmin() {
  const session = await getSession();
  return session.adminId ? session : null;
}

export async function PUT(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { id } = await params;
  messages.markRead(parseInt(id, 10));
  return Response.json({ ok: true });
}

export async function DELETE(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { id } = await params;
  const ok = messages.remove(parseInt(id, 10));
  if (!ok) return Response.json({ error: 'Nicht gefunden.' }, { status: 404 });
  return Response.json({ ok: true });
}
