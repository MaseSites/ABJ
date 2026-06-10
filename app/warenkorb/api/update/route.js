export const dynamic = 'force-dynamic';
import { getSession } from '../../../../lib/session.js';
import * as inventory from '../../../../src/models/inventory.js';

export async function POST(request) {
  let body;
  try {
    const text = await request.text();
    body = Object.fromEntries(new URLSearchParams(text));
  } catch {
    return Response.json({ error: 'Ungültige Eingabe.' }, { status: 400 });
  }

  const index = parseInt(body.index, 10);
  const qty = parseInt(body.qty, 10);

  const session = await getSession();
  const cart = session.cart || [];

  if (index >= 0 && index < cart.length) {
    if (qty === 0) {
      cart.splice(index, 1);
    } else {
      const line = cart[index];
      const avail = inventory.stockForVariant(line.productId, line.size || '', '');
      cart[index] = { ...line, qty: Math.min(avail, qty) };
    }
  }

  session.cart = cart;
  await session.save();
  return Response.json({ ok: true });
}
