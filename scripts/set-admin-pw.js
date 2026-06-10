import bcrypt from 'bcryptjs';
import Database from 'better-sqlite3';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const db = new Database(path.join(__dirname, '..', 'data', 'app.db'));
const hash = await bcrypt.hash('abj', 10);
const result = db.prepare('UPDATE users SET password_hash = ? WHERE username = ?').run(hash, 'admin');
if (result.changes === 0) {
  db.prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)').run('admin', hash);
  console.log('Admin-User erstellt mit Passwort "abj"');
} else {
  console.log('Passwort auf "abj" geaendert fuer user "admin"');
}
db.close();
