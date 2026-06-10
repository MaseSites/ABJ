import db from '../config/db.js';

const insertStmt = db.prepare(
  'INSERT INTO newsletter (email) VALUES (?) ON CONFLICT(email) DO NOTHING'
);
const listStmt   = db.prepare('SELECT * FROM newsletter ORDER BY created_at DESC LIMIT 1000');
const countStmt  = db.prepare('SELECT COUNT(*) AS n FROM newsletter');
const deleteStmt = db.prepare('DELETE FROM newsletter WHERE id = ?');

export function subscribe(email) {
  const info = insertStmt.run(String(email).toLowerCase().trim());
  return info.changes > 0; // true = neu hinzugefügt
}

export function list() {
  return listStmt.all();
}

export function count() {
  return countStmt.get().n;
}

export function remove(id) {
  return deleteStmt.run(id).changes > 0;
}
