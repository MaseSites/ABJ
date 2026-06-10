<?php
function messages_list(): array {
    return db()->query('SELECT * FROM messages ORDER BY created_at DESC')->fetchAll();
}

function messages_unread_count(): int {
    $stmt = db()->query('SELECT COUNT(*) AS n FROM messages WHERE is_read=0');
    return (int)$stmt->fetch()['n'];
}

function message_mark_read(int $id): void {
    db()->prepare('UPDATE messages SET is_read=1 WHERE id=?')->execute([$id]);
}

function message_delete(int $id): void {
    db()->prepare('DELETE FROM messages WHERE id=?')->execute([$id]);
}

function message_create(array $data): void {
    db()->prepare('INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)')
        ->execute([$data['name'] ?? '', $data['email'] ?? '', $data['subject'] ?? '', $data['message'] ?? '']);
}
