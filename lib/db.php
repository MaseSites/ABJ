<?php
define('DB_PATH', __DIR__ . '/../data/shop.db');

/**
 * Liefert das Seed-Admin-Passwort: zuerst aus der Umgebungsvariable/.env
 * ($envKey). Ist keine gesetzt, wird ein starkes Zufallspasswort erzeugt und
 * EINMALIG in data/INITIAL-ADMIN-PASSWORD.txt abgelegt (nicht öffentlich,
 * nicht in Git). So steht nie ein Passwort im Quellcode.
 */
function _abj_seed_admin_pw(string $envKey, string $label): string {
    $pw = function_exists('env_get') ? (string)env_get($envKey) : (string)getenv($envKey);
    if ($pw !== '') return $pw;
    $pw = bin2hex(random_bytes(9)); // 18 Hex-Zeichen
    @file_put_contents(__DIR__ . '/../data/INITIAL-ADMIN-PASSWORD.txt',
        '[' . date('Y-m-d H:i:s') . "] {$label} ({$envKey}): {$pw}\n", FILE_APPEND);
    return $pw;
}

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
            tags TEXT DEFAULT '',
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
            merged_into TEXT DEFAULT '',
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
        CREATE TABLE IF NOT EXISTS product_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            customer_name TEXT DEFAULT '',
            email TEXT DEFAULT '',
            phone TEXT DEFAULT '',
            description TEXT DEFAULT '',
            link TEXT DEFAULT '',
            screenshot TEXT DEFAULT '',
            status TEXT DEFAULT 'neu',
            price_cents INTEGER DEFAULT 0,
            admin_note TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS order_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_reference TEXT NOT NULL,
            author_role TEXT DEFAULT 'admin',
            author_name TEXT DEFAULT '',
            subject TEXT DEFAULT '',
            body TEXT DEFAULT '',
            is_system INTEGER DEFAULT 0,
            is_read INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now'))
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
        CREATE TABLE IF NOT EXISTS discount_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            type TEXT NOT NULL DEFAULT 'percent',  -- 'percent' | 'fixed' | 'free_shipping'
            value INTEGER NOT NULL DEFAULT 0,      -- Prozent (1-100) oder Betrag in Rappen
            min_order_cents INTEGER DEFAULT 0,
            max_uses INTEGER DEFAULT 0,            -- 0 = unbegrenzt
            used_count INTEGER DEFAULT 0,
            valid_until TEXT DEFAULT '',           -- ISO-Datum, leer = unbegrenzt
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            author TEXT DEFAULT '',
            rating INTEGER NOT NULL DEFAULT 5,
            text TEXT DEFAULT '',
            is_approved INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            name TEXT DEFAULT '',
            phone TEXT DEFAULT '',
            address TEXT DEFAULT '{}',
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS account_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            order_reference TEXT DEFAULT '',
            sender_role TEXT DEFAULT 'admin',
            subject TEXT DEFAULT '',
            body TEXT DEFAULT '',
            is_read INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT DEFAULT '',
            path TEXT DEFAULT '',
            user_agent TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS ip_blocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT UNIQUE NOT NULL,
            note TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS ip_allow (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT UNIQUE NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS access_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            account_id INTEGER,
            created_at TEXT DEFAULT (datetime('now')),
            used_at TEXT DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS promo_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            code TEXT UNIQUE NOT NULL,
            used_by INTEGER,
            used_at TEXT DEFAULT '',
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS login_throttle (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            scope TEXT NOT NULL DEFAULT '',
            ip TEXT NOT NULL DEFAULT '',
            created_at TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS promo_redemptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            reward TEXT DEFAULT '',
            code TEXT DEFAULT '',
            cost INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now'))
        );
    ");
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_ip ON visits(ip)"); } catch (\Throwable $e) {}
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_created ON visits(created_at)"); } catch (\Throwable $e) {}
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
        'tags'          => "TEXT DEFAULT ''",
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

    $acc_cols = array_column($pdo->query("PRAGMA table_info(accounts)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['phone' => "TEXT DEFAULT ''", 'address' => "TEXT DEFAULT '{}'", 'access_code' => "TEXT DEFAULT ''",
              'referred_by' => 'INTEGER', 'promo_points' => 'INTEGER DEFAULT 0'] as $col => $def) {
        if (!in_array($col, $acc_cols)) {
            try { $pdo->exec("ALTER TABLE accounts ADD COLUMN $col $def"); } catch (\Throwable $e) {}
        }
    }

    $am_cols = array_column($pdo->query("PRAGMA table_info(account_messages)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $am_add = [
        'account_id'      => 'INTEGER DEFAULT 0',
        'order_reference' => "TEXT DEFAULT ''",
        'sender_role'     => "TEXT DEFAULT 'admin'",
        'subject'         => "TEXT DEFAULT ''",
        'body'            => "TEXT DEFAULT ''",
        'is_read'         => 'INTEGER DEFAULT 0',
        'message_type'    => "TEXT DEFAULT 'plain'",
        'action_url'      => "TEXT DEFAULT ''",
        'decline_url'     => "TEXT DEFAULT ''",
        'action_label'    => "TEXT DEFAULT ''",
        'decline_label'   => "TEXT DEFAULT ''",
    ];
    foreach ($am_add as $col => $def) {
        if (!in_array($col, $am_cols)) {
            try { $pdo->exec("ALTER TABLE account_messages ADD COLUMN $col $def"); } catch (\Throwable $e) {}
        }
    }

    $pc_cols = array_column($pdo->query("PRAGMA table_info(promo_codes)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['used_by' => 'INTEGER', 'used_at' => "TEXT DEFAULT ''"] as $col => $def) {
        if (!in_array($col, $pc_cols)) {
            try { $pdo->exec("ALTER TABLE promo_codes ADD COLUMN $col $def"); } catch (\Throwable $e) {}
        }
    }

    $ord_cols = array_column($pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $ord_add = [
        'is_seen'                  => 'INTEGER DEFAULT 0',
        'merged_into'              => "TEXT DEFAULT ''",
        'discount_code'            => "TEXT DEFAULT ''",
        'discount_cents'           => 'INTEGER DEFAULT 0',
        'note'                     => "TEXT DEFAULT ''",
        'promo_awarded'            => 'INTEGER DEFAULT 0',
    ];
    foreach ($ord_add as $col => $def) {
        if (!in_array($col, $ord_cols)) {
            try { $pdo->exec("ALTER TABLE orders ADD COLUMN $col $def"); } catch (\Throwable $e) {}
        }
    }

    $req_cols = array_column($pdo->query("PRAGMA table_info(product_requests)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $req_add = [
        'account_id'    => 'INTEGER DEFAULT 0',
        'customer_name' => "TEXT DEFAULT ''",
        'email'         => "TEXT DEFAULT ''",
        'phone'         => "TEXT DEFAULT ''",
        'description'   => "TEXT DEFAULT ''",
        'link'          => "TEXT DEFAULT ''",
        'screenshot'    => "TEXT DEFAULT ''",
        'status'        => "TEXT DEFAULT 'neu'",
        'price_cents'   => 'INTEGER DEFAULT 0',
        'admin_note'    => "TEXT DEFAULT ''",
    ];
    foreach ($req_add as $col => $def) {
        if (!in_array($col, $req_cols)) {
            try { $pdo->exec("ALTER TABLE product_requests ADD COLUMN $col $def"); } catch (\Throwable $e) {}
        }
    }

    $msg_cols = array_column($pdo->query("PRAGMA table_info(order_messages)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $msg_add = [
        'author_role'    => "TEXT DEFAULT 'admin'",
        'author_name'    => "TEXT DEFAULT ''",
        'subject'        => "TEXT DEFAULT ''",
        'body'           => "TEXT DEFAULT ''",
        'is_system'      => 'INTEGER DEFAULT 0',
        'is_read'        => 'INTEGER DEFAULT 0',
        'order_reference'=> "TEXT DEFAULT ''",
    ];
    foreach ($msg_add as $col => $def) {
        if (!in_array($col, $msg_cols)) {
            try { $pdo->exec("ALTER TABLE order_messages ADD COLUMN $col $def"); } catch (\Throwable $e) {}
        }
    }

    // IP -> Nutzer-Zuordnung: account_id auf Besuchen und freigeschalteten IPs
    foreach (['visits', 'ip_allow'] as $tbl) {
        try {
            $cols = array_column($pdo->query("PRAGMA table_info($tbl)")->fetchAll(PDO::FETCH_ASSOC), 'name');
            if (!in_array('account_id', $cols, true)) {
                $pdo->exec("ALTER TABLE $tbl ADD COLUMN account_id INTEGER DEFAULT 0");
            }
        } catch (\Throwable $e) {}
    }

    // Migrate: force currency to CHF if still set to old EUR default
    try { $pdo->exec("UPDATE settings SET value = 'CHF' WHERE key = 'currency' AND value = 'EUR'"); } catch (\Throwable $e) {}

    // One-time backfill: jedes Produkt ohne Lagereintrag bekommt automatisch
    // einen Standard-Eintrag mit seinem hinterlegten Bestand.
    try {
        $done = $pdo->query("SELECT value FROM settings WHERE key='inv_backfill_v1'")->fetch();
        if (!$done) {
            $rows = $pdo->query("SELECT p.id, p.stock FROM products p
                WHERE NOT EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id)")->fetchAll();
            $ins = $pdo->prepare("INSERT INTO inventory (product_id, size, color, stock, reserved, min_stock, is_default)
                VALUES (?, '', '', ?, 0, 3, 1)");
            foreach ($rows as $p) {
                try { $ins->execute([(int)$p['id'], max(0, (int)$p['stock'])]); } catch (\Throwable $e) {}
            }
            $pdo->exec("INSERT OR REPLACE INTO settings (key, value) VALUES ('inv_backfill_v1', '1')");
        }
    } catch (\Throwable $e) {}

    // Rollen-Spalte für Admin-Konten (root = alle Rechte, lookup = beschränkt)
    $user_cols = array_column($pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('role', $user_cols, true)) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'root'"); } catch (\Throwable $e) {}
    }

    $cnt = (int)$pdo->query("SELECT COUNT(*) AS n FROM users")->fetch()['n'];
    if ($cnt === 0) {
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES ('admin_user_root', ?, 'root')")
            ->execute([password_hash(_abj_seed_admin_pw('ADMIN_ROOT_PASSWORD', 'Root-Admin'), PASSWORD_DEFAULT)]);
    }

    // Admin-Zugänge sicherstellen: alten admin/abj entfernen, Root + beschränktes
    // Lookup-Konto anlegen. Robuster Upsert ohne ON CONFLICT (maximal portabel).
    try {
        $accInit = $pdo->query("SELECT value FROM settings WHERE key='admin_accounts_v4'")->fetch();
        if (!$accInit) {
            $pdo->prepare("DELETE FROM users WHERE username='admin'")->execute();
            $setUser = function (string $username, string $pw, string $role) use ($pdo) {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $s = $pdo->prepare("SELECT id FROM users WHERE username=?");
                $s->execute([$username]);
                if ($s->fetch()) {
                    $pdo->prepare("UPDATE users SET password_hash=?, role=? WHERE username=?")->execute([$hash, $role, $username]);
                } else {
                    $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")->execute([$username, $hash, $role]);
                }
            };
            $setUser('admin_user_root', _abj_seed_admin_pw('ADMIN_ROOT_PASSWORD', 'Root-Admin'), 'root');
            $setUser('admin_user_lookup', _abj_seed_admin_pw('ADMIN_LOOKUP_PASSWORD', 'Lookup-Admin'), 'lookup');
            $pdo->exec("INSERT OR REPLACE INTO settings (key, value) VALUES ('admin_accounts_v4', '1')");
        }
    } catch (\Throwable $e) {}

    // Einmalig: alle bestehenden Admin-Sessions ungültig machen -> jeder muss
    // sich neu anmelden (Session-Epoche auf einen frischen Wert setzen).
    try {
        $loFlag = $pdo->query("SELECT value FROM settings WHERE key='admin_logout_all_v1'")->fetch();
        if (!$loFlag) {
            $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('admin_session_epoch', ?)")->execute([(string)time()]);
            $pdo->exec("INSERT OR REPLACE INTO settings (key, value) VALUES ('admin_logout_all_v1', '1')");
        }
    } catch (\Throwable $e) {}
}
