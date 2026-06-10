export const dynamic = 'force-dynamic';
import * as newsletter from '../../../src/models/newsletter.js';

export default function AdminNewsletter() {
  const subscribers = newsletter.list();

  return (
    <main className="admin-main narrow">
      <p className="admin-kicker">E-Mail Marketing</p>
      <div className="admin-head-row">
        <h1>Newsletter</h1>
        <span className="tag tag-ok">{subscribers.length} Abonnent:innen</span>
      </div>

      <section className="admin-section">
        {subscribers.length === 0 ? (
          <p className="muted">Noch keine Anmeldungen.</p>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>E-Mail</th>
                <th>Angemeldet</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {subscribers.map((s) => (
                <tr key={s.id}>
                  <td>{s.email}</td>
                  <td className="muted" style={{ fontSize: '.82rem' }}>{s.created_at?.slice(0, 10)}</td>
                  <td className="cell-actions">
                    <button
                      className="btn btn-danger btn-sm"
                      data-delete-newsletter={s.id}
                      data-name={s.email}
                    >
                      Entfernen
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </main>
  );
}
