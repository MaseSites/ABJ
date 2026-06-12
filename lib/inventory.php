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

function inv_is_back_order(int $productId, string $size = '', string $color = ''): bool {
    try {
        $row = inv_by_variant($productId, $size, $color);
        if ($row) return (bool)($row['back_order'] ?? 0);
        $stmt = db()->prepare('SELECT back_order FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $p = $stmt->fetch();
        return $p ? (bool)($p['back_order'] ?? 0) : false;
    } catch (Throwable $e) {
        return false;
    }
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
    $pdo  = db();
    $pid  = $data['product_id'];
    $size = $data['size'] ?? '';
    $col  = $data['color'] ?? '';

    $ov    = json_encode(is_array($data['option_values'] ?? null) ? $data['option_values'] : []);
    $imgs  = json_encode(is_array($data['images'] ?? null) ? $data['images'] : []);
    $vpc   = ($data['variant_price_cents'] !== null && $data['variant_price_cents'] !== '')
             ? (int)$data['variant_price_cents'] : null;

    $check = $pdo->prepare('SELECT id FROM inventory WHERE product_id=? AND size=? AND color=?');
    $check->execute([$pid, $size, $col]);
    if ($check->fetch()) {
        $pdo->prepare("UPDATE inventory SET
            sku=?, stock=?, min_stock=?, next_delivery=?, notes=?, title=?,
            option_values=?, images=?, variant_price_cents=?, is_default=?, back_order=?,
            updated_at=datetime('now')
            WHERE product_id=? AND size=? AND color=?")
        ->execute([
            $data['sku'] ?? '', max(0, (int)($data['stock'] ?? 0)),
            max(0, (int)($data['min_stock'] ?? 3)), $data['next_delivery'] ?? '',
            $data['notes'] ?? '', $data['title'] ?? '',
            $ov, $imgs, $vpc, $data['is_default'] ? 1 : 0, $data['back_order'] ? 1 : 0,
            $pid, $size, $col,
        ]);
    } else {
        $pdo->prepare("INSERT INTO inventory
            (product_id,sku,size,color,option_values,stock,reserved,min_stock,next_delivery,notes,title,images,variant_price_cents,is_default,back_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $pid, $data['sku'] ?? '', $size, $col, $ov,
            max(0, (int)($data['stock'] ?? 0)), max(0, (int)($data['reserved'] ?? 0)),
            max(0, (int)($data['min_stock'] ?? 3)), $data['next_delivery'] ?? '',
            $data['notes'] ?? '', $data['title'] ?? '', $imgs, $vpc,
            $data['is_default'] ? 1 : 0, $data['back_order'] ? 1 : 0,
        ]);
    }
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

/**
 * Stellt sicher, dass JEDES Produkt mindestens einen Lagereintrag hat.
 * Produkte ohne Eintrag bekommen automatisch eine Standard-Zeile mit dem
 * am Produkt hinterlegten Bestand. Gibt die Anzahl neu erstellter Einträge zurück.
 */
function inv_ensure_entries(): int {
    $pdo  = db();
    $rows = $pdo->query("SELECT p.id, p.stock FROM products p
        WHERE NOT EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id)")->fetchAll();
    $created = 0;
    $stmt = $pdo->prepare("INSERT INTO inventory (product_id, size, color, stock, reserved, min_stock, is_default)
        VALUES (?, '', '', ?, 0, 3, 1)");
    foreach ($rows as $p) {
        try { $stmt->execute([(int)$p['id'], max(0, (int)$p['stock'])]); $created++; } catch (\Throwable $e) {}
    }
    return $created;
}

/** Gesamtwert des verfügbaren Lagerbestands (in Rappen/Cents). */
function inv_total_value(): int {
    $pdo = db();
    $invVal = (int)$pdo->query("SELECT COALESCE(SUM(
                MAX(i.stock - i.reserved, 0) *
                COALESCE(i.variant_price_cents, p.sale_price_cents, p.price_cents)
            ), 0) AS v
            FROM inventory i JOIN products p ON p.id = i.product_id
            WHERE p.is_active = 1")->fetch()['v'];
    $fallback = (int)$pdo->query("SELECT COALESCE(SUM(
                MAX(p.stock, 0) * COALESCE(p.sale_price_cents, p.price_cents)
            ), 0) AS v
            FROM products p
            WHERE p.is_active = 1 AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id)")->fetch()['v'];
    return $invVal + $fallback;
}

/** Gesamter verfügbarer Lagerbestand über alle aktiven Produkte. */
function inv_total_all(): int {
    $pdo = db();
    $invSum = (int)$pdo->query("SELECT COALESCE(SUM(MAX(i.stock - i.reserved, 0)), 0) AS n
        FROM inventory i JOIN products p ON p.id = i.product_id WHERE p.is_active = 1")->fetch()['n'];
    $fallback = (int)$pdo->query("SELECT COALESCE(SUM(MAX(p.stock, 0)), 0) AS n FROM products p
        WHERE p.is_active = 1 AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id)")->fetch()['n'];
    return $invSum + $fallback;
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
