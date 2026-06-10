export const dynamic = 'force-dynamic';
import { notFound } from 'next/navigation';
import * as orders from '../../../../src/models/orders.js';
import * as settings from '../../../../src/models/settings.js';

function formatPrice(cents) {
  try {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: settings.get('currency') || 'EUR' }).format((cents || 0) / 100);
  } catch { return `${((cents || 0) / 100).toFixed(2)} €`; }
}

async function updateStatusAction(formData) {
  'use server';
  const { redirect } = await import('next/navigation');
  const ord = await import('../../../../src/models/orders.js');
  const reference = String(formData.get('reference') || '');
  const status = String(formData.get('status') || '');
  const payment_status = String(formData.get('payment_status') || '');
  if (reference) ord.updateStatus(reference, status, payment_status);
  redirect(`/admin/bestellungen/${encodeURIComponent(reference)}`);
}

export default async function OrderDetail({ params }) {
  const { reference } = await params;
  const order = orders.getByReference(reference);
  if (!order) return notFound();

  const items = Array.isArray(order.items) ? order.items : [];

  return (
    <main className="admin-main narrow">
      <div className="admin-head-row">
        <h1>Bestellung {order.reference}</h1>
        <a className="btn btn-ghost" href="/admin/bestellungen">Zurück</a>
      </div>

      <div className="form-grid">
        <section className="admin-section span-1">
          <h2>Artikel</h2>
          <table className="data-table">
            <tbody>
              {items.map((it, i) => (
                <tr key={i}>
                  <td>{it.qty}× {it.name}{it.size ? ` (${it.size})` : ''}</td>
                  <td style={{ textAlign: 'right' }}>{formatPrice(it.lineCents ?? (it.unitCents * it.qty))}</td>
                </tr>
              ))}
              <tr>
                <td><strong>Gesamt</strong></td>
                <td style={{ textAlign: 'right' }}><strong>{formatPrice(order.total_cents)}</strong></td>
              </tr>
            </tbody>
          </table>
        </section>

        <section className="admin-section span-1">
          <h2>Kunde</h2>
          <p><strong>{order.customer_name}</strong><br />
            <a href={`mailto:${order.email}`}>{order.email}</a>
            {order.phone ? <><br /><span className="muted">{order.phone}</span></> : null}
          </p>
          {order.address && typeof order.address === 'object' ? (
            <p className="muted" style={{ lineHeight: 1.7 }}>
              {order.address.street} {order.address.housenr}<br />
              {order.address.zip} {order.address.city}<br />
              {order.address.country}
            </p>
          ) : (
            <p className="muted" style={{ whiteSpace: 'pre-line' }}>{order.address}</p>
          )}
          {order.payment_method && (
            <p className="muted" style={{ fontSize: '.82rem' }}>
              Zahlungsmethode: <strong style={{ color: 'var(--ink)' }}>
                {order.payment_method === 'kreditkarte' ? 'Kreditkarte' :
                 order.payment_method === 'paypal' ? 'PayPal' :
                 order.payment_method === 'vorkasse' ? 'Banküberweisung' :
                 order.payment_method}
              </strong>
            </p>
          )}
          <p className="muted" style={{ fontSize: '.82rem' }}>Eingegangen: {order.created_at?.slice(0, 16)}</p>
        </section>
      </div>

      <section className="admin-section">
        <h2>Status verwalten</h2>
        <form action={updateStatusAction} className="admin-form">
          <input type="hidden" name="reference" value={order.reference} />
          <div className="form-grid">
            <label className="field">
              <span>Bestellstatus</span>
              <select name="status" defaultValue={order.status}>
                {['neu', 'in_bearbeitung', 'versendet', 'erledigt', 'storniert'].map((s) => (
                  <option key={s} value={s}>{s.replace('_', ' ')}</option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Zahlungsstatus</span>
              <select name="payment_status" defaultValue={order.payment_status}>
                {['offen', 'bezahlt', 'erstattet'].map((s) => (
                  <option key={s} value={s}>{s}</option>
                ))}
              </select>
            </label>
          </div>
          <button className="btn btn-primary" type="submit">Status speichern</button>
        </form>
      </section>
    </main>
  );
}
