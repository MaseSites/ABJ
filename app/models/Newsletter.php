<?php
class Newsletter {
    private $db;
    
    public function __construct() {
        $this->db = Database::connect();
    }
    
    public function subscribe($email) {
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO newsletter (email) VALUES (?)");
        $stmt->execute([$email]);
    }
    
    public function unsubscribe($email) {
        $stmt = $this->db->prepare("DELETE FROM newsletter WHERE email = ?");
        $stmt->execute([$email]);
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM newsletter ORDER BY subscribed_at DESC");
        return $stmt->fetchAll();
    }
    
    public function count() {
        $result = $this->db->query("SELECT COUNT(*) as n FROM newsletter")->fetch();
        return $result['n'];
    }
}
?>
