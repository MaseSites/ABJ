export const dynamic = 'force-dynamic';
import { getSession } from '../../lib/session.js';
import * as newsletter from '../../src/models/newsletter.js';

export async function POST(request) {
  let email;
  try {
    const text = await request.text();
    const body = Object.fromEntries(new URLSearchParams(text));
    email = String(body.email || '').trim();
  } catch {
    return Response.json({ error: 'Ungültige Eingabe.' }, { status: 400 });
  }

  if (!email || !email.includes('@') || email.length > 200) {
    return Response.json({ error: 'Ungültige E-Mail-Adresse.' }, { status: 400 });
  }

  newsletter.subscribe(email);
  return Response.json({ ok: true });
}
