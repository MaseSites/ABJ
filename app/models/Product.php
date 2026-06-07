<?php
class Product {
    private $db;
    
    public function __construct() {
        $this->db = Database::connect();
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function getBySlug($slug) {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO products (slug, name, description, category, price_cents, sale_price_cents, sizes, option_groups, images, stock, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['slug'],
            $data['name'],
            $data['description'] ?? '',
            $data['category'] ?? 'Allgemein',
            $data['price_cents'] ?? 0,
            $data['sale_price_cents'] ?? null,
            json_encode($data['sizes'] ?? []),
            json_encode($data['option_groups'] ?? []),
            json_encode($data['images'] ?? []),
            $data['stock'] ?? 0,
            $data['is_active'] ?? 1
        ]);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $fields = [];
        $values = [];
        foreach ($data as $k => $v) {
            if (in_array($k, ['sizes', 'option_groups', 'images'])) {
                $v = json_encode($v);
            }
            $fields[] = "$k = ?";
            $values[] = $v;
        }
        $values[] = $id;
        
        $stmt = $this->db->prepare("UPDATE products SET " . implode(", ", $fields) . ", updated_at = datetime('now') WHERE id = ?");
        $stmt->execute($values);
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    public function getByCategory($category) {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE category = ? AND is_active = 1");
        $stmt->execute([$category]);
        return $stmt->fetchAll();
    }
}
?>
