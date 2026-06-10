-- ABJ Store – Datenbank-Migration
-- Stelle sicher, dass alle benötigten Spalten und Tabellen existieren.
-- Sicher mehrfach ausführbar (IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).

PRAGMA journal_mode = WAL;

-- ============================================================
-- Tabelle: products
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  slug             TEXT    NOT NULL DEFAULT '',
  name             TEXT    NOT NULL,
  description      TEXT    NOT NULL DEFAULT '',
  category         TEXT    NOT NULL DEFAULT 'Allgemein',
  price_cents      INTEGER NOT NULL DEFAULT 0,
  sale_price_cents INTEGER,
  sizes            TEXT    NOT NULL DEFAULT '[]',
  option_groups    TEXT    NOT NULL DEFAULT '[]',
  images           TEXT    NOT NULL DEFAULT '[]',
  stock            INTEGER NOT NULL DEFAULT 0,
  is_bestseller    INTEGER NOT NULL DEFAULT 0,
  is_active        INTEGER NOT NULL DEFAULT 1,
  created_at       TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at       TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- Optionale Spalten nachträglich hinzufügen (ignoriert wenn schon vorhanden)
ALTER TABLE products ADD COLUMN sale_price_cents INTEGER;
ALTER TABLE products ADD COLUMN sizes            TEXT NOT NULL DEFAULT '[]';
ALTER TABLE products ADD COLUMN option_groups    TEXT NOT NULL DEFAULT '[]';

-- ============================================================
-- Tabelle: inventory
-- ============================================================
CREATE TABLE IF NOT EXISTS inventory (
  id                  INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id          INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  sku                 TEXT    NOT NULL DEFAULT '',
  size                TEXT    NOT NULL DEFAULT '',
  color               TEXT    NOT NULL DEFAULT '',
  option_values       TEXT    NOT NULL DEFAULT '[]',
  stock               INTEGER NOT NULL DEFAULT 0,
  reserved            INTEGER NOT NULL DEFAULT 0,
  min_stock           INTEGER NOT NULL DEFAULT 3,
  next_delivery       TEXT    NOT NULL DEFAULT '',
  notes               TEXT    NOT NULL DEFAULT '',
  title               TEXT    NOT NULL DEFAULT '',
  images              TEXT    NOT NULL DEFAULT '[]',
  variant_price_cents INTEGER,
  is_default          INTEGER NOT NULL DEFAULT 0,
  updated_at          TEXT    NOT NULL DEFAULT (datetime('now')),
  UNIQUE(product_id, size, color)
);

-- Optionale Spalten nachträglich hinzufügen
ALTER TABLE inventory ADD COLUMN color               TEXT    NOT NULL DEFAULT '';
ALTER TABLE inventory ADD COLUMN option_values       TEXT    NOT NULL DEFAULT '[]';
ALTER TABLE inventory ADD COLUMN title               TEXT    NOT NULL DEFAULT '';
ALTER TABLE inventory ADD COLUMN images              TEXT    NOT NULL DEFAULT '[]';
ALTER TABLE inventory ADD COLUMN variant_price_cents INTEGER;
ALTER TABLE inventory ADD COLUMN is_default          INTEGER NOT NULL DEFAULT 0;

-- ============================================================
-- Tabelle: orders
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  status       TEXT    NOT NULL DEFAULT 'pending',
  customer     TEXT    NOT NULL DEFAULT '{}',
  lines        TEXT    NOT NULL DEFAULT '[]',
  total_cents  INTEGER NOT NULL DEFAULT 0,
  notes        TEXT    NOT NULL DEFAULT '',
  created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at   TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- Tabelle: messages
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name       TEXT NOT NULL DEFAULT '',
  email      TEXT NOT NULL DEFAULT '',
  subject    TEXT NOT NULL DEFAULT '',
  body       TEXT NOT NULL DEFAULT '',
  is_read    INTEGER NOT NULL DEFAULT 0,
  created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- Tabelle: newsletter_subscribers
-- ============================================================
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  email      TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- Tabelle: settings
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
  key   TEXT PRIMARY KEY,
  value TEXT NOT NULL DEFAULT ''
);

-- Standard-Admin-Account (nur wenn noch keiner existiert)
INSERT OR IGNORE INTO settings (key, value) VALUES ('admin_username', 'admin');
-- Passwort: abj (bcrypt-Hash) – bitte nach dem ersten Login ändern!
INSERT OR IGNORE INTO settings (key, value) VALUES ('admin_password_hash', '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uvosg.0tm');

-- Aufräumen: Test-Produkte löschen (IDs 5–8 wurden beim Debugging angelegt)
-- Kommentar entfernen um auszuführen:
-- DELETE FROM inventory WHERE product_id IN (5,6,7,8);
-- DELETE FROM products    WHERE id        IN (5,6,7,8) AND name LIKE 'Test%';
