-- ABJ Store – Datenbankinhalt
-- Generiert: 2026-06-10T16:52:59.835Z

PRAGMA foreign_keys = OFF;

-- ── settings (10 Zeilen) ──
DELETE FROM settings;
INSERT INTO settings (key, value) VALUES ('shop_name', 'ABJ Store');
INSERT INTO settings (key, value) VALUES ('tagline', 'Kuratierte Vintage- & Premiummode');
INSERT INTO settings (key, value) VALUES ('hero_title', 'Frühlings-Sale – bis zu -70%');
INSERT INTO settings (key, value) VALUES ('hero_subtitle', 'Limitierte Boxen & Einzelstücke. Nur solange der Vorrat reicht.');
INSERT INTO settings (key, value) VALUES ('sale_ends_at', '2026-06-22T19:32');
INSERT INTO settings (key, value) VALUES ('members_count', '20000');
INSERT INTO settings (key, value) VALUES ('ratings_count', '1000');
INSERT INTO settings (key, value) VALUES ('contact_email', 'kontakt@abj-store.example');
INSERT INTO settings (key, value) VALUES ('admin_username', 'admin');
INSERT INTO settings (key, value) VALUES ('admin_password_hash', '$2b$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uvosg.0tm');

-- ── users (1 Zeilen) ──
DELETE FROM users;
INSERT INTO users (id, username, password_hash, created_at) VALUES (1, 'admin', '$2a$10$2aDyBttNC/06KV3tdo/IIupRs6.wwoZeEHCsgeX9aTWlFkyepeuxy', '2026-06-09 18:54:47');

-- products: keine Daten

-- inventory: keine Daten

-- orders: keine Daten

-- messages: keine Daten

-- newsletter_subscribers: keine Daten

-- newsletter: keine Daten

PRAGMA foreign_keys = ON;
