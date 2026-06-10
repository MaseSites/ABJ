export const dynamic = 'force-dynamic';
import * as messages from '../../../src/models/messages.js';

async function markReadAction(formData) {
  'use server';
  const { redirect } = await import('next/navigation');
  const msgs = await import('../../../src/models/messages.js');
  const id = parseInt(formData.get('id'), 10);
  if (id) msgs.markRead(id);
  redirect('/admin/nachrichten');
}

async function deleteAction(formData) {
  'use server';
  const { redirect } = await import('next/navigation');
  const msgs = await import('../../../src/models/messages.js');
  const id = parseInt(formData.get('id'), 10);
  if (id) msgs.remove(id);
  redirect('/admin/nachrichten');
}

export default function AdminMessages() {
  const all = messages.list();
  const unread = all.filter((m) => !m.is_read).length;

  return (
    <main className="admin-main narrow">
      <p className="admin-kicker">Support</p>
      <div className="admin-head-row">
        <h1>Nachrichten</h1>
        <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center' }}>
          {unread > 0 && <span className="tag tag-new">{unread} ungelesen</span>}
          <span className="tag">{all.length} gesamt</span>
        </div>
      </div>

      <div className="admin-section">
        {all.length === 0 ? (
          <div className="empty-state">
            <p>Noch keine Kontaktnachrichten.</p>
          </div>
        ) : (
          <div className="msg-list">
            {all.map((m) => (
              <article key={m.id} className={`msg-card${m.is_read ? '' : ' unread'}`}>
                <div className="msg-head">
                  <div>
                    <span className="msg-sender">{m.name}</span>
                    <a className="msg-email" href={`mailto:${m.email}`}>{m.email}</a>
                  </div>
                  <div className="msg-meta">
                    <span className="msg-date">{m.created_at?.slice(0, 16)}</span>

                    {!m.is_read && (
                      <form action={markReadAction}>
                        <input type="hidden" name="id" value={m.id} />
                        <button className="btn btn-ghost btn-sm" type="submit">
                          Als gelesen
                        </button>
                      </form>
                    )}

                    <form
                      action={deleteAction}
                      data-confirm="Nachricht wirklich löschen?"
                    >
                      <input type="hidden" name="id" value={m.id} />
                      <button className="btn btn-danger btn-sm" type="submit">
                        Löschen
                      </button>
                    </form>
                  </div>
                </div>
                <p className="msg-body">{m.body}</p>
              </article>
            ))}
          </div>
        )}
      </div>
    </main>
  );
}
