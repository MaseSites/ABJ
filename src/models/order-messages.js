import db from '../config/db.js';

const insertStmt = db.prepare(`
  INSERT INTO order_messages (order_reference, author_role, author_name, subject, body, is_system, is_read)
  VALUES (?, ?, ?, ?, ?, ?, ?)
`);
const listByOrderStmt = db.prepare('SELECT * FROM order_messages WHERE order_reference = ? ORDER BY created_at ASC, id ASC');
const unreadByOrderStmt = db.prepare(`
  SELECT COUNT(*) AS n
  FROM order_messages
  WHERE order_reference = ? AND author_role = 'admin' AND is_read = 0
`);
const unreadTotalStmt = db.prepare(`
  SELECT COUNT(*) AS n
  FROM order_messages
  WHERE author_role = 'admin' AND is_read = 0
`);
const markReadStmt = db.prepare(`
  UPDATE order_messages
  SET is_read = 1
  WHERE order_reference = ? AND author_role = 'admin'
`);

export function create({ order_reference, author_role = 'admin', author_name = '', subject = '', body, is_system = 0, is_read = 0 }) {
  return insertStmt.run(
    order_reference,
    author_role,
    author_name,
    subject,
    body,
    is_system ? 1 : 0,
    is_read ? 1 : 0,
  ).lastInsertRowid;
}

export function listByOrder(order_reference) {
  return listByOrderStmt.all(order_reference);
}

export function unreadCount(order_reference) {
  return unreadByOrderStmt.get(order_reference).n;
}

export function unreadTotal() {
  return unreadTotalStmt.get().n;
}

export function markOrderRead(order_reference) {
  return markReadStmt.run(order_reference).changes > 0;
}
