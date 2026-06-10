export const dynamic = 'force-dynamic';
import { getSession } from '../../../../lib/session.js';
import * as settings from '../../../../src/models/settings.js';

async function requireAdmin() {
  const session = await getSession();
  return session.adminId ? session : null;
}

export async function GET() {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  return Response.json({ settings: settings.all() });
}

export async function PUT(request) {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  let body;
  try { body = await request.json(); } catch { return Response.json({ error: 'Ungültige Eingabe.' }, { status: 400 }); }
  settings.setMany(body);
  return Response.json({ ok: true });
}
