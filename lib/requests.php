<?php
function request_create(array $data): int {
    $stmt = db()->prepare('INSERT INTO product_requests (account_id, customer_name, email, phone, description, link, screenshot, status, price_cents, admin_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        (int)($data['account_id'] ?? 0),
        $data['customer_name'] ?? '',
        $data['email'] ?? '',
        $data['phone'] ?? '',
        $data['description'] ?? '',
        $data['link'] ?? '',
        $data['screenshot'] ?? '',
        $data['status'] ?? 'neu',
        (int)($data['price_cents'] ?? 0),
        $data['admin_note'] ?? '',
    ]);
    return (int)db()->lastInsertId();
}

function requests_list(string $status = ''): array {
    $sql = 'SELECT * FROM product_requests';
    $args = [];
    if ($status !== '') {
        $sql .= ' WHERE status = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY created_at DESC, id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function request_by_id(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM product_requests WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function request_update_status(int $id, string $status, int $priceCents = 0, string $note = ''): bool {
    $stmt = db()->prepare("UPDATE product_requests SET status=?, price_cents=?, admin_note=?, updated_at=datetime('now') WHERE id=?");
    $stmt->execute([$status, max(0, $priceCents), $note, $id]);
    return $stmt->rowCount() > 0;
}

function request_delete(int $id): bool {
    $stmt = db()->prepare('DELETE FROM product_requests WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}
