export const dynamic = 'force-dynamic';
import Header from '../../../components/Header.jsx';
import Footer from '../../../components/Footer.jsx';
import { getSession } from '../../../lib/session.js';
import * as settings from '../../../src/models/settings.js';

export default async function OrderConfirmationPage({ params }) {
  const { reference } = await params;
  const session = await getSession();
  const cartCount = (session.cart || []).reduce((n, l) => n + (l.qty || 0), 0);
  const contactEmail = settings.get('contact_email') || '';

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
        <a className="btn btn-primary" href="/shop">Weiter einkaufen</a>
      </main>
      <Footer />
    </>
  );
}
