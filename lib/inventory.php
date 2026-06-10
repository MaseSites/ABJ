<?php
function inv_by_product(int $productId): array {
    $stmt = db()->prepare('SELECT * FROM inventory WHERE product_id = ? ORDER BY size, color');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function inv_by_variant(int $productId, string $size = '', string $color = ''): ?array {
    $stmt = db()->prepare('SELECT * FROM inventory WHERE product_id = ? AND size = ? AND color = ?');
    $stmt->execute([$productId, $size, $color]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function inv_row_count(int $productId): int {
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM inventory WHERE product_id = ?');
    $stmt->execute([$productId]);
    return (int)$stmt->fetch()['n'];
}

function inv_product_stock_fallback(int $productId): int {
    $stmt = db()->prepare('SELECT stock FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return max(0, (int)($row['stock'] ?? 0));
}

function inv_has_variants(int $productId): bool {
    $rows = inv_by_product($productId);
    foreach ($rows as $r) {
        if ($r['size'] || $r['color']) return true;
    }
    return false;
}

function inv_stock_for_variant(int $productId, string $size = '', string $color = ''): int {
    $row = inv_by_variant($productId, $size, $color);
    if ($row) return max(0, $row['stock'] - $row['reserved']);
    if (inv_row_count($productId) === 0) return inv_product_stock_fallback($productId);
    return 0;
}

function inv_total_stock(int $productId): int {
    $rows = inv_by_product($productId);
    if (empty($rows)) return inv_product_stock_fallback($productId);
    return array_sum(array_map(function($r) { return max(0, $r['stock'] - $r['reserved']); }, $rows));
}

function inv_upsert(array $data): void {
    $stmt = db()->prepare("
        INSERT INTO inventory (product_id,sku,size,color,option_values,stock,reserved,min_stock,next_delivery,notes,title,images,variant_price_cents,is_default)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON CONFLICT(product_id,size,color) DO UPDATE SET
            sku=excluded.sku, stock=excluded.stock, min_stock=excluded.min_stock,
            next_delivery=excluded.next_delivery, notes=excluded.notes, title=excluded.title,
            option_values=excluded.option_values, images=excluded.images,
            variant_price_cents=excluded.variant_price_cents, is_default=excluded.is_default,
            updated_at=datetime('now')
    ");
    $stmt->execute([
        $data['product_id'], $data['sku'] ?? '', $data['size'] ?? '', $data['color'] ?? '',
        json_encode(is_array($data['option_values'] ?? null) ? $data['option_values'] : []),
        max(0, (int)($data['stock'] ?? 0)), max(0, (int)($data['reserved'] ?? 0)),
        max(0, (int)($data['min_stock'] ?? 3)), $data['next_delivery'] ?? '',
        $data['notes'] ?? '', $data['title'] ?? '',
        json_encode(is_array($data['images'] ?? null) ? $data['images'] : []),
        isset($data['variant_price_cents']) && $data['variant_price_cents'] !== '' ? (int)$data['variant_price_cents'] : null,
        $data['is_default'] ? 1 : 0,
    ]);
}

function inv_upsert_many(array $rows): void {
    if (empty($rows)) return;
    $pdo = db();
    $productId = $rows[0]['product_id'];
    $incomingDefault = -1;
    foreach ($rows as $i => $r) {
        if (!empty($r['is_default'])) { $incomingDefault = $i; break; }
    }
    foreach ($rows as $r) inv_upsert($r);
    if ($incomingDefault !== -1) {
        $pdo->prepare('UPDATE inventory SET is_default=0 WHERE product_id=?')->execute([$productId]);
        $key = $rows[$incomingDefault]['size'] ?? '';
        $pdo->prepare('UPDATE inventory SET is_default=1 WHERE product_id=? AND size=? AND color=?')->execute([$productId, $key, '']);
    } else {
        $cnt = (int)$pdo->prepare('SELECT COUNT(*) AS n FROM inventory WHERE product_id=? AND is_default=1')->execute([$productId]) ? 1 : 0;
        $stmt = $pdo->prepare('SELECT COUNT(*) AS n FROM inventory WHERE product_id=? AND is_default=1');
        $stmt->execute([$productId]); $cnt = (int)$stmt->fetch()['n'];
        if ($cnt === 0) {
            $key = $rows[0]['size'] ?? '';
            $pdo->prepare('UPDATE inventory SET is_default=1 WHERE product_id=? AND size=? AND color=?')->execute([$productId, $key, '']);
        }
    }
}

function inv_delete_by_product(int $productId): void {
    $stmt = db()->prepare('DELETE FROM inventory WHERE product_id = ?');
    $stmt->execute([$productId]);
}

function inv_all(): array {
    $stmt = db()->query("SELECT i.*, p.name AS product_name, p.category, p.slug, p.price_cents, p.sale_price_cents, p.is_active
        FROM inventory i JOIN products p ON p.id = i.product_id ORDER BY p.name, i.size, i.color");
    return array_map(function($r) {
        $r['available'] = max(0, $r['stock'] - $r['reserved']);
        $r['is_out'] = $r['stock'] - $r['reserved'] <= 0;
        $r['is_low'] = $r['stock'] > 0 && $r['stock'] - $r['reserved'] <= $r['min_stock'];
        return $r;
    }, $stmt->fetchAll());
}

function inv_low_stock(): array {
    $stmt = db()->query("SELECT i.*, p.name AS product_name, p.category
        FROM inventory i JOIN products p ON p.id = i.product_id
        WHERE p.is_active=1 AND i.stock <= i.min_stock ORDER BY i.stock ASC, p.name");
    return $stmt->fetchAll();
}

function inv_deduct_stock(array $lines): void {
    $pdo = db();
    foreach ($lines as $line) {
        $size = $line['size'] ?? '';
        $row = inv_by_variant($line['productId'], $size, '');
        if ($row) {
            $newStock = max(0, $row['stock'] - $line['qty']);
            $pdo->prepare("UPDATE inventory SET stock=?, updated_at=datetime('now') WHERE product_id=? AND size=? AND color=?")->execute([$newStock, $line['productId'], $size, '']);
        } else if (inv_row_count($line['productId']) === 0) {
            $pdo->prepare("UPDATE products SET stock=MAX(0,stock-?), updated_at=datetime('now') WHERE id=?")->execute([$line['qty'], $line['productId']]);
        }
    }
}
