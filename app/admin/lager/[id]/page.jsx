export const dynamic = 'force-dynamic';
import { notFound } from 'next/navigation';
import * as products from '../../../../src/models/products.js';
import * as inventory from '../../../../src/models/inventory.js';

export default async function AdminLagerProduct({ params }) {
  const { id } = await params;
  const product = products.getById(parseInt(id, 10));
  if (!product) return notFound();

  const rows = inventory.byProduct(product.id) || [];
  const sizes = Array.isArray(product.sizes) ? product.sizes : [];

  return (
    <main className="admin-main narrow">
      <div className="admin-head-row">
        <div>
          <h1>Lager · {product.name}</h1>
          <p className="muted">Kategorie: {product.category} · Produktbestand gesamt: {product.stock}</p>
        </div>
        <div style={{ display: 'flex', gap: '.6rem' }}>
          <a className="btn btn-ghost" href="/admin/lager">Zurück</a>
          <a className="btn btn-ghost" href={`/admin/produkte/${product.id}`}>Produkt bearbeiten</a>
        </div>
      </div>

      <div id="inv-alert" className="alert alert-error" hidden></div>
      <div id="inv-ok" className="alert alert-ok" hidden>Gespeichert.</div>

      <input type="hidden" id="product-id-val" value={product.id} />

      <div className="admin-section">
        <p className="muted">
          Trage für jede Grösse/Variante den Bestand ein.
          Varianten mit Bestand 0 erscheinen im Shop als ausverkauft.
        </p>

        <div style={{ overflowX: 'auto' }}>
          <table className="data-table" id="inv-table">
            <thead>
              <tr>
                <th>Grösse</th>
                <th>Farbe</th>
                <th>SKU</th>
                <th>Bestand</th>
                <th>Reserviert</th>
                <th>Verfügbar</th>
                <th>Minimum</th>
                <th>Lieferung</th>
                <th>Notizen</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="inv-rows">
              {rows.map((r, i) => (
                <tr key={i}>
                  <td><input className="inv-field" name="size" type="text" defaultValue={r.size || ''} maxLength={50} placeholder="M" /></td>
                  <td><input className="inv-field" name="color" type="text" defaultValue={r.color || ''} maxLength={50} /></td>
                  <td><input className="inv-field" name="sku" type="text" defaultValue={r.sku || ''} maxLength={100} placeholder="ABJ-001" /></td>
                  <td><input className="inv-field inv-stock" name="stock" type="number" min="0" max="999999" defaultValue={r.stock || 0} style={{ width: '80px' }} /></td>
                  <td className="muted">{r.reserved || 0}</td>
                  <td className="inv-avail"><strong>{Math.max(0, (r.stock || 0) - (r.reserved || 0))}</strong></td>
                  <td><input className="inv-field" name="min_stock" type="number" min="0" max="1000" defaultValue={r.min_stock ?? 3} style={{ width: '70px' }} /></td>
                  <td><input className="inv-field" name="next_delivery" type="date" defaultValue={r.next_delivery || ''} /></td>
                  <td><input className="inv-field" name="notes" type="text" defaultValue={r.notes || ''} maxLength={500} style={{ minWidth: '140px' }} /></td>
                  <td><button type="button" className="btn btn-danger btn-sm" data-remove-row="">✕</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div style={{ display: 'flex', gap: '.8rem', marginTop: '1.2rem', flexWrap: 'wrap' }}>
          <button type="button" className="btn btn-ghost btn-sm" id="add-row">+ Variante hinzufügen</button>
          {sizes.length > 0 && (
            <button
              type="button"
              className="btn btn-ghost btn-sm"
              id="fill-sizes"
              data-sizes={JSON.stringify(sizes)}
            >
              Grössen aus Produkt übernehmen
            </button>
          )}
          <button type="button" className="btn btn-primary" id="save-inv">Speichern</button>
        </div>
      </div>
    </main>
  );
}
