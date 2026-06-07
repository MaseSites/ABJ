<?php
class Database {
    private static $db = null;
    
    public static function connect() {
        if (self::$db === null) {
            $dbPath = DATA_PATH . '/app.db';
            
            // Verzeichnis erstellen falls nicht vorhanden
            if (!file_exists(DATA_PATH)) {
                mkdir(DATA_PATH, 0755, true);
            }
            
            try {
                self::$db = new PDO('sqlite:' . $dbPath);
                self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::initSchema();
            } catch (PDOException $e) {
                die('Datenbankfehler: ' . $e->getMessage());
            }
        }
        return self::$db;
    }
    
    private static function initSchema() {
        $db = self::$db;
        
        // Users Tabelle
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        
        // Settings Tabelle
        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )");
        
        // Products Tabelle
        $db->exec("CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            category TEXT NOT NULL DEFAULT 'Allgemein',
            price_cents INTEGER NOT NULL DEFAULT 0,
            sale_price_cents INTEGER,
            sizes TEXT NOT NULL DEFAULT '[]',
            option_groups TEXT NOT NULL DEFAULT '[]',
            images TEXT NOT NULL DEFAULT '[]',
            stock INTEGER NOT NULL DEFAULT 0,
            is_bestseller INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        
        // Orders Tabelle
        $db->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL,
            items TEXT NOT NULL,
            total_cents INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        
        // Newsletter Tabelle
        $db->exec("CREATE TABLE IF NOT EXISTS newsletter (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            subscribed_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
        
        // Messages Tabelle
        $db->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
    }
}
?>
