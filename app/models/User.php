<?php
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::connect();
    }
    
    public function findByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT id, username, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($username, $password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        return $this->db->lastInsertId();
    }
    
    public function verify($username, $password) {
        $user = $this->findByUsername($username);
        if (!$user) {
            return null;
        }
        if (password_verify($password, $user['password_hash'])) {
            return ['id' => $user['id'], 'username' => $user['username']];
        }
        return null;
    }
    
    public function changePassword($id, $newPassword) {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $id]);
    }
    
    public function count() {
        $result = $this->db->query("SELECT COUNT(*) as n FROM users")->fetch();
        return $result['n'];
    }
}
?>
