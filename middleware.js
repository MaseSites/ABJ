import { NextResponse } from 'next/server';
import { getIronSession } from 'iron-session';

const SESSION_OPTS = {
  password: process.env.SESSION_SECRET || 'dev-only-session-secret-change-me-at-least-32',
  cookieName: 'abj.session',
  cookieOptions: { secure: process.env.NODE_ENV === 'production', sameSite: 'lax', httpOnly: true },
};

export async function middleware(request) {
  const { pathname } = request.nextUrl;

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set('x-pathname', pathname);

  if (
    pathname.startsWith('/admin') &&
    !pathname.startsWith('/admin/login') &&
    !pathname.startsWith('/admin/logout')
  ) {
    const res = NextResponse.next({ request: { headers: requestHeaders } });
    try {
      const session = await getIronSession(request, res, SESSION_OPTS);
      if (!session.adminId) {
        return NextResponse.redirect(new URL('/admin/login', request.url));
      }
    } catch {
      return NextResponse.redirect(new URL('/admin/login', request.url));
    }
    return res;
  }

  return NextResponse.next({ request: { headers: requestHeaders } });
}

export const config = {
  matcher: ['/admin', '/admin/:path*'],
};
