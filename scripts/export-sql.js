#!/usr/bin/env node
/**
 * ABJ Store – SQL-Export
 * Erzeugt scripts/schema.sql und scripts/data.sql aus der aktuellen Datenbank.
 * Ausführen: node scripts/export-sql.js
 */

import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import { writeFileSync } from 'fs';
import Database from 'better-sqlite3';

const __dirname = dirname(fileURLToPath(import.meta.url));
const dbPath    = process.env.DATA_DIR
  ? resolve(process.env.DATA_DIR, 'app.db')
  : resolve(__dirname, '../data/app.db');

console.log('Datenbank:', dbPath);
const db = new Database(dbPath, { readonly: true });

// ── Tabellen-Reihenfolge (FK-abhängig) ──────────────────────────────────────
const TABLE_ORDER = [
  'settings', 'users', 'products', 'inventory',
  'orders', 'messages', 'newsletter_subscribers', 'newsletter',
];

function escapeSql(val) {
  if (val === null || val === undefined) return 'NULL';
  if (typeof val === 'number') return String(val);
  return "'" + String(val).replace(/'/g, "''") + "'";
}

// ── Schema ───────────────────────────────────────────────────────────────────
const schemaRows = db.prepare(
  "SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
).all();

const schemaMap = {};
for (const r of schemaRows) schemaMap[r.name] = r.sql;

let schema = '-- ABJ Store – Datenbankschema\n';
schema += '-- Generiert: ' + new Date().toISOString() + '\n\n';
schema += 'PRAGMA journal_mode = WAL;\n';
schema += 'PRAGMA foreign_keys = ON;\n\n';

for (const name of TABLE_ORDER) {
  const sql = schemaMap[name];
  if (!sql) continue;
  schema += `-- ── ${name} ──\n`;
  schema += `DROP TABLE IF EXISTS ${name};\n`;
  schema += sql.replace(/^CREATE TABLE/, 'CREATE TABLE IF NOT EXISTS') + ';\n\n';
}
// Remaining tables not in TABLE_ORDER
for (const { name, sql } of schemaRows) {
  if (TABLE_ORDER.includes(name) || name === 'sessions') continue;
  schema += `-- ── ${name} ──\n`;
  schema += `DROP TABLE IF EXISTS ${name};\n`;
  schema += sql.replace(/^CREATE TABLE/, 'CREATE TABLE IF NOT EXISTS') + ';\n\n';
}

// ── Daten ────────────────────────────────────────────────────────────────────
let data = '-- ABJ Store – Datenbankinhalt\n';
data += '-- Generiert: ' + new Date().toISOString() + '\n\n';
data += 'PRAGMA foreign_keys = OFF;\n\n';

let totalRows = 0;

for (const name of [...TABLE_ORDER, ...schemaRows.map(r => r.name).filter(n => !TABLE_ORDER.includes(n) && n !== 'sessions')]) {
  if (name === 'sessions') continue; // Sessions nicht exportieren
  let rows;
  try { rows = db.prepare(`SELECT * FROM ${name}`).all(); }
  catch { continue; }
  if (!rows.length) {
    data += `-- ${name}: keine Daten\n\n`;
    continue;
  }

  data += `-- ── ${name} (${rows.length} Zeilen) ──\n`;
  data += `DELETE FROM ${name};\n`;

  // Spalten ermitteln
  const cols = Object.keys(rows[0]);

  for (const row of rows) {
    const vals = cols.map(c => escapeSql(row[c]));
    data += `INSERT INTO ${name} (${cols.join(', ')}) VALUES (${vals.join(', ')});\n`;
  }
  data += '\n';
  totalRows += rows.length;
}

data += 'PRAGMA foreign_keys = ON;\n';

// ── Dateien schreiben ────────────────────────────────────────────────────────
const schemaPath = resolve(__dirname, 'schema.sql');
const dataPath   = resolve(__dirname, 'data.sql');

writeFileSync(schemaPath, schema, 'utf8');
writeFileSync(dataPath,   data,   'utf8');

db.close();

console.log('✓ schema.sql geschrieben:', schemaPath);
console.log('✓ data.sql   geschrieben:', dataPath);
console.log(`  ${totalRows} Zeilen exportiert.`);
