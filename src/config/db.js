import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';
import { DATA_DIR } from './env.js';

// Datenverzeichnis sicherstellen
fs.mkdirSync(DATA_DIR, { recursive: true });

const dbPath = path.join(DATA_DIR, 'app.db');
const db = new Database(dbPath);

// Sinnvolle, sichere PRAGMAs
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

// Schema (idempotent)
db.exec(`
  CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
  );

  CREATE TABLE IF NOT EXISTS products (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    slug            TEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL,
    description     TEXT NOT NULL DEFAULT '',
    category        TEXT NOT NULL DEFAULT 'Allgemein',
    price_cents     INTEGER NOT NULL DEFAULT 0,
    sale_price_cents INTEGER,
    sizes           TEXT NOT NULL DEFAULT '[]',   -- JSON-Array
    option_groups   TEXT NOT NULL DEFAULT '[]',   -- JSON-Array von Gruppen + Werten
    images          TEXT NOT NULL DEFAULT '[]',   -- JSON-Array {type,src}
    stock           INTEGER NOT NULL DEFAULT 0,
    is_bestseller   INTEGER NOT NULL DEFAULT 0,
    is_active       INTEGER NOT NULL DEFAULT 1,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE INDEX IF NOT EXISTS idx_products_active   ON products(is_active);
  CREATE INDEX IF NOT EXISTS idx_products_category ON products(category);

  CREATE TABLE IF NOT EXISTS orders (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    reference     TEXT NOT NULL UNIQUE,
    customer_name TEXT NOT NULL,
    email         TEXT NOT NULL,
    address       TEXT NOT NULL DEFAULT '',
    items         TEXT NOT NULL DEFAULT '[]',   -- JSON-Snapshot
    total_cents   INTEGER NOT NULL DEFAULT 0,
    status        TEXT NOT NULL DEFAULT 'neu',
    payment_status TEXT NOT NULL DEFAULT 'offen',
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);

  CREATE TABLE IF NOT EXISTS order_messages (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    order_reference TEXT NOT NULL REFERENCES orders(reference) ON DELETE CASCADE,
    author_role     TEXT NOT NULL DEFAULT 'admin',
    author_name     TEXT NOT NULL DEFAULT '',
    subject         TEXT NOT NULL DEFAULT '',
    body            TEXT NOT NULL,
    is_system       INTEGER NOT NULL DEFAULT 0,
    is_read         INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE INDEX IF NOT EXISTS idx_order_messages_order ON order_messages(order_reference, created_at DESC);

  CREATE TABLE IF NOT EXISTS newsletter (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  );

  CREATE TABLE IF NOT EXISTS messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    email      TEXT NOT NULL,
    body       TEXT NOT NULL,
    is_read    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  );

  -- Lager: Bestand pro Produkt + Variante (Grösse + optional Farbe)
  CREATE TABLE IF NOT EXISTS inventory (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id      INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    sku             TEXT NOT NULL DEFAULT '',
    size            TEXT NOT NULL DEFAULT '',   -- '' = kein Grössen-Variant
    color           TEXT NOT NULL DEFAULT '',
    option_values   TEXT NOT NULL DEFAULT '[]',   -- JSON-Array von {group,value}
    stock           INTEGER NOT NULL DEFAULT 0,
    reserved        INTEGER NOT NULL DEFAULT 0, -- im Warenkorb reserviert
    min_stock       INTEGER NOT NULL DEFAULT 3, -- Warnschwelle
    next_delivery   TEXT NOT NULL DEFAULT '',   -- ISO-Datum
    notes           TEXT NOT NULL DEFAULT '',
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(product_id, size, color)
  );
  CREATE INDEX IF NOT EXISTS idx_inventory_product ON inventory(product_id);
`);

// Falls wir das Schema nachträglich erweitern: sicherstellen, dass die
// Spalten `title` und `images` existieren (für Varianten-Metadaten).
try {
  const info = db.prepare("PRAGMA table_info(inventory)").all();
  const cols = info.map((c) => c.name);
  if (!cols.includes('title')) {
    db.exec("ALTER TABLE inventory ADD COLUMN title TEXT NOT NULL DEFAULT '';");
  }
  if (!cols.includes('images')) {
    db.exec("ALTER TABLE inventory ADD COLUMN images TEXT NOT NULL DEFAULT '[]';");
  }
  if (!cols.includes('option_values')) {
    db.exec("ALTER TABLE inventory ADD COLUMN option_values TEXT NOT NULL DEFAULT '[]';");
  }
  if (!cols.includes('variant_price_cents')) {
    db.exec("ALTER TABLE inventory ADD COLUMN variant_price_cents INTEGER;");
  }
  if (!cols.includes('is_default')) {
    db.exec("ALTER TABLE inventory ADD COLUMN is_default INTEGER NOT NULL DEFAULT 0;");
  }
} catch (e) {
  // Falls ALTER TABLE fehlschlägt, nichts blockieren – existierende DB bleibt nutzbar.
  // Fehler werden nicht hochgeworfen, Admin kann später migrieren.
  console.warn('Schema-Update inventory skipped:', e?.message || e);
}

try {
  const info = db.prepare("PRAGMA table_info(products)").all();
  const cols = info.map((c) => c.name);
  if (!cols.includes('option_groups')) {
    db.exec("ALTER TABLE products ADD COLUMN option_groups TEXT NOT NULL DEFAULT '[]';");
  }
} catch (e) {
  console.warn('Schema-Update products skipped:', e?.message || e);
}

try {
  const info = db.prepare("PRAGMA table_info(orders)").all();
  const cols = info.map((c) => c.name);
  if (!cols.includes('phone'))          db.exec("ALTER TABLE orders ADD COLUMN phone TEXT NOT NULL DEFAULT '';");
  if (!cols.includes('payment_method')) db.exec("ALTER TABLE orders ADD COLUMN payment_method TEXT NOT NULL DEFAULT '';");
  if (!cols.includes('shipping_cents')) db.exec("ALTER TABLE orders ADD COLUMN shipping_cents INTEGER NOT NULL DEFAULT 0;");
} catch (e) {
  console.warn('Schema-Update orders skipped:', e?.message || e);
}

try {
  const info = db.prepare("PRAGMA table_info(order_messages)").all();
  const cols = info.map((c) => c.name);
  if (!cols.includes('subject')) db.exec("ALTER TABLE order_messages ADD COLUMN subject TEXT NOT NULL DEFAULT ''; ");
  if (!cols.includes('author_role')) db.exec("ALTER TABLE order_messages ADD COLUMN author_role TEXT NOT NULL DEFAULT 'admin';");
  if (!cols.includes('author_name')) db.exec("ALTER TABLE order_messages ADD COLUMN author_name TEXT NOT NULL DEFAULT ''; ");
  if (!cols.includes('is_system')) db.exec("ALTER TABLE order_messages ADD COLUMN is_system INTEGER NOT NULL DEFAULT 0;");
  if (!cols.includes('is_read')) db.exec("ALTER TABLE order_messages ADD COLUMN is_read INTEGER NOT NULL DEFAULT 0;");
  if (!cols.includes('order_reference')) db.exec("ALTER TABLE order_messages ADD COLUMN order_reference TEXT NOT NULL DEFAULT ''; ");
} catch (e) {
  console.warn('Schema-Update order_messages skipped:', e?.message || e);
}

export default db;
