<?php
class Message {
    private $db;
    
    public function __construct() {
        $this->db = Database::connect();
    }
    
    public function create($name, $email, $subject, $message) {
        $stmt = $this->db->prepare("
            INSERT INTO messages (name, email, subject, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $subject, $message]);
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM messages ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    public function count() {
        $result = $this->db->query("SELECT COUNT(*) as n FROM messages")->fetch();
        return $result['n'];
    }
}
?>
