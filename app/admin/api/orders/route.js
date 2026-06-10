export const dynamic = 'force-dynamic';
import { getSession } from '../../../../lib/session.js';
import * as orders from '../../../../src/models/orders.js';

async function requireAdmin() {
  const session = await getSession();
  return session.adminId ? session : null;
}

export async function GET() {
  if (!await requireAdmin()) return Response.json({ error: 'Unauthorized' }, { status: 401 });
  const all = orders.list() || [];
  return Response.json({ orders: all });
}
