export const dynamic = 'force-dynamic';
import { redirect } from 'next/navigation';
import { getSession } from '../../../lib/session.js';

export default async function LoginPage({ searchParams }) {
  const session = await getSession();
  if (session.adminId) redirect('/admin');

  const params = await searchParams;
  const error = params?.error === '1';

  return (
    <main className="gate-wrap">
      <div className="gate-card">
        <p className="admin-kicker">ABJ Store</p>
        <h1>Admin-Login</h1>
        <p className="muted">Melde dich an, um das Dashboard zu öffnen.</p>

        {error && (
          <div className="alert alert-error">
            Benutzername oder Passwort falsch.
          </div>
        )}

        <form method="post" action="/api/admin/auth" className="gate-form">
          <div className="field">
            <span>Benutzername</span>
            <input
              type="text"
              name="username"
              autoComplete="username"
              required
              autoFocus
              placeholder="admin"
            />
          </div>
          <div className="field">
            <span>Passwort</span>
            <input
              type="password"
              name="password"
              autoComplete="current-password"
              required
              placeholder="••••••••"
            />
          </div>
          <button className="btn btn-primary btn-block" type="submit" style={{marginTop:'.4rem'}}>
            Anmelden
          </button>
        </form>
      </div>
    </main>
  );
}
