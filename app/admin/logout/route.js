export const dynamic = 'force-dynamic';
import { NextResponse } from 'next/server';
import { getIronSession } from 'iron-session';

const SESSION_OPTS = {
  password: process.env.SESSION_SECRET || 'dev-only-session-secret-change-me-at-least-32',
  cookieName: 'abj.session',
  cookieOptions: {
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    httpOnly: true,
    maxAge: 8 * 60 * 60,
  },
};

export async function POST(request) {
  const res = NextResponse.redirect(new URL('/admin/login', request.url));
  const session = await getIronSession(request, res, SESSION_OPTS);
  session.destroy();
  return res;
}
