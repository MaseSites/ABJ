<?php
function product_row(array $row): array {
    $row['sizes']         = safe_parse($row['sizes'] ?? '[]', []);
    $row['option_groups'] = safe_parse($row['option_groups'] ?? '[]', []);
    $row['images']        = safe_parse($row['images'] ?? '[]', []);
    $row['is_bestseller'] = (bool)$row['is_bestseller'];
    $row['is_active']     = (bool)$row['is_active'];
    return $row;
}

function products_list_public(?string $category = null): array {
    $pdo = db();
    if ($category) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE is_active = 1 AND category = ? ORDER BY created_at DESC');
        $stmt->execute([$category]);
    } else {
        $stmt = $pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC');
    }
    return array_map('product_row', $stmt->fetchAll());
}

function products_list_all(): array {
    $stmt = db()->query('SELECT * FROM products ORDER BY created_at DESC');
    return array_map('product_row', $stmt->fetchAll());
}

function product_by_id(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? product_row($row) : null;
}

function product_by_slug(string $slug): ?array {
    $stmt = db()->prepare('SELECT * FROM products WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ? product_row($row) : null;
}

function products_bestsellers(int $limit = 4): array {
    $stmt = db()->prepare('SELECT * FROM products WHERE is_active = 1 AND is_bestseller = 1 ORDER BY created_at DESC LIMIT ?');
    $stmt->execute([$limit]);
    return array_map('product_row', $stmt->fetchAll());
}

function products_categories(): array {
    $stmt = db()->query("SELECT DISTINCT category FROM products WHERE is_active = 1 AND category <> '' ORDER BY category");
    return array_column($stmt->fetchAll(), 'category');
}

function products_search(string $q, ?string $category = null): array {
    $term = '%' . strtolower(trim($q)) . '%';
    $sql = "SELECT * FROM products WHERE is_active = 1
        AND (lower(name) LIKE ? OR lower(category) LIKE ? OR lower(description) LIKE ? OR lower(tags) LIKE ?)";
    $params = [$term, $term, $term, $term];
    if ($category) { $sql .= ' AND category = ?'; $params[] = $category; }
    $sql .= ' ORDER BY is_bestseller DESC, created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return array_map('product_row', $stmt->fetchAll());
}

function products_related(string $category, int $excludeId, int $limit = 4): array {
    $stmt = db()->prepare('SELECT * FROM products WHERE is_active = 1 AND category = ? AND id != ? ORDER BY is_bestseller DESC, created_at DESC LIMIT ?');
    $stmt->execute([$category, $excludeId, $limit]);
    $rows = array_map('product_row', $stmt->fetchAll());
    if (count($rows) < $limit) {
        $stmt2 = db()->prepare('SELECT * FROM products WHERE is_active = 1 AND id != ? AND category != ? ORDER BY created_at DESC LIMIT ?');
        $stmt2->execute([$excludeId, $category, $limit - count($rows)]);
        $rows = array_merge($rows, array_map('product_row', $stmt2->fetchAll()));
    }
    return $rows;
}

function products_shuffle(array $items): array {
    shuffle($items);
    return $items;
}

function product_create(array $p): array {
    $slug = unique_slug($p['name']);
    $stmt = db()->prepare("INSERT INTO products (slug,name,description,category,price_cents,sale_price_cents,sizes,option_groups,images,tags,stock,is_bestseller,is_active)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $slug, $p['name'], $p['description'] ?? '', $p['category'] ?? 'Allgemein',
        $p['price_cents'] ?? 0, $p['sale_price_cents'] ?: null,
        json_encode($p['sizes'] ?? []), json_encode($p['option_groups'] ?? []),
        json_encode($p['images'] ?? []), $p['tags'] ?? '', $p['stock'] ?? 0,
        $p['is_bestseller'] ? 1 : 0, $p['is_active'] ? 1 : 0,
    ]);
    return product_by_id((int)db()->lastInsertId());
}

function product_update(int $id, array $p): array {
    $slug = unique_slug($p['name'], $id);
    $stmt = db()->prepare("UPDATE products SET slug=?,name=?,description=?,category=?,price_cents=?,sale_price_cents=?,sizes=?,option_groups=?,images=?,tags=?,stock=?,is_bestseller=?,is_active=?,updated_at=datetime('now') WHERE id=?");
    $stmt->execute([
        $slug, $p['name'], $p['description'] ?? '', $p['category'] ?? 'Allgemein',
        $p['price_cents'] ?? 0, $p['sale_price_cents'] ?: null,
        json_encode($p['sizes'] ?? []), json_encode($p['option_groups'] ?? []),
        json_encode($p['images'] ?? []), $p['tags'] ?? '', $p['stock'] ?? 0,
        $p['is_bestseller'] ? 1 : 0, $p['is_active'] ? 1 : 0,
        $id,
    ]);
    return product_by_id($id);
}

function product_delete(int $id): bool {
    $stmt = db()->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function products_list_lite(): array {
    $stmt = db()->query('SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC');
    return array_map(function($row) {
        $p = product_row($row);
        return [
            'id' => $p['id'], 'slug' => $p['slug'], 'name' => $p['name'],
            'category' => $p['category'], 'price_cents' => $p['price_cents'],
            'sale_price_cents' => $p['sale_price_cents'],
            'image' => $p['images'][0]['src'] ?? null, 'stock' => $p['stock'],
        ];
    }, $stmt->fetchAll());
}
