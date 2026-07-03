import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatPrice, parsePriceToCents, discountPercent, placeholder } from '../src/lib/format.js';
import { slugify } from '../src/models/products.js';
import * as inventory from '../src/models/inventory.js';
import * as products from '../src/models/products.js';

// ---------------------------------------------------------------------------
// format.js
// ---------------------------------------------------------------------------

test('parsePriceToCents akzeptiert Komma und Punkt', () => {
  assert.equal(parsePriceToCents('19,99'), 1999);
  assert.equal(parsePriceToCents('19.99'), 1999);
  assert.equal(parsePriceToCents('100'), 10000);
  assert.equal(parsePriceToCents('0'), 0);
  assert.equal(parsePriceToCents(''), null);
  assert.equal(parsePriceToCents(null), null);
  assert.equal(parsePriceToCents('-5'), null);
  assert.equal(parsePriceToCents('abc'), null);
});

test('formatPrice formatiert in EUR', () => {
  const out = formatPrice(4900, 'EUR');
  assert.match(out, /49/);
  assert.match(out, /€/);
});

test('formatPrice gibt 0 korrekt aus', () => {
  const out = formatPrice(0, 'EUR');
  assert.match(out, /0/);
  assert.match(out, /€/);
});

test('discountPercent berechnet Rabatt', () => {
  assert.equal(discountPercent(10000, 5000), 50);
  assert.equal(discountPercent(10000, null), 0);
  assert.equal(discountPercent(10000, 12000), 0);
  assert.equal(discountPercent(10000, 10000), 0); // kein Rabatt
  assert.equal(discountPercent(0, 0), 0);
  assert.equal(discountPercent(15000, 10000), 33);
});

test('slugify erzeugt URL-sichere Slugs (inkl. Umlaute)', () => {
  assert.equal(slugify('Größe XL Hoodie'), 'groesse-xl-hoodie');
  assert.equal(slugify('  Hallo Welt!  '), 'hallo-welt');
  assert.equal(slugify('@@@'), 'produkt');
  assert.equal(slugify('Fear of God'), 'fear-of-god');
  assert.equal(slugify('REPRESENT.'), 'represent');
});

test('placeholder erzeugt sichere SVG-data-URI', () => {
  const uri = placeholder('Vintage Hoodie');
  assert.match(uri, /^data:image\/svg\+xml,/);
  assert.ok(uri.length > 50);
  assert.equal(placeholder('Test'), placeholder('Test')); // deterministisch
  assert.ok(!decodeURIComponent(placeholder('<b>x')).includes('<b>')); // XSS-safe
  assert.ok(!decodeURIComponent(placeholder('<script>')).includes('<script>'));
});

// ---------------------------------------------------------------------------
// inventory.js - Bestandslogik (in-memory, nutzt die In-Memory-DB für Tests)
// ---------------------------------------------------------------------------

let testProductId;

test('Produkt für Inventory-Tests anlegen', () => {
  const p = products.create({
    name: 'Test Hoodie Unit',
    category: 'Hoodies',
    price_cents: 7900,
    sale_price_cents: null,
    sizes: ['S', 'M', 'L', 'XL'],
    images: [],
    stock: 20,
    is_bestseller: false,
    is_active: true,
  });
  assert.ok(p.id > 0, 'Produkt wurde erstellt');
  testProductId = p.id;
});

test('Inventory upsert und stockForVariant', () => {
  inventory.upsert({ product_id: testProductId, size: 'M', color: '', stock: 7, reserved: 0, min_stock: 2 });
  inventory.upsert({ product_id: testProductId, size: 'L', color: '', stock: 3, reserved: 0, min_stock: 2 });
  inventory.upsert({ product_id: testProductId, size: 'S', color: '', stock: 0, reserved: 0, min_stock: 2 });

  assert.equal(inventory.stockForVariant(testProductId, 'M', ''), 7);
  assert.equal(inventory.stockForVariant(testProductId, 'L', ''), 3);
  assert.equal(inventory.stockForVariant(testProductId, 'S', ''), 0);
  assert.equal(inventory.stockForVariant(testProductId, 'XL', ''), 0); // nicht eingetragen
});

test('isAvailable prüft Verfügbarkeit', () => {
  assert.equal(inventory.isAvailable(testProductId, 'M', ''), true);
  assert.equal(inventory.isAvailable(testProductId, 'S', ''), false);
  assert.equal(inventory.isAvailable(testProductId, 'XL', ''), false);
});

test('totalStock summiert alle Varianten', () => {
  const total = inventory.totalStock(testProductId);
  assert.equal(total, 10); // 7 + 3
});

test('validateCart erkennt ausverkaufte Variante', () => {
  const issues = inventory.validateCart([
    { productId: testProductId, size: 'S', name: 'Test Hoodie Unit', qty: 1 },
  ]);
  assert.equal(issues.length, 1);
  assert.equal(issues[0].type, 'sold_out');
  assert.equal(issues[0].maxQty, 0);
});

test('validateCart erlaubt Menge ≤ Bestand', () => {
  const issues = inventory.validateCart([
    { productId: testProductId, size: 'M', name: 'Test Hoodie Unit', qty: 7 },
  ]);
  assert.equal(issues.length, 0);
});

test('validateCart blockiert Menge > Bestand', () => {
  const issues = inventory.validateCart([
    { productId: testProductId, size: 'M', name: 'Test Hoodie Unit', qty: 8 },
  ]);
  assert.equal(issues.length, 1);
  assert.equal(issues[0].type, 'qty_exceeded');
  assert.equal(issues[0].maxQty, 7);
  assert.match(issues[0].message, /7/);
});

test('validateCart leerer Warenkorb = keine Fehler', () => {
  assert.equal(inventory.validateCart([]).length, 0);
});

test('validateCart mehrere Artikel gleichzeitig', () => {
  const issues = inventory.validateCart([
    { productId: testProductId, size: 'M', name: 'Test Hoodie Unit', qty: 7 },
    { productId: testProductId, size: 'L', name: 'Test Hoodie Unit', qty: 3 },
  ]);
  assert.equal(issues.length, 0);
});

test('deductStock reduziert Bestand', () => {
  const before = inventory.stockForVariant(testProductId, 'L', '');
  inventory.deductStock([{ productId: testProductId, size: 'L', qty: 1 }]);
  const after = inventory.stockForVariant(testProductId, 'L', '');
  assert.equal(after, before - 1);
});

test('deductStock kann nicht unter 0 gehen', () => {
  // Versuche mehr abzubuchen als vorhanden
  inventory.deductStock([{ productId: testProductId, size: 'L', qty: 999 }]);
  const stock = inventory.stockForVariant(testProductId, 'L', '');
  assert.ok(stock >= 0, 'Bestand darf nicht negativ werden');
});

test('allInventory liefert alle Zeilen mit verfügbar/niedrig Flags', () => {
  const all = inventory.allInventory();
  assert.ok(Array.isArray(all));
  const row = all.find((r) => r.product_id === testProductId && r.size === 'M');
  assert.ok(row, 'Zeile für M gefunden');
  assert.ok('available' in row, 'available Feld vorhanden');
  assert.ok('is_low' in row, 'is_low Feld vorhanden');
  assert.ok('is_out' in row, 'is_out Feld vorhanden');
});

test('byProduct liefert nur Zeilen dieses Produkts', () => {
  const rows = inventory.byProduct(testProductId);
  assert.ok(rows.length >= 3);
  assert.ok(rows.every((r) => r.product_id === testProductId));
});

test('upsertMany speichert mehrere Varianten', () => {
  const p2 = products.create({
    name: 'Test Sneaker Batch',
    category: 'Schuhe',
    price_cents: 12900,
    sale_price_cents: null,
    sizes: ['41', '42', '43'],
    images: [],
    stock: 0,
    is_bestseller: false,
    is_active: true,
  });

  inventory.upsertMany([
    { product_id: p2.id, size: '41', color: '', stock: 2, min_stock: 1 },
    { product_id: p2.id, size: '42', color: '', stock: 5, min_stock: 2 },
    { product_id: p2.id, size: '43', color: '', stock: 0, min_stock: 1 },
  ]);

  assert.equal(inventory.stockForVariant(p2.id, '41', ''), 2);
  assert.equal(inventory.stockForVariant(p2.id, '42', ''), 5);
  assert.equal(inventory.stockForVariant(p2.id, '43', ''), 0);
});

// ---------------------------------------------------------------------------
// products.js - Produkte & Sichtbarkeit
// ---------------------------------------------------------------------------

test('Inaktives Produkt erscheint nicht in listPublic', () => {
  const p = products.create({
    name: 'Inaktives Produkt Test',
    category: 'Test',
    price_cents: 1000,
    sale_price_cents: null,
    sizes: [],
    images: [],
    stock: 5,
    is_bestseller: false,
    is_active: false, // INAKTIV
  });
  const all = products.listPublic();
  assert.ok(!all.find((x) => x.id === p.id), 'Inaktives Produkt nicht in listPublic');
});

test('Aktives Produkt erscheint in listPublic', () => {
  const p = products.create({
    name: 'Aktives Produkt Test',
    category: 'Test',
    price_cents: 5000,
    sale_price_cents: null,
    sizes: [],
    images: [],
    stock: 3,
    is_bestseller: false,
    is_active: true,
  });
  const all = products.listPublic();
  assert.ok(all.find((x) => x.id === p.id), 'Aktives Produkt in listPublic');
});

test('Bestseller erscheint in bestsellers()', () => {
  const p = products.create({
    name: 'Bestseller Test',
    category: 'Test',
    price_cents: 8000,
    sale_price_cents: null,
    sizes: [],
    images: [],
    stock: 10,
    is_bestseller: true,
    is_active: true,
  });
  const bs = products.bestsellers(50);
  assert.ok(bs.find((x) => x.id === p.id), 'Bestseller in bestsellers()');
});

test('Sale-Preis wird korrekt gespeichert und gelesen', () => {
  const p = products.create({
    name: 'Sale Produkt Test',
    category: 'Test',
    price_cents: 10000,
    sale_price_cents: 6500,
    sizes: [],
    images: [],
    stock: 1,
    is_bestseller: false,
    is_active: true,
  });
  const fetched = products.getById(p.id);
  assert.equal(fetched.price_cents, 10000);
  assert.equal(fetched.sale_price_cents, 6500);
  assert.equal(discountPercent(fetched.price_cents, fetched.sale_price_cents), 35);
});

test('Update des Produktnamens ändert auch den Slug', () => {
  const ts = Date.now();
  const p = products.create({
    name: `Original Slug ${ts}`,
    category: 'Test',
    price_cents: 1000,
    sale_price_cents: null,
    sizes: [],
    images: [],
    stock: 0,
    is_bestseller: false,
    is_active: true,
  });
  assert.equal(p.slug, `original-slug-${ts}`);
  const updated = products.update(p.id, { ...p, name: `Neuer Name ${ts}` });
  assert.equal(updated.slug, `neuer-name-${ts}`);
});

test('search findet Produkte nach Name', () => {
  products.create({
    name: 'Exclusive Vintage Hoodie Search',
    category: 'Hoodies',
    price_cents: 7900,
    sale_price_cents: null,
    sizes: ['M'],
    images: [],
    stock: 5,
    is_bestseller: false,
    is_active: true,
  });
  const results = products.search({ q: 'Exclusive Vintage Hoodie Search' });
  assert.ok(results.length >= 1);
  assert.ok(results.some((r) => r.name.includes('Exclusive Vintage Hoodie')));
});
