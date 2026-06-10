export const dynamic = 'force-dynamic';
import * as orders from '../../../src/models/orders.js';
import * as settings from '../../../src/models/settings.js';

function formatPrice(cents) {
  try {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: settings.get('currency') || 'EUR' }).format((cents || 0) / 100);
  } catch { return `${((cents || 0) / 100).toFixed(2)} €`; }
}

const STATUS_LABEL = {
  neu:            { label: 'Neu',            cls: 'tag-warn' },
  in_bearbeitung: { label: 'In Bearbeitung', cls: 'tag-warn' },
  versendet:      { label: 'Versendet',      cls: 'tag-ok'   },
  erledigt:       { label: 'Erledigt',       cls: 'tag-ok'   },
  storniert:      { label: 'Storniert',      cls: 'tag-off'  },
};
const PAY_LABEL = {
  offen:     { label: 'Offen',     cls: 'tag-warn' },
  bezahlt:   { label: 'Bezahlt',   cls: 'tag-ok'   },
  erstattet: { label: 'Erstattet', cls: 'tag-off'  },
};

export default function AdminOrdersList() {
  const allOrders = orders.list() || [];
  const counts = {
    alle:    allOrders.length,
    offen:   allOrders.filter((o) => o.status !== 'erledigt' && o.status !== 'storniert').length,
    erledigt: allOrders.filter((o) => o.status === 'erledigt').length,
    storniert: allOrders.filter((o) => o.status === 'storniert').length,
  };

  function statusTag(s) {
    const { label, cls } = STATUS_LABEL[s] || { label: s, cls: '' };
    return <span className={`tag ${cls}`}>{label}</span>;
  }
  function payTag(s) {
    const { label, cls } = PAY_LABEL[s] || { label: s, cls: '' };
    return <span className={`tag ${cls}`}>{label}</span>;
  }

  return (
    <main className="admin-main">
      <p className="admin-kicker">Verwaltung</p>
      <div className="admin-head-row">
        <h1>Bestellungen</h1>
        <div style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap' }}>
          <span className="tag">{counts.alle} gesamt</span>
          {counts.offen > 0    && <span className="tag tag-warn">{counts.offen} offen</span>}
          {counts.erledigt > 0 && <span className="tag tag-ok">{counts.erledigt} erledigt</span>}
        </div>
      </div>

      <div className="admin-section">
        {allOrders.length === 0 ? (
          <p className="muted">Noch keine Bestellungen vorhanden.</p>
        ) : (
          <>
            <div style={{ marginBottom: '1rem' }}>
              <input
                type="search"
                className="admin-search"
                data-order-filter=""
                placeholder="Bestellungen filtern… (Name, Referenz, E-Mail)"
                aria-label="Bestellungen filtern"
              />
            </div>
            <table className="data-table" id="orders-table">
              <thead>
                <tr>
                  <th>Referenz</th>
                  <th>Kunde</th>
                  <th>Summe</th>
                  <th>Bestellstatus</th>
                  <th>Zahlung</th>
                  <th>Datum</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {allOrders.map((o) => {
                  const items = Array.isArray(o.items) ? o.items : [];
                  const qty   = items.reduce((n, i) => n + (i.qty || 0), 0);
                  const isDone = o.status === 'erledigt' || o.status === 'storniert';
                  return (
                    <tr key={o.reference}>
                      <td>
                        <a href={`/admin/bestellungen/${o.reference}`} style={{ color: '#b89c67', fontWeight: 700 }}>
                          {o.reference}
                        </a>
                        <div className="muted" style={{ fontSize: '.72rem', marginTop: '.15rem' }}>
                          {qty} Artikel · {formatPrice((o.total_cents || 0) + (o.shipping_cents || 0))}
                        </div>
                      </td>
                      <td>
                        <strong>{o.customer_name}</strong>
                        <div className="muted" style={{ fontSize: '.78rem' }}>{o.email}</div>
                      </td>
                      <td style={{ fontWeight: 700 }}>{formatPrice((o.total_cents || 0) + (o.shipping_cents || 0))}</td>
                      <td>{statusTag(o.status)}</td>
                      <td>{payTag(o.payment_status)}</td>
                      <td className="muted" style={{ fontSize: '.8rem' }}>{o.created_at?.slice(0, 10)}</td>
                      <td className="cell-actions">
                        <a className="btn btn-ghost btn-sm" href={`/admin/bestellungen/${o.reference}`}>Details</a>
                        {!isDone && (
                          <button
                            className="btn btn-sm"
                            style={{ background: 'rgba(127,184,140,.12)', color: '#a8e6b8', border: '1px solid rgba(127,184,140,.25)' }}
                            data-done-order={o.reference}
                          >
                            Erledigt
                          </button>
                        )}
                        <button
                          className="btn btn-danger btn-sm"
                          data-delete-order={o.reference}
                          data-name={o.reference}
                        >
                          Löschen
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </>
        )}
      </div>
    </main>
  );
}
