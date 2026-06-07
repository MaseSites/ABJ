<?php
class Setting {
    private $db;
    private $cache = [];
    
    public function __construct() {
        $this->db = Database::connect();
        $this->loadDefaults();
    }
    
    private function loadDefaults() {
        $defaults = [
            'shop_name' => 'ABJ Shop',
            'currency' => 'CHF',
            'announcement' => '',
            'sale_ends_at' => '2026-06-30T23:59:59',
            'gate_password_hash' => ''
        ];
        $this->cache = $defaults;
    }
    
    public function get($key) {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        if ($result) {
            $this->cache[$key] = $result['value'];
            return $result['value'];
        }
        
        return $this->cache[$key] ?? null;
    }
    
    public function set($key, $value) {
        $this->cache[$key] = $value;
        
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
    
    public function all() {
        $stmt = $this->db->query("SELECT key, value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
            $this->cache[$row['key']] = $row['value'];
        }
        return $settings;
    }
}
?>
