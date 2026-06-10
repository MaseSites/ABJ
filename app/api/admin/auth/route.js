export const dynamic = 'force-dynamic';
import { NextResponse } from 'next/server';
import { getIronSession } from 'iron-session';
import * as users from '../../../../src/models/users.js';
import bcrypt from 'bcryptjs';

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
  let username = '', password = '';
  try {
    const body = await request.formData();
    username = String(body.get('username') || '').trim();
    password = String(body.get('password') || '');
  } catch {
    return NextResponse.redirect(new URL('/admin/login?error=1', request.url));
  }

  if (!username || !password) {
    return NextResponse.redirect(new URL('/admin/login?error=1', request.url));
  }

  const user = users.findByUsername(username);
  if (!user) {
    return NextResponse.redirect(new URL('/admin/login?error=1', request.url));
  }

  let valid = false;
  try {
    valid = await bcrypt.compare(password, user.password_hash);
  } catch {
    return NextResponse.redirect(new URL('/admin/login?error=1', request.url));
  }
  if (!valid) {
    return NextResponse.redirect(new URL('/admin/login?error=1', request.url));
  }

  const res = NextResponse.redirect(new URL('/admin', request.url));
  const session = await getIronSession(request, res, SESSION_OPTS);
  session.adminId = user.id;
  session.adminUsername = user.username;
  await session.save();

  return res;
}
