export const dynamic = 'force-dynamic';
import * as products from '../../../src/models/products.js';
import * as settings from '../../../src/models/settings.js';

function formatPrice(cents) {
  try {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: settings.get('currency') || 'EUR' }).format((cents || 0) / 100);
  } catch { return `${((cents || 0) / 100).toFixed(2)} €`; }
}

function placeholder(name) {
  return `https://placehold.co/48x56/0f1115/969cb0?text=${encodeURIComponent((name || '?').slice(0, 1))}`;
}

export default function AdminProductsList() {
  const items = products.listAll();

  return (
    <main className="admin-main">
      <div className="admin-head-row">
        <h1>Produkte</h1>
        <a className="btn btn-primary" href="/admin/produkte/neu">+ Neues Produkt</a>
      </div>

      {items.length > 0 && (
        <input type="search" className="admin-search" data-product-filter="" placeholder="Produkte filtern… (Name, Kategorie, Status)" aria-label="Produkte filtern" />
      )}

      {items.length === 0 ? (
        <p className="muted">Noch keine Produkte. Lege das erste an.</p>
      ) : (
        <table className="data-table">
          <thead>
            <tr><th>Bild</th><th>Name</th><th>Kategorie</th><th>Preis</th><th>Lager</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            {items.map((p) => {
              const imgs = Array.isArray(p.images) ? p.images : [];
              const imgSrc = imgs[0]?.src || placeholder(p.name);
              return (
                <tr key={p.id}>
                  <td className="cell-img"><img src={imgSrc} alt="" /></td>
                  <td>
                    <strong>{p.name}</strong>
                    {!!p.is_bestseller && <span className="tag tag-gold">Bestseller</span>}
                  </td>
                  <td>{p.category}</td>
                  <td>
                    {p.sale_price_cents
                      ? <>{formatPrice(p.sale_price_cents)} <span className="muted" style={{textDecoration:'line-through'}}>{formatPrice(p.price_cents)}</span></>
                      : formatPrice(p.price_cents)}
                  </td>
                  <td>{p.stock}</td>
                  <td><span className={`tag${p.is_active ? ' tag-ok' : ' tag-off'}`}>{p.is_active ? 'aktiv' : 'inaktiv'}</span></td>
                  <td className="cell-actions">
                    <a className="btn btn-ghost btn-sm" href={`/admin/produkte/${p.id}`}>Bearbeiten</a>
                    <button className="btn btn-danger btn-sm" data-delete-product={String(p.id)} data-name={p.name}>Löschen</button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}
    </main>
  );
}
