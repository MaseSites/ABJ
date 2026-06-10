export const dynamic = 'force-dynamic';
import { getSession } from '../../../../../lib/session.js';
import * as newsletter from '../../../../../src/models/newsletter.js';

async function requireAdmin() {
  const session = await getSession();
  return session.adminId ? session : null;
}

export async function DELETE(request, { params }) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const { id } = await params;
  const ok = newsletter.remove(parseInt(id, 10));
  if (!ok) return Response.json({ error: 'Nicht gefunden.' }, { status: 404 });
  return Response.json({ ok: true });
}
