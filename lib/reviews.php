<?php
// Produktbewertungen (mit Moderation: neue Bewertungen sind erst nach Freigabe sichtbar)

function reviews_for_product(int $productId): array {
    $stmt = db()->prepare('SELECT * FROM reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC LIMIT 50');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function reviews_avg(int $productId): array {
    $stmt = db()->prepare('SELECT COUNT(*) AS n, COALESCE(AVG(rating),0) AS avg FROM reviews WHERE product_id = ? AND is_approved = 1');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return ['count' => (int)$row['n'], 'avg' => round((float)$row['avg'], 1)];
}

function review_create(int $productId, string $author, int $rating, string $text): void {
    db()->prepare('INSERT INTO reviews (product_id, author, rating, text, is_approved) VALUES (?,?,?,?,0)')
       ->execute([$productId, mb_substr(trim($author), 0, 80), max(1, min(5, $rating)), mb_substr(trim($text), 0, 1500)]);
}

function reviews_list_admin(?int $approved = null): array {
    $sql = 'SELECT r.*, p.name AS product_name, p.slug FROM reviews r LEFT JOIN products p ON p.id = r.product_id';
    if ($approved !== null) $sql .= ' WHERE r.is_approved = ' . (int)$approved;
    $sql .= ' ORDER BY r.created_at DESC LIMIT 300';
    return db()->query($sql)->fetchAll();
}

function reviews_pending_count(): int {
    return (int)db()->query('SELECT COUNT(*) AS n FROM reviews WHERE is_approved = 0')->fetch()['n'];
}

function review_set_approved(int $id, bool $approved): void {
    db()->prepare('UPDATE reviews SET is_approved=? WHERE id=?')->execute([$approved ? 1 : 0, $id]);
}

function review_delete(int $id): void {
    db()->prepare('DELETE FROM reviews WHERE id=?')->execute([$id]);
}
