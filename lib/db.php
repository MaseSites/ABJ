<?php
define('DB_PATH', __DIR__ . '/../data/shop.db');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        db_init($pdo);
    }
    return $pdo;
}

function db_init(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL DEFAULT '',
            description TEXT DEFAULT '',
            category TEXT DEFAULT 'Allgemein',
            price_cents INTEGER DEFAULT 0,
            sale_price_cents INTEGER,
            sizes TEXT DEFAULT '[]',
            option_groups TEXT DEFAULT '[]',
            images TEXT DEFAULT '[]',
            stock INTEGER DEFAULT 0,
            is_bestseller INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            sku TEXT DEFAULT '',
            size TEXT DEFAULT '',
            color TEXT DEFAULT '',
            option_values TEXT DEFAULT '[]',
            stock INTEGER DEFAULT 0,
            reserved INTEGER DEFAULT 0,
            min_stock INTEGER DEFAULT 3,
            next_delivery TEXT DEFAULT '',
            notes TEXT DEFAULT '',
            title TEXT DEFAULT '',
            images TEXT DEFAULT '[]',
            variant_price_cents INTEGER,
            is_default INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            UNIQUE(product_id, size, color)
        );
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reference TEXT UNIQUE NOT NULL,
            customer_name TEXT DEFAULT '',
            email TEXT DEFAULT '',
            phone TEXT DEFAULT '',
            address TEXT DEFAULT '{}',
            items TEXT DEFAULT '[]',
            total_cents INTEGER DEFAULT 0,
            shipping_cents INTEGER DEFAULT 0,
            status TEXT DEFAULT 'neu',
            payment_status TEXT DEFAULT 'offen',
            payment_method TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS newsletter (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT DEFAULT '',
            email TEXT DEFAULT '',
            subject TEXT DEFAULT '',
            message TEXT DEFAULT '',
            is_read INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS carts (
            token TEXT NOT NULL,
            product_id INTEGER NOT NULL,
            size TEXT NOT NULL DEFAULT '',
            qty INTEGER NOT NULL DEFAULT 1,
            updated_at TEXT DEFAULT (datetime('now')),
            PRIMARY KEY (token, product_id, size)
        );
    ");
    // Migrate: add columns that may be missing in older DB versions
    $inv_cols = array_column($pdo->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $inv_add = [
        'option_values'       => "TEXT DEFAULT '[]'",
        'title'               => "TEXT DEFAULT ''",
        'images'              => "TEXT DEFAULT '[]'",
        'variant_price_cents' => 'INTEGER',
        'is_default'          => 'INTEGER DEFAULT 0',
        'back_order'          => 'INTEGER DEFAULT 0',
    ];
    foreach ($inv_add as $col => $def) {
        if (!in_array($col, $inv_cols)) {
            try { $pdo->exec("ALTER TABLE inventory ADD COLUMN $col $def"); } catch (\Throwable $e) {}
        }
    }
    $prod_cols = array_column($pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $prod_add = [
        'option_groups' => "TEXT DEFAULT '[]'",
        'sizes'         => "TEXT DEFAULT '[]'",
        'back_order'    => 'INTEGER DEFAULT 0',
    ];
    foreach ($prod_add as $col => $def) {
        if (!in_array($col, $prod_cols)) {
            try { $pdo->exec("ALTER TABLE products ADD COLUMN $col $def"); } catch (\Throwable $e) {}
        }
    }
    // Remove duplicate inventory rows (keep highest id per product+size+color) then create unique index
    try {
        $pdo->exec("DELETE FROM inventory WHERE id NOT IN (SELECT MAX(id) FROM inventory GROUP BY product_id, size, color)");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_inv_pid_size_color ON inventory(product_id, size, color)");
    } catch (\Throwable $e) {}

    $ord_cols = array_column($pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('stripe_payment_intent_id', $ord_cols)) {
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN stripe_payment_intent_id TEXT DEFAULT ''"); } catch (\Throwable $e) {}
    }
    if (!in_array('is_seen', $ord_cols)) {
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN is_seen INTEGER DEFAULT 0"); } catch (\Throwable $e) {}
    }

    // Migrate: force currency to CHF if still set to old EUR default
    try { $pdo->exec("UPDATE settings SET value = 'CHF' WHERE key = 'currency' AND value = 'EUR'"); } catch (\Throwable $e) {}

    $cnt = (int)$pdo->query("SELECT COUNT(*) AS n FROM users")->fetch()['n'];
    if ($cnt === 0) {
        $hash = password_hash('abj', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)")->execute(['admin', $hash]);
    }
    // One-time password reset: forces admin password to 'abj' once after this deploy
    try {
        $pwInit = $pdo->query("SELECT value FROM settings WHERE key='admin_pw_v2'")->fetch();
        if (!$pwInit) {
            $hash = password_hash('abj', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT OR IGNORE INTO users (username, password_hash) VALUES ('admin', ?)")->execute([$hash]);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE username='admin'")->execute([$hash]);
            $pdo->exec("INSERT OR REPLACE INTO settings (key, value) VALUES ('admin_pw_v2', '1')");
        }
    } catch (\Throwable $e) {}
}
