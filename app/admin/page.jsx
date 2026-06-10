export const dynamic = 'force-dynamic';
import * as products from '../../src/models/products.js';
import * as orders from '../../src/models/orders.js';
import * as newsletter from '../../src/models/newsletter.js';
import * as messages from '../../src/models/messages.js';
import * as settings from '../../src/models/settings.js';

function fmt(cents) {
  try {
    return new Intl.NumberFormat('de-DE', {
      style: 'currency',
      currency: settings.get('currency') || 'EUR',
    }).format((cents || 0) / 100);
  } catch {
    return `${((cents || 0) / 100).toFixed(2)} €`;
  }
}

function statusTag(status) {
  const map = {
    bezahlt: 'tag-ok',
    offen: 'tag-warn',
    storniert: 'tag-off',
  };
  return map[status] || '';
}

export default function AdminDashboard() {
  const allProducts = products.listAll();
  const allOrders   = orders.list() || [];
  const ostats      = orders.stats(7);
  const recentOrders = allOrders.slice(0, 8);

  const stats = {
    revenue:     fmt(ostats.totalRevenue),
    orders:      allOrders.length,
    open:        ostats.openCount,
    products:    allProducts.length,
    active:      allProducts.filter((p) => p.is_active).length,
    subscribers: newsletter.count(),
    messages:    messages.unreadCount(),
  };

  const W = 480, H = 140, pad = 20;
  const n = ostats.series.length;
  const bw = (W - pad * 2) / n * 0.55;
  const gap = (W - pad * 2) / n;
  const dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

  return (
    <main className="admin-main">
      <p className="admin-kicker">Dashboard</p>
      <div className="admin-head-row" style={{marginBottom:'1.4rem'}}>
        <h1>Übersicht</h1>
        <div style={{display:'flex',gap:'.6rem'}}>
          <a className="btn btn-primary btn-sm" href="/admin/produkte/neu">+ Produkt</a>
          <a className="btn btn-ghost btn-sm" href="/admin/bestellungen">Bestellungen</a>
        </div>
      </div>

      {/* Stats */}
      <div className="stat-grid">
        <div className="stat-card">
          <span className="stat-num">{stats.revenue}</span>
          <span className="stat-label">Umsatz (bezahlt)</span>
        </div>
        <div className="stat-card">
          <span className="stat-num">{stats.orders}</span>
          <span className="stat-label">Bestellungen gesamt</span>
        </div>
        <div className="stat-card">
          <span className="stat-num">{stats.open}</span>
          <span className="stat-label">Offen</span>
        </div>
        <div className="stat-card">
          <span className="stat-num">{stats.products}</span>
          <span className="stat-label">Produkte ({stats.active} aktiv)</span>
        </div>
        <div className="stat-card">
          <span className="stat-num">{stats.subscribers}</span>
          <span className="stat-label">Newsletter-Abos</span>
        </div>
        <div className="stat-card">
          <span className="stat-num">{stats.messages}</span>
          <span className="stat-label">Neue Nachrichten</span>
        </div>
      </div>

      {/* Chart */}
      <div className="admin-section">
        <h2>Bestellungen – letzte 7 Tage</h2>
        <svg className="bar-chart" viewBox={`0 0 ${W} ${H}`} role="img" aria-label="Bestellungen der letzten 7 Tage">
          {ostats.series.map((d, i) => {
            const h = ostats.maxOrders > 0
              ? Math.round((d.orders / ostats.maxOrders) * (H - pad * 2))
              : 2;
            const x = pad + i * gap + (gap - bw) / 2;
            const y = H - pad - Math.max(h, 2);
            const day = new Date();
            day.setDate(day.getDate() - d.dayOffset);
            return (
              <g key={i}>
                <rect x={x.toFixed(1)} y={y.toFixed(1)} width={bw.toFixed(1)} height={Math.max(h, 2)} rx="4" className="bar" />
                <text x={(x + bw / 2).toFixed(1)} y={H - 5} textAnchor="middle" fontSize="10" fill="#3d4055">
                  {dayNames[day.getDay()]}
                </text>
                {d.orders > 0 && (
                  <text x={(x + bw / 2).toFixed(1)} y={(y - 4).toFixed(1)} textAnchor="middle" fontSize="10" fill="#c8ccd8" fontWeight="700">
                    {d.orders}
                  </text>
                )}
              </g>
            );
          })}
        </svg>
      </div>

      {/* Recent orders */}
      <div className="admin-section">
        <div className="admin-head-row" style={{marginBottom:'1rem'}}>
          <h2 style={{marginBottom:0}}>Letzte Bestellungen</h2>
          <a className="btn btn-ghost btn-sm" href="/admin/bestellungen">Alle anzeigen</a>
        </div>
        {recentOrders.length === 0 ? (
          <div className="empty-state">
            <p>Noch keine Bestellungen vorhanden.</p>
          </div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Referenz</th>
                <th>Kunde</th>
                <th>Summe</th>
                <th>Zahlung</th>
                <th>Status</th>
                <th>Datum</th>
              </tr>
            </thead>
            <tbody>
              {recentOrders.map((o) => (
                <tr key={o.reference}>
                  <td>
                    <a href={`/admin/bestellungen/${o.reference}`} style={{color:'#b89c67',fontWeight:700}}>
                      {o.reference}
                    </a>
                  </td>
                  <td><strong>{o.customer_name}</strong></td>
                  <td style={{fontWeight:700,color:'#e0e2ea'}}>{fmt(o.total_cents)}</td>
                  <td>
                    <span className={`tag ${statusTag(o.payment_status)}`}>
                      {o.payment_status}
                    </span>
                  </td>
                  <td><span className="tag">{o.status}</span></td>
                  <td className="muted" style={{fontSize:'.8rem'}}>{o.created_at?.slice(0, 10)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </main>
  );
}
