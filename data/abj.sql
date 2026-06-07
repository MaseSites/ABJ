PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT
);

CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  category TEXT NOT NULL DEFAULT 'Allgemein',
  price_cents INTEGER NOT NULL DEFAULT 0,
  sale_price_cents INTEGER,
  sizes TEXT NOT NULL DEFAULT '[]',
  option_groups TEXT NOT NULL DEFAULT '[]',
  images TEXT NOT NULL DEFAULT '[]',
  stock INTEGER NOT NULL DEFAULT 0,
  is_bestseller INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS inventory (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  sku TEXT NOT NULL DEFAULT '',
  size TEXT NOT NULL DEFAULT '',
  color TEXT NOT NULL DEFAULT '',
  option_values TEXT NOT NULL DEFAULT '[]',
  stock INTEGER NOT NULL DEFAULT 0,
  reserved INTEGER NOT NULL DEFAULT 0,
  min_stock INTEGER NOT NULL DEFAULT 3,
  next_delivery TEXT NOT NULL DEFAULT '',
  notes TEXT NOT NULL DEFAULT '',
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  title TEXT NOT NULL DEFAULT '',
  images TEXT NOT NULL DEFAULT '[]',
  variant_price_cents INTEGER,
  is_default INTEGER NOT NULL DEFAULT 0,
  UNIQUE(product_id, size, color)
);

CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  reference TEXT NOT NULL UNIQUE,
  customer_name TEXT NOT NULL,
  email TEXT NOT NULL,
  address TEXT NOT NULL DEFAULT '',
  items TEXT NOT NULL DEFAULT '[]',
  total_cents INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'neu',
  payment_status TEXT NOT NULL DEFAULT 'offen',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS newsletter (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL,
  body TEXT NOT NULL,
  is_read INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

INSERT OR REPLACE INTO settings(key, value) VALUES
('shop_name', 'ABJ Store'),
('tagline', 'Kuratierte Vintage- & Premiummode'),
('hero_title', 'Frühlings-Sale – bis zu -70%'),
('hero_subtitle', 'Limitierte Boxen & Einzelstücke. Nur solange der Vorrat reicht.'),
('sale_ends_at', '2026-06-22T23:59'),
('members_count', '20000'),
('ratings_count', '1000'),
('contact_email', 'kontakt@abj-store.example');

INSERT OR REPLACE INTO products(id, slug, name, description, category, price_cents, sale_price_cents, sizes, option_groups, images, stock, is_bestseller, is_active, created_at, updated_at) VALUES
(1, 'vintage-hoodie-heritage', 'Vintage Hoodie – Heritage', '<p>Schwerer Baumwoll-Hoodie im Used-Look. Unisex-Schnitt.</p>', 'Hoodies', 7900, 4900, '["S","M","L","XL"]', '[]', '[]', 25, 1, 1, datetime('now'), datetime('now')),
(2, 'premium-mystery-box-groesse-m', 'Premium Mystery Box – Größe M', '<p>Überraschungs-Box mit kuratierten Vintage-Teilen. Wert deutlich über dem Preis.</p>', 'Boxen', 14900, 8900, '["M"]', '[]', '[]', 12, 1, 1, datetime('now'), datetime('now')),
(3, 'classic-polo-marine', 'Classic Polo – Marine', '<p>Zeitloses Polo aus Piqué-Baumwolle.</p>', 'Polos', 3900, NULL, '["S","M","L"]', '[]', '[]', 40, 0, 1, datetime('now'), datetime('now')),
(4, 'sweatpants-relaxed-fit', 'Sweatpants – Relaxed Fit', '<p>Bequeme Jogginghose mit elastischem Bund.</p>', 'Hosen', 5900, 3900, '["S","M","L","XL"]', '[]', '[]', 0, 0, 1, datetime('now'), datetime('now'));
