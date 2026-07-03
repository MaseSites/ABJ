#!/usr/bin/env node
/**
 * ABJ Store - Datenbank-Migration
 * Ausführen: node scripts/migrate.js
 */

import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import Database from 'better-sqlite3';

const __dirname = dirname(fileURLToPath(import.meta.url));
const dbPath = process.env.DATA_DIR
  ? resolve(process.env.DATA_DIR, 'app.db')
  : resolve(__dirname, '../data/app.db');

console.log('Datenbank:', dbPath);
const db = new Database(dbPath);
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

function run(sql, label) {
  try {
    db.exec(sql);
    if (label) console.log('  ✓', label);
  } catch (e) {
    if (label) console.log('  - ', label, '(übersprungen:', e.message, ')');
  }
}

// ── products ──────────────────────────────────────────────────────────────────
run(`
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
  )
`, 'products-Tabelle');

run(`ALTER TABLE products ADD COLUMN sale_price_cents INTEGER`,            'products.sale_price_cents');
run(`ALTER TABLE products ADD COLUMN sizes         TEXT NOT NULL DEFAULT '[]'`, 'products.sizes');
run(`ALTER TABLE products ADD COLUMN option_groups TEXT NOT NULL DEFAULT '[]'`, 'products.option_groups');

// ── inventory ─────────────────────────────────────────────────────────────────
run(`
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
  )
`, 'inventory-Tabelle');

run(`ALTER TABLE inventory ADD COLUMN color               TEXT    NOT NULL DEFAULT ''`,   'inventory.color');
run(`ALTER TABLE inventory ADD COLUMN option_values       TEXT    NOT NULL DEFAULT '[]'`, 'inventory.option_values');
run(`ALTER TABLE inventory ADD COLUMN title               TEXT    NOT NULL DEFAULT ''`,   'inventory.title');
run(`ALTER TABLE inventory ADD COLUMN images              TEXT    NOT NULL DEFAULT '[]'`, 'inventory.images');
run(`ALTER TABLE inventory ADD COLUMN variant_price_cents INTEGER`,                       'inventory.variant_price_cents');
run(`ALTER TABLE inventory ADD COLUMN is_default          INTEGER NOT NULL DEFAULT 0`,    'inventory.is_default');

// ── orders ────────────────────────────────────────────────────────────────────
run(`
  CREATE TABLE IF NOT EXISTS orders (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    status      TEXT    NOT NULL DEFAULT 'pending',
    customer    TEXT    NOT NULL DEFAULT '{}',
    lines       TEXT    NOT NULL DEFAULT '[]',
    total_cents INTEGER NOT NULL DEFAULT 0,
    notes       TEXT    NOT NULL DEFAULT '',
    created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT    NOT NULL DEFAULT (datetime('now'))
  )
`, 'orders-Tabelle');

// ── messages ──────────────────────────────────────────────────────────────────
run(`
  CREATE TABLE IF NOT EXISTS messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL DEFAULT '',
    email      TEXT NOT NULL DEFAULT '',
    subject    TEXT NOT NULL DEFAULT '',
    body       TEXT NOT NULL DEFAULT '',
    is_read    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
  )
`, 'messages-Tabelle');

// ── newsletter_subscribers ────────────────────────────────────────────────────
run(`
  CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  )
`, 'newsletter_subscribers-Tabelle');

// ── settings ──────────────────────────────────────────────────────────────────
run(`
  CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
  )
`, 'settings-Tabelle');

run(`INSERT OR IGNORE INTO settings (key,value) VALUES ('admin_username','admin')`,                                                                     'admin_username');
run(`INSERT OR IGNORE INTO settings (key,value) VALUES ('admin_password_hash','$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uvosg.0tm')`,       'admin_password_hash');

db.close();
console.log('\nMigration abgeschlossen.');
