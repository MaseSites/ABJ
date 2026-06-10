/**
 * Inventory-Modell – Lagerbestand pro Produkt / Variante (Grösse + Farbe).
 *
 * Konzept:
 * - Jede Kombination (product_id, size, color) ist eine eigene Zeile.
 * - Produkte OHNE Grössen-Varianten haben eine einzige Zeile mit size=''.
 * - `stock`    = Gesamtbestand dieser Variante.
 * - `reserved` = durch aktive Warenkörbe reservierte Menge (best-effort).
 * - Verfügbar  = stock - reserved (min 0).
 */

import db from '../config/db.js';

// ---------------------------------------------------------------------------
// Prepared statements
// ---------------------------------------------------------------------------

const byProductStmt = db.prepare(
  'SELECT * FROM inventory WHERE product_id = ? ORDER BY size, color'
);

const byVariantStmt = db.prepare(
  'SELECT * FROM inventory WHERE product_id = ? AND size = ? AND color = ?'
);

const upsertStmt = db.prepare(`
  INSERT INTO inventory (
    product_id, sku, size, color, option_values, stock, reserved, min_stock, next_delivery, notes, title, images, variant_price_cents, is_default
  )
  VALUES (
    @product_id, @sku, @size, @color, @option_values, @stock, @reserved, @min_stock, @next_delivery, @notes, @title, @images, @variant_price_cents, @is_default
  )
  ON CONFLICT(product_id, size, color) DO UPDATE SET
    sku          = excluded.sku,
    stock        = excluded.stock,
    min_stock    = excluded.min_stock,
    next_delivery= excluded.next_delivery,
    notes        = excluded.notes,
    title        = excluded.title,
    option_values= excluded.option_values,
    images       = excluded.images,
    variant_price_cents = excluded.variant_price_cents,
    is_default   = excluded.is_default,
    updated_at   = datetime('now')
`);

const updateStockStmt = db.prepare(
  `UPDATE inventory SET stock = ?, updated_at = datetime('now')
   WHERE product_id = ? AND size = ? AND color = ?`
);

const addReservedStmt = db.prepare(
  `UPDATE inventory SET reserved = MAX(0, reserved + ?), updated_at = datetime('now')
   WHERE product_id = ? AND size = ? AND color = ?`
);

const allLowStockStmt = db.prepare(`
  SELECT i.*, p.name AS product_name, p.category
  FROM inventory i
  JOIN products p ON p.id = i.product_id
  WHERE p.is_active = 1 AND i.stock <= i.min_stock
  ORDER BY i.stock ASC, p.name
`);

const allInventoryStmt = db.prepare(`
  SELECT i.*, p.name AS product_name, p.category, p.slug, p.price_cents, p.sale_price_cents, p.is_active
  FROM inventory i
  JOIN products p ON p.id = i.product_id
  ORDER BY p.name, i.size, i.color
`);

const deleteByProductStmt = db.prepare(
  'DELETE FROM inventory WHERE product_id = ?'
);

const countByProductStmt = db.prepare(
  'SELECT COUNT(*) AS n FROM inventory WHERE product_id = ?'
);

// Fallback-Quelle: gepoolter Gesamtbestand aus der products-Tabelle.
const productStockStmt = db.prepare('SELECT stock FROM products WHERE id = ?');
const productByIdStmt = db.prepare('SELECT * FROM products WHERE id = ?');
const decProductStockStmt = db.prepare(
  `UPDATE products SET stock = MAX(0, stock - ?), updated_at = datetime('now') WHERE id = ?`
);
const setProductStockStmt = db.prepare(
  `UPDATE products SET stock = ?, updated_at = datetime('now') WHERE id = ?`
);

// ---------------------------------------------------------------------------
// Fallback-Helpers
// ---------------------------------------------------------------------------

/** Anzahl Inventory-Zeilen eines Produkts. */
function rowCount(productId) {
  return countByProductStmt.get(productId).n;
}

/** products.stock-Spalte (Fallback-Quelle), nie negativ. */
function productStockFallback(productId) {
  const row = productStockStmt.get(productId);
  return Math.max(0, row?.stock ?? 0);
}

/** Hat das Produkt überhaupt schon Lager-Zeilen? */
export function hasInventory(productId) {
  return rowCount(productId) > 0;
}

/** Hat das Produkt Varianten, die der Kunde wählen muss? (size/color nicht leer) */
export function hasVariants(productId) {
  const rows = byProductStmt.all(productId);
  return rows.some((r) => r.size || r.color);
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/** Alle Inventory-Zeilen für ein Produkt. */
export function byProduct(productId) {
  return byProductStmt.all(productId);
}

/** Einzelne Inventory-Zeile nach Produkt + Schlüssel (size) */
export function byVariant(productId, size = '', color = '') {
  return byVariantStmt.get(productId, size, color);
}

/**
 * Verfügbarer Bestand für eine konkrete Variante.
 *
 * Fallback: Hat das Produkt GAR KEINE Lager-Zeilen, gilt der gepoolte
 * `products.stock` für jede Variante – bis das Lager im Dashboard
 * (/admin/lager/[ID]) pro Grösse befüllt ist. Sobald mindestens eine Zeile
 * existiert, sind die Lager-Zeilen massgeblich (fehlende Variante = 0).
 */
export function stockForVariant(productId, size = '', color = '') {
  const row = byVariantStmt.get(productId, size, color);
  if (row) return Math.max(0, row.stock - row.reserved);
  if (rowCount(productId) === 0) return productStockFallback(productId);
  return 0;
}

/** Gesamtbestand für ein Produkt (alle Varianten, sonst Fallback products.stock). */
export function totalStock(productId) {
  const rows = byProductStmt.all(productId);
  if (rows.length === 0) return productStockFallback(productId);
  return rows.reduce((s, r) => s + Math.max(0, r.stock - r.reserved), 0);
}

/** Ist eine spezifische Variante verfügbar? */
export function isAvailable(productId, size = '', color = '') {
  return stockForVariant(productId, size, color) > 0;
}

/** Upsert: Bestand setzen (aus Dashboard oder Seed). */
export function upsert(data) {
  upsertStmt.run({
    product_id: data.product_id,
    sku: data.sku || '',
    size: data.size || '',
    color: data.color || '',
    option_values: JSON.stringify(Array.isArray(data.option_values) ? data.option_values : []),
    stock: Math.max(0, Number(data.stock) || 0),
    reserved: Math.max(0, Number(data.reserved) || 0),
    min_stock: Math.max(0, Number(data.min_stock) ?? 3),
    next_delivery: data.next_delivery || '',
    notes: data.notes || '',
    title: data.title || '',
    images: JSON.stringify(Array.isArray(data.images) ? data.images : []),
    variant_price_cents: data.variant_price_cents ?? null,
    is_default: data.is_default ? 1 : 0,
  });
}

/** Mehrere Varianten für ein Produkt auf einmal speichern (Batch aus Formular). */
export function upsertMany(rows) {
  const tx = db.transaction((rows) => {
    if (!rows || rows.length === 0) return;
    for (const r of rows) upsert(r);

    const productId = rows[0].product_id;
    // If any incoming row requests is_default, enforce single default.
    const incomingDefaultIndex = rows.findIndex((r) => !!r.is_default);
    if (incomingDefaultIndex !== -1) {
      // clear existing defaults, then set the requested one
      db.prepare('UPDATE inventory SET is_default = 0 WHERE product_id = ?').run(productId);
      const key = rows[incomingDefaultIndex].size || '';
      db.prepare('UPDATE inventory SET is_default = 1 WHERE product_id = ? AND size = ? AND color = ?').run(productId, key, '');
    } else {
      // Ensure there is at least one default: if none exists, set the first of the batch
      const cnt = db.prepare('SELECT COUNT(*) AS n FROM inventory WHERE product_id = ? AND is_default = 1').get(productId).n;
      if (cnt === 0) {
        const key = rows[0].size || '';
        db.prepare('UPDATE inventory SET is_default = 1 WHERE product_id = ? AND size = ? AND color = ?').run(productId, key, '');
      }
    }
  });
  tx(rows);
}

/** Löscht alle Inventory-Zeilen eines Produkts (z. B. vor Neueingabe). */
export function deleteByProduct(productId) {
  return deleteByProductStmt.run(productId).changes;
}

/** Alle Produkte mit niedrigem Bestand (stock ≤ min_stock). */
export function lowStock() {
  return allLowStockStmt.all();
}

/** Vollständige Lager-Übersicht (für Admin-Tab). */
export function allInventory() {
  return allInventoryStmt.all().map((r) => ({
    ...r,
    available: Math.max(0, r.stock - r.reserved),
    is_out: r.stock - r.reserved <= 0,
    is_low: r.stock > 0 && r.stock - r.reserved <= r.min_stock,
  }));
}

export function adjustStock(productId, size = '', color = '', delta = 0) {
  const tx = db.transaction(() => {
    const product = productByIdStmt.get(productId);
    if (!product) return null;

    const row = byVariantStmt.get(productId, size || '', color || '');
    if (row) {
      const next = Math.max(0, row.stock + Number(delta || 0));
      updateStockStmt.run(next, productId, size || '', color || '');
    } else {
      upsert({
        product_id: productId,
        size: size || '',
        color: color || '',
        stock: Math.max(0, (Number(product.stock) || 0) + Number(delta || 0)),
        min_stock: 3,
        title: product.name,
        is_default: true,
      });
    }

    const total = totalStock(productId);
    setProductStockStmt.run(total, productId);
    return { stock: stockForVariant(productId, size || '', color || ''), total };
  });
  return tx();
}

/**
 * Reserviert Menge (positive delta) oder gibt sie frei (negative delta).
 * Wird beim Hinzufügen / Entfernen aus dem Warenkorb aufgerufen.
 */
export function changeReserved(productId, size = '', color = '', delta = 0) {
  addReservedStmt.run(delta, productId, size, color);
}

/**
 * Überprüft einen Warenkorb gegen den aktuellen Bestand.
 * Gibt ein Array von Problemen zurück, oder [].
 */
export function validateCart(cartLines) {
  const issues = [];
  for (const line of cartLines) {
    const avail = stockForVariant(line.productId, line.size || '', '');
    if (avail <= 0) {
      issues.push({
        productId: line.productId,
        size: line.size,
        type: 'sold_out',
        message: `"${line.name || 'Produkt'}" ist ausverkauft.`,
        maxQty: 0,
      });
    } else if (line.qty > avail) {
      issues.push({
        productId: line.productId,
        size: line.size,
        type: 'qty_exceeded',
        message: `Nur noch ${avail} Stück von "${line.name || 'Produkt'}" verfügbar.`,
        maxQty: avail,
      });
    }
  }
  return issues;
}

/**
 * Löscht alle Inventory-Zeilen eines Produkts und legt neue an.
 * Erhält bestehende `reserved`-Werte (Warenkörbe) für gleichnamige Varianten.
 */
export function syncVariants(productId, rows) {
  const tx = db.transaction((rows) => {
    // Reservierungen sichern
    const existing = byProductStmt.all(productId);
    const reservedMap = new Map(existing.map((r) => [`${r.size || ''}|${r.color || ''}`, r.reserved || 0]));

    // Alle alten Zeilen löschen
    deleteByProductStmt.run(productId);

    // Neu anlegen
    for (const r of rows) {
      upsertStmt.run({
        product_id: productId,
        sku: r.sku || '',
        size: r.size || '',
        color: r.color || '',
        option_values: JSON.stringify(Array.isArray(r.option_values) ? r.option_values : []),
        stock: Math.max(0, Number(r.stock) || 0),
        reserved: reservedMap.get(`${r.size || ''}|${r.color || ''}`) || 0,
        min_stock: r.min_stock ?? 3,
        next_delivery: '',
        notes: '',
        title: r.title || '',
        images: JSON.stringify([]),
        variant_price_cents: r.variant_price_cents ?? null,
        is_default: r.is_default ? 1 : 0,
      });
    }

    // Produktbestand aktualisieren
    const total = rows.reduce((s, r) => s + Math.max(0, Number(r.stock) || 0), 0);
    setProductStockStmt.run(total, productId);
  });
  tx(rows);
}

/**
 * Wird nach erfolgtem Checkout aufgerufen, um den Bestand zu reduzieren.
 */
export function deductStock(cartLines) {
  const tx = db.transaction((lines) => {
    for (const line of lines) {
      const size = line.size || '';
      const variant = byVariantStmt.get(line.productId, size, '');
      if (variant) {
        updateStockStmt.run(Math.max(0, variant.stock - line.qty), line.productId, size, '');
      } else if (rowCount(line.productId) === 0) {
        // Fallback-Produkt ohne Lager-Zeilen: direkt products.stock abbuchen.
        decProductStockStmt.run(line.qty, line.productId);
      }
      // sonst: Variante fehlt, aber andere Zeilen existieren -> nichts abbuchen.
    }
  });
  tx(cartLines);
}
