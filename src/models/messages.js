import db from '../config/db.js';

const insertStmt   = db.prepare('INSERT INTO messages (name, email, body) VALUES (?, ?, ?)');
const listStmt     = db.prepare('SELECT * FROM messages ORDER BY created_at DESC LIMIT 500');
const unreadStmt   = db.prepare('SELECT COUNT(*) AS n FROM messages WHERE is_read = 0');
const markReadStmt = db.prepare('UPDATE messages SET is_read = 1 WHERE id = ?');
const deleteStmt   = db.prepare('DELETE FROM messages WHERE id = ?');

export function create({ name, email, body }) {
  return insertStmt.run(name, email, body).lastInsertRowid;
}

export function list() {
  return listStmt.all();
}

export function unreadCount() {
  return unreadStmt.get().n;
}

export function markRead(id) {
  return markReadStmt.run(id).changes > 0;
}

export function remove(id) {
  return deleteStmt.run(id).changes > 0;
}
