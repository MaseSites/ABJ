<?php
class Order {
    private $db;
    
    public function __construct() {
        $this->db = Database::connect();
    }
    
    public function create($email, $items, $totalCents) {
        $orderNumber = 'ORD-' . date('Ymd') . '-' . uniqid();
        $stmt = $this->db->prepare("
            INSERT INTO orders (order_number, email, items, total_cents, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $orderNumber,
            $email,
            json_encode($items),
            $totalCents
        ]);
        return $this->db->lastInsertId();
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM orders ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE orders SET status = ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$status, $id]);
    }
}
?>
