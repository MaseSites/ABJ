-- ABJ Store – Datenbankschema
-- Generiert: 2026-06-10T16:52:59.834Z

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ── settings ──
DROP TABLE IF EXISTS settings;
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
  );

-- ── users ──
DROP TABLE IF EXISTS users;
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
  );

-- ── products ──
DROP TABLE IF EXISTS products;
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

-- ── inventory ──
DROP TABLE IF EXISTS inventory;
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
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')), title TEXT NOT NULL DEFAULT '', images TEXT NOT NULL DEFAULT '[]', variant_price_cents INTEGER, is_default INTEGER NOT NULL DEFAULT 0,
    UNIQUE(product_id, size, color)
  );

-- ── orders ──
DROP TABLE IF EXISTS orders;
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

-- ── messages ──
DROP TABLE IF EXISTS messages;
CREATE TABLE IF NOT EXISTS messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    email      TEXT NOT NULL,
    body       TEXT NOT NULL,
    is_read    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  );

-- ── newsletter_subscribers ──
DROP TABLE IF EXISTS newsletter_subscribers;
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  );

-- ── newsletter ──
DROP TABLE IF EXISTS newsletter;
CREATE TABLE IF NOT EXISTS newsletter (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  );

