import db from '../config/db.js';
import { customAlphabet } from 'nanoid';

const refId = customAlphabet('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 8);

const insertStmt = db.prepare(`
  INSERT INTO orders (reference, customer_name, email, phone, address, items, total_cents, shipping_cents, status, payment_status, payment_method)
  VALUES (@reference, @customer_name, @email, @phone, @address, @items, @total_cents, @shipping_cents, @status, @payment_status, @payment_method)
`);
const listStmt = db.prepare('SELECT * FROM orders ORDER BY created_at DESC LIMIT 500');
const byRefStmt = db.prepare('SELECT * FROM orders WHERE reference = ?');
const deleteStmt = db.prepare('DELETE FROM orders WHERE reference = ?');

export function create({ customer_name, email, phone, address, items, total_cents, shipping_cents, payment_method }) {
  const reference = 'ABJ-' + refId();
  const isPaid = payment_method === 'kreditkarte' || payment_method === 'paypal';
  insertStmt.run({
    reference,
    customer_name,
    email,
    phone:            phone ?? '',
    address:          typeof address === 'string' ? address : JSON.stringify(address ?? {}),
    items:            JSON.stringify(items ?? []),
    total_cents:      total_cents ?? 0,
    shipping_cents:   shipping_cents ?? 0,
    status:           'neu',
    payment_status:   isPaid ? 'bezahlt' : 'offen',
    payment_method:   payment_method ?? '',
  });
  return reference;
}

export function list() {
  return listStmt.all().map(parseOrder);
}

export function getByReference(reference) {
  const r = byRefStmt.get(reference);
  return r ? parseOrder(r) : null;
}

export function deleteOrder(reference) {
  return deleteStmt.run(reference).changes > 0;
}

const updateStatusStmt = db.prepare(
  'UPDATE orders SET status = ?, payment_status = ? WHERE reference = ?'
);

export function updateStatus(reference, status, paymentStatus) {
  return updateStatusStmt.run(status, paymentStatus, reference).changes > 0;
}

function parseOrder(r) {
  let address = r.address || '';
  try {
    const parsed = JSON.parse(r.address);
    if (parsed && typeof parsed === 'object') address = parsed;
  } catch { /* bleibt string */ }
  return { ...r, items: JSON.parse(r.items || '[]'), address };
}

// Aggregierte Umsatzkennzahlen + Tagesreihe (letzte N Tage) für das Dashboard.
export function stats(days = 7) {
  const totalRevenue = db
    .prepare("SELECT COALESCE(SUM(total_cents),0) AS c FROM orders WHERE payment_status = 'bezahlt'")
    .get().c;
  const openCount = db.prepare("SELECT COUNT(*) AS n FROM orders WHERE payment_status <> 'bezahlt'").get().n;

  const series = [];
  for (let i = days - 1; i >= 0; i--) {
    const day = db
      .prepare(
        `SELECT COUNT(*) AS orders, COALESCE(SUM(total_cents),0) AS revenue
         FROM orders WHERE date(created_at) = date('now', ?)`
      )
      .get(`-${i} days`);
    series.push({ dayOffset: i, orders: day.orders, revenue: day.revenue });
  }
  const maxOrders = Math.max(1, ...series.map((s) => s.orders));
  return { totalRevenue, openCount, series, maxOrders };
}
