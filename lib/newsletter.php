<?php
function newsletter_count(): int {
    $stmt = db()->query('SELECT COUNT(*) AS n FROM newsletter');
    return (int)$stmt->fetch()['n'];
}

function newsletter_list(): array {
    return db()->query('SELECT * FROM newsletter ORDER BY created_at DESC')->fetchAll();
}

function newsletter_delete(int $id): void {
    db()->prepare('DELETE FROM newsletter WHERE id=?')->execute([$id]);
}
