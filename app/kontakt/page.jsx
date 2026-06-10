export const dynamic = 'force-dynamic';
import { redirect } from 'next/navigation';
import Header from '../../components/Header.jsx';
import Footer from '../../components/Footer.jsx';
import { getSession } from '../../lib/session.js';
import * as messages from '../../src/models/messages.js';
import * as settings from '../../src/models/settings.js';

async function submitContact(formData) {
  'use server';
  const name = String(formData.get('name') || '').trim();
  const email = String(formData.get('email') || '').trim();
  const body = String(formData.get('message') || '').trim();
  if (name.length < 2 || !email.includes('@') || body.length < 5) {
    redirect('/kontakt?error=1');
  }
  messages.create({ name, email, body });
  redirect('/kontakt?sent=1');
}

export default async function KontaktPage({ searchParams }) {
  const session = await getSession();
  const cartCount = (session.cart || []).reduce((n, l) => n + (l.qty || 0), 0);
  const params = await searchParams;
  const sent = params?.sent === '1';
  const contactEmail = settings.get('contact_email') || '';

  return (
    <>
      <Header cartCount={cartCount} currentPath="/kontakt" />
      <main id="main" className="container section narrow">
        <h1 className="section-title">Kontakt</h1>
        {contactEmail && (
          <p className="muted" style={{ marginBottom: '1.4rem' }}>
            Oder schreib uns direkt: <a href={`mailto:${contactEmail}`}>{contactEmail}</a>
          </p>
        )}
        {sent ? (
          <div className="alert alert-ok">Danke! Wir melden uns bald.</div>
        ) : (
          <form action={submitContact} className="form-stack">
            <label className="field">
              <span>Name</span>
              <input type="text" name="name" required minLength={2} maxLength={120} />
            </label>
            <label className="field">
              <span>E-Mail</span>
              <input type="email" name="email" required maxLength={200} />
            </label>
            <label className="field">
              <span>Nachricht</span>
              <textarea name="message" required minLength={5} maxLength={2000} rows={5}></textarea>
            </label>
            <button className="btn btn-primary" type="submit">Senden</button>
          </form>
        )}
      </main>
      <Footer />
    </>
  );
}
