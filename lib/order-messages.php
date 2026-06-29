<?php
function order_message_create(array $data): int {
    $stmt = db()->prepare('INSERT INTO order_messages (order_reference, author_role, author_name, subject, body, is_system, is_read) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['order_reference'] ?? '',
        $data['author_role'] ?? 'admin',
        $data['author_name'] ?? '',
        $data['subject'] ?? '',
        $data['body'] ?? '',
        !empty($data['is_system']) ? 1 : 0,
        !empty($data['is_read']) ? 1 : 0,
    ]);
    return (int)db()->lastInsertId();
}

function order_messages_by_ref(string $ref): array {
    $stmt = db()->prepare('SELECT * FROM order_messages WHERE order_reference = ? ORDER BY created_at ASC, id ASC');
    $stmt->execute([$ref]);
    return $stmt->fetchAll();
}

function order_messages_mark_read(string $ref): void {
    db()->prepare("UPDATE order_messages SET is_read=1 WHERE order_reference=? AND author_role='admin'")->execute([$ref]);
}

function order_messages_unread_count(): int {
    $stmt = db()->query("SELECT COUNT(*) AS n FROM order_messages WHERE author_role='admin' AND is_read=0");
    return (int)$stmt->fetch()['n'];
}
