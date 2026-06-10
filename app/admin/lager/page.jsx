export const dynamic = 'force-dynamic';
import * as inventory from '../../../src/models/inventory.js';
import * as products from '../../../src/models/products.js';

export default function AdminLager() {
  const items = inventory.allInventory() || [];
  const warnings = inventory.lowStock() || [];
  const allProducts = products.listAll();

  return (
    <main className="admin-main">
      <p className="admin-kicker">Bestandsverwaltung</p>
      <div className="admin-head-row">
        <h1>Lager</h1>
        <span className="tag">{items.length} Varianten</span>
      </div>

      {warnings.length > 0 && (
        <div className="alert alert-error" style={{ marginBottom: '1.5rem' }}>
          <strong>⚠ Niedriger Bestand</strong> –{' '}
          {warnings.slice(0, 3).map((w, i) => (
            <span key={i}>
              {w.product_name}{w.size ? ` (${w.size})` : ''}: <strong>{w.stock}</strong> Stk
              {i < Math.min(warnings.length, 3) - 1 ? ' · ' : ''}
            </span>
          ))}
          {warnings.length > 3 && ` und ${warnings.length - 3} weitere`}
        </div>
      )}

      <div className="admin-section">
        <div style={{ display: 'flex', gap: '.8rem', marginBottom: '1.2rem', flexWrap: 'wrap' }}>
          <input className="admin-search" data-product-filter="" placeholder="Produkt oder SKU filtern…" style={{ maxWidth: '340px' }} />
          <a className="btn btn-primary btn-sm" href="/admin/produkte/neu">+ Neues Produkt</a>
        </div>

        {items.length === 0 ? (
          <p className="muted">Noch keine Lagereinträge. Produkte anlegen und dann hier Bestand eintragen.</p>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Produkt</th>
                <th>Grösse</th>
                <th>SKU</th>
                <th>Bestand</th>
                <th>Reserviert</th>
                <th>Verfügbar</th>
                <th>Status</th>
                <th>Nächste Lieferung</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {items.map((it, i) => {
                const available = Math.max(0, (it.stock || 0) - (it.reserved || 0));
                const isOut = (it.stock || 0) <= 0;
                const isLow = !isOut && it.is_low;
                return (
                  <tr key={i} style={isOut ? { opacity: 0.6 } : {}}>
                    <td><strong>{it.product_name}</strong><br /><span className="muted small">{it.category}</span></td>
                    <td>{it.size || '—'}</td>
                    <td className="muted small">{it.sku || '—'}</td>
                    <td>{it.stock}</td>
                    <td className="muted">{it.reserved || 0}</td>
                    <td><strong style={isOut ? { color: 'var(--danger)' } : {}}>{available}</strong></td>
                    <td>
                      {isOut
                        ? <span className="tag">Ausverkauft</span>
                        : isLow
                          ? <span className="tag tag-gold">Niedrig</span>
                          : <span className="tag tag-ok">OK</span>
                      }
                    </td>
                    <td className="muted small">{it.next_delivery || '—'}</td>
                    <td><a className="btn btn-ghost btn-sm" href={`/admin/lager/${it.product_id}`}>Bearbeiten</a></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>

      <section className="admin-section" style={{ marginTop: '1.5rem' }}>
        <h2>Lagerbestand schnell befüllen</h2>
        <p className="muted">Wähle ein Produkt und trage den Bestand pro Variante ein.</p>
        <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginTop: '1rem' }}>
          {allProducts.slice(0, 12).map((p) => (
            <a key={p.id} className="btn btn-ghost btn-sm" href={`/admin/lager/${p.id}`}>{p.name}</a>
          ))}
        </div>
      </section>
    </main>
  );
}
