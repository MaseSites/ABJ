export const dynamic = 'force-dynamic';
import { notFound } from 'next/navigation';
import * as products from '../../../../src/models/products.js';
import * as inventory from '../../../../src/models/inventory.js';

function centsToInput(c) {
  return c == null ? '' : (c / 100).toFixed(2);
}

function adminSlugify(text) {
  return String(text || '')
    .toLowerCase()
    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function buildOptionGroups(p, invRows) {
  if (Array.isArray(p?.option_groups) && p.option_groups.length > 0) return p.option_groups;
  const groups = [];
  const sizes = Array.isArray(p?.sizes) ? p.sizes : [];
  const colors = [...new Set(invRows.map((r) => r.color).filter(Boolean))];
  if (sizes.length > 0) groups.push({ key: 'size', label: 'Größe', values: sizes });
  if (colors.length > 0) groups.push({ key: 'color', label: 'Farbe', values: colors });
  return groups;
}

function buildExistingVariants(invRows, optionGroups) {
  return invRows.map((row) => {
    const ovParts = [];
    if (row.size)  ovParts.push({ key: 'size',  label: 'Größe', value: row.size });
    if (row.color) ovParts.push({ key: 'color', label: 'Farbe', value: row.color });
    const signature = ovParts.length > 0
      ? ovParts.map((ov) => `${adminSlugify(ov.key)}:${adminSlugify(ov.value)}`).join('__')
      : 'default';
    return {
      signature,
      size: '',   // leer lassen, damit admin.js per signature matched
      option_values:       ovParts,
      sku:                 row.sku || '',
      stock:               row.stock || 0,
      variant_price_cents: row.variant_price_cents ?? null,
      is_default:          !!row.is_default,
    };
  });
}

export default async function AdminProductEdit({ params }) {
  const { id } = await params;
  const isNew = id === 'neu';
  const p = isNew ? null : products.getById(parseInt(id, 10));
  if (!isNew && !p) return notFound();

  const formAction = isNew ? '/admin/api/products' : `/admin/api/products/${p.id}`;
  const method = isNew ? 'POST' : 'PUT';
  const title = isNew ? 'Neues Produkt' : p.name;

  const existingImages = Array.isArray(p?.images) ? p.images : [];

  const invRows = isNew ? [] : (inventory.byProduct(p.id) || []);
  const existingOptionGroups = buildOptionGroups(p, invRows);
  const existingVariantsForJS = buildExistingVariants(invRows, existingOptionGroups);

  const hasVariantsDefault = isNew
    ? true
    : existingOptionGroups.length > 0 || (invRows.length > 1) || (invRows.length === 1 && (invRows[0].size || invRows[0].color));

  return (
    <main className="admin-main narrow">
      <p className="admin-kicker">Produkte</p>
      <div className="admin-head-row">
        <h1>{title}</h1>
        <a className="btn btn-ghost" href="/admin/produkte">← Zurück</a>
      </div>

      <div id="form-alert" className="alert alert-error" hidden></div>

      <form
        id="product-form"
        data-action={formAction}
        data-method={method}
        className="admin-form product-editor"
      >
        {/* ── Schritt 1: Grunddaten ── */}
        <div className="admin-section">
          <div className="step-head">
            <span className="step-num">1</span>
            <div>
              <h2>Grunddaten</h2>
              <p>Name, Preis und Beschreibung</p>
            </div>
          </div>
          <div className="form-step">
            <div className="form-grid">
              <label className="field span-2">
                <span>Name *</span>
                <input type="text" name="name" required maxLength={160} defaultValue={p?.name || ''} placeholder="z.B. ABJ Essentials Hoodie" />
              </label>

              <label className="field">
                <span>Kategorie</span>
                <input type="text" name="category" maxLength={80} defaultValue={p?.category || ''} placeholder="z.B. Hoodies" />
              </label>

              <label className="field">
                <span>Preis (€)</span>
                <input type="text" name="price" inputMode="decimal" defaultValue={centsToInput(p?.price_cents)} placeholder="49.90" />
              </label>

              <label className="field">
                <span>Sale-Preis (€) <small className="muted">optional</small></span>
                <input type="text" name="sale_price" inputMode="decimal" defaultValue={centsToInput(p?.sale_price_cents)} placeholder="29.90" />
              </label>

              <label className="field span-2">
                <span>Beschreibung</span>
                <textarea name="description" rows={4} maxLength={8000} defaultValue={p?.description || ''} placeholder="Produktbeschreibung…" />
              </label>
            </div>
          </div>
        </div>

        {/* ── Schritt 2: Varianten (Größen & Farben) ── */}
        <div className="admin-section">
          <div className="step-head">
            <span className="step-num">2</span>
            <div>
              <h2>Größen & Farben</h2>
              <p>Varianten wählen — werden automatisch im Lager angelegt</p>
            </div>
          </div>
          <div className="form-step">
            <label className="switch-row" style={{ marginBottom: '1.2rem' }}>
              <input
                type="checkbox"
                name="has_variants"
                value="1"
                defaultChecked={hasVariantsDefault}
              />
              <div>
                <strong>Varianten aktivieren</strong>
                <small>Wähle Größen, Farben oder eigene Optionen. Bestand wird pro Variante verwaltet.</small>
              </div>
            </label>

            {/* Kein-Varianten-Bestand (nur wenn Toggle aus) */}
            <div id="no-variant-stock" hidden={hasVariantsDefault}>
              <label className="field" style={{ maxWidth: '180px' }}>
                <span>Lagerbestand</span>
                <input type="number" name="stock" min="0" max="1000000" defaultValue={p?.stock ?? 0} />
              </label>
            </div>

            {/* Option-Builder (wird von admin.js befüllt) */}
            <div id="option-builder" hidden={!hasVariantsDefault}>
              <div id="option-groups-list"></div>

              <div className="option-toolbar" style={{ marginTop: '1rem' }}>
                <button type="button" className="btn btn-ghost btn-sm" data-add-option="size">
                  + Größe hinzufügen
                </button>
                <button type="button" className="btn btn-ghost btn-sm" data-add-option="color">
                  + Farbe hinzufügen
                </button>
                <button type="button" className="btn btn-ghost btn-sm" data-add-option="custom">
                  + Eigene Option
                </button>
              </div>

              <div className="variant-preview-head" style={{ marginTop: '1.2rem' }}>
                <h3>Varianten & Bestand</h3>
                <span className="muted" style={{ fontSize: '.8rem' }}>Bestand für jede Variante eintragen</span>
              </div>
              <div id="variant-preview" className="variant-preview"></div>
            </div>
          </div>
        </div>

        {/* ── Schritt 3: Bilder ── */}
        <div className="admin-section">
          <div className="step-head">
            <span className="step-num">3</span>
            <div>
              <h2>Bilder</h2>
              <p>Bis zu 8 Bilder — das erste wird als Hauptbild angezeigt</p>
            </div>
          </div>
          <div className="form-step">
            <div className="existing-images" id="existing-images"></div>
            <p className="img-hint muted">Das erste Bild ist das Hauptbild. Reihenfolge mit den Pfeilen ändern, × entfernt ein Bild.</p>
            <div className="image-input-grid">
              <label className="field">
                <span>Bilder hochladen <small className="muted">(JPG, PNG, WEBP – max. 5 MB)</small></span>
                <input type="file" name="images" accept="image/*" multiple data-file-input />
              </label>
              <label className="field">
                <span>Oder Bild-URLs <small className="muted">(eine URL pro Zeile)</small></span>
                <textarea name="image_urls" rows={3} placeholder="https://..." />
              </label>
            </div>
            <div className="upload-preview" id="upload-preview"></div>
          </div>
        </div>

        {/* ── Schritt 4: Einstellungen ── */}
        <div className="admin-section">
          <div className="step-head">
            <span className="step-num">4</span>
            <div>
              <h2>Einstellungen</h2>
            </div>
          </div>
          <div className="form-step">
            <div className="field checkbox-row">
              <label className="checkbox">
                <input type="checkbox" name="is_active" value="1" defaultChecked={!p || !!p.is_active} />
                Aktiv (im Shop sichtbar)
              </label>
              <label className="checkbox">
                <input type="checkbox" name="is_bestseller" value="1" defaultChecked={!!p?.is_bestseller} />
                Als Bestseller markieren
              </label>
            </div>
          </div>
        </div>

        {/* ── Sticky Speichern-Leiste ── */}
        <div className="sticky-save">
          <p className="muted" style={{ margin: 0, fontSize: '.82rem' }}>
            {isNew
              ? 'Produkt wird gespeichert und automatisch ins Lager eingetragen.'
              : 'Änderungen werden gespeichert und im Lager synchronisiert.'}
          </p>
          <div className="form-footer" style={{ margin: 0 }}>
            <button className="btn btn-primary" type="submit">
              {isNew ? 'Produkt erstellen' : 'Änderungen speichern'}
            </button>
            <a className="btn btn-ghost" href="/admin/produkte">Abbrechen</a>
          </div>
        </div>
      </form>

      {/* Daten für admin.js */}
      <script
        type="application/json"
        id="existing-images-data"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(existingImages) }}
      />
      <script
        type="application/json"
        id="existing-option-groups-data"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(existingOptionGroups) }}
      />
      <script
        type="application/json"
        id="existing-variants-data"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(existingVariantsForJS) }}
      />
    </main>
  );
}
