export const dynamic = 'force-dynamic';
import Header from '../../../components/Header.jsx';
import Footer from '../../../components/Footer.jsx';
import { getSession } from '../../../lib/session.js';
import * as orders from '../../../src/models/orders.js';
import * as orderMessages from '../../../src/models/order-messages.js';
import * as settings from '../../../src/models/settings.js';

export default async function OrderConfirmationPage({ params }) {
  const { reference } = await params;
  const session = await getSession();
  const cartCount = (session.cart || []).reduce((n, l) => n + (l.qty || 0), 0);
  const contactEmail = settings.get('contact_email') || '';
  const order = orders.getByReference(reference);
  const messages = order ? orderMessages.listByOrder(reference) : [];

  return (
    <>
      <Header cartCount={cartCount} />
      <main id="main" className="container section narrow center">
        <div className="confirmation-icon">✓</div>
        <h1>Bestellung eingegangen!</h1>
        <p className="muted">
          Deine Bestellnummer: <strong>{reference}</strong>
        </p>
        <p className="muted">
          Wir haben deine Bestellung erhalten und melden uns bald.
          {contactEmail && <> Fragen? <a href={`mailto:${contactEmail}`}>{contactEmail}</a></>}
        </p>
        {order ? (
          <section className="admin-section" style={{ width: '100%', marginTop: '1.5rem', textAlign: 'left' }}>
            <h2>Posteingang zur Bestellung</h2>
            {messages.length === 0 ? (
              <p className="muted">Sobald wir deine Bestellung aktualisieren, erscheinen hier Nachrichten und Hinweise.</p>
            ) : (
              <div className="msg-list">
                {messages.map((msg) => (
                  <article key={msg.id} className="msg-card">
                    <div className="msg-head">
                      <div>
                        <strong>{msg.subject || 'Nachricht'}</strong>
                        <div className="muted" style={{ fontSize: '.82rem' }}>
                          {msg.author_name || 'ABJ Team'} · {msg.created_at?.slice(0, 16)}
                        </div>
                      </div>
                      <span className="tag">{msg.is_system ? 'System' : 'Info'}</span>
                    </div>
                    <p className="msg-body" style={{ whiteSpace: 'pre-line' }}>{msg.body}</p>
                  </article>
                ))}
              </div>
            )}
          </section>
        ) : null}
        <a className="btn btn-primary" href="/shop">Weiter einkaufen</a>
      </main>
      <Footer />
    </>
  );
}
