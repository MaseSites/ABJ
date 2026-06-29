<?php
function account_message_create(array $data): int {
    $accountId = (int)($data['account_id'] ?? 0);
    if ($accountId <= 0) return 0;
    $stmt = db()->prepare('INSERT INTO account_messages (account_id, order_reference, sender_role, subject, body, is_read, message_type, action_url, decline_url, action_label, decline_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $accountId,
        $data['order_reference'] ?? '',
        $data['sender_role'] ?? 'admin',
        $data['subject'] ?? '',
        $data['body'] ?? '',
        !empty($data['is_read']) ? 1 : 0,
        $data['message_type'] ?? 'plain',
        $data['action_url'] ?? '',
        $data['decline_url'] ?? '',
        $data['action_label'] ?? '',
        $data['decline_label'] ?? '',
    ]);
    return (int)db()->lastInsertId();
}

function account_messages_by_account(int $accountId): array {
    $stmt = db()->prepare('SELECT * FROM account_messages WHERE account_id = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$accountId]);
    return $stmt->fetchAll();
}

function account_messages_unread_count(int $accountId): int {
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM account_messages WHERE account_id = ? AND is_read = 0');
    $stmt->execute([$accountId]);
    return (int)$stmt->fetch()['n'];
}

function account_messages_mark_read(int $accountId): void {
    db()->prepare('UPDATE account_messages SET is_read=1 WHERE account_id=?')->execute([$accountId]);
}

function account_message_by_id(int $accountId, int $messageId): ?array {
    $stmt = db()->prepare('SELECT * FROM account_messages WHERE account_id = ? AND id = ? LIMIT 1');
    $stmt->execute([$accountId, $messageId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function account_message_delete(int $accountId, int $messageId): bool {
    $stmt = db()->prepare('DELETE FROM account_messages WHERE account_id = ? AND id = ?');
    $stmt->execute([$accountId, $messageId]);
    return $stmt->rowCount() > 0;
}
