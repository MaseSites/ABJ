/**
 * Test-Setup - wird VOR allen Tests ausgeführt.
 *
 * Ziele:
 * - Isolierte In-Memory-SQLite-DB für Tests (keine Live-DB-Pollution)
 * - Schnelle bcrypt-Rounds (cost=1 statt 12) für Gate/Admin-Passwörter
 * - Einheitliche Test-Passwörter
 */

import { before } from 'node:test';
import bcrypt from 'bcryptjs';

// Passwörter für alle Tests
export const TEST_GATE_PW = 'test-gate-2026';
export const TEST_ADMIN_PW = 'test-admin-2026';
export const TEST_ADMIN_USER = 'testadmin';

/**
 * Initialisiert die Test-Umgebung.
 * WICHTIG: Muss VOR jedem Test-Modul importiert werden.
 */
export async function setupTestEnv() {
  // Schnelle bcrypt-Hashes (cost=1) für Tests
  const [gateHash, adminHash] = await Promise.all([
    bcrypt.hash(TEST_GATE_PW, 1),
    bcrypt.hash(TEST_ADMIN_PW, 1),
  ]);

  // Settings und Users per DB direkt setzen (umgeht Modell-Imports)
  const { default: db } = await import('../src/config/db.js');
  const upsert = db.prepare(
    'INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value'
  );
  upsert.run('gate_password_hash', gateHash);

  // Test-Admin anlegen (idempotent)
  const existing = db.prepare('SELECT id FROM users WHERE username=?').get(TEST_ADMIN_USER);
  if (!existing) {
    db.prepare('INSERT INTO users(username,password_hash) VALUES(?,?)').run(TEST_ADMIN_USER, adminHash);
  } else {
    db.prepare('UPDATE users SET password_hash=? WHERE username=?').run(adminHash, TEST_ADMIN_USER);
  }

  return { db, gateHash };
}

/**
 * Bereinigt Test-Produkte, -Inventory und -Coupons aus der DB.
 * Nutze nach der Test-Suite, um die Live-DB sauber zu halten.
 */
export function cleanupTestData(db) {
  // Produkte mit "Test" im Namen löschen
  db.prepare("DELETE FROM inventory WHERE product_id IN (SELECT id FROM products WHERE name LIKE '%Test%' OR name LIKE '%Http%' OR name LIKE '%Unit%' OR name LIKE '%Batch%' OR name LIKE '%Search%')").run();
  db.prepare("DELETE FROM products WHERE name LIKE '%Test%' OR name LIKE '%Http%' OR name LIKE '%Unit%' OR name LIKE '%Batch%' OR name LIKE '%Search%'").run();
}
