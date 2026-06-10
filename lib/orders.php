<?php
function order_create(array $data): string {
    $reference = 'ABJ-' . nano_id(8);
    $isPaid = in_array($data['payment_method'] ?? '', ['kreditkarte', 'paypal']);
    $stmt = db()->prepare("INSERT INTO orders (reference,customer_name,email,phone,address,items,total_cents,shipping_cents,status,payment_status,payment_method)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $reference, $data['customer_name'] ?? '', $data['email'] ?? '',
        $data['phone'] ?? '',
        is_string($data['address']) ? $data['address'] : json_encode($data['address'] ?? []),
        json_encode($data['items'] ?? []),
        $data['total_cents'] ?? 0, $data['shipping_cents'] ?? 0,
        'neu', $isPaid ? 'bezahlt' : 'offen',
        $data['payment_method'] ?? '',
    ]);
    return $reference;
}

function orders_list(): array {
    $stmt = db()->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 500');
    return array_map('order_parse', $stmt->fetchAll());
}

function order_by_ref(string $ref): ?array {
    $stmt = db()->prepare('SELECT * FROM orders WHERE reference = ?');
    $stmt->execute([$ref]);
    $row = $stmt->fetch();
    return $row ? order_parse($row) : null;
}

function order_update_status(string $ref, string $status, string $paymentStatus): bool {
    $stmt = db()->prepare('UPDATE orders SET status=?, payment_status=? WHERE reference=?');
    $stmt->execute([$status, $paymentStatus, $ref]);
    return $stmt->rowCount() > 0;
}

function order_delete(string $ref): bool {
    $stmt = db()->prepare('DELETE FROM orders WHERE reference=?');
    $stmt->execute([$ref]);
    return $stmt->rowCount() > 0;
}

function order_parse(array $r): array {
    $r['items'] = json_decode($r['items'] ?? '[]', true) ?: [];
    $addr = $r['address'] ?? '';
    $parsed = json_decode($addr, true);
    if (is_array($parsed)) $r['address'] = $parsed;
    return $r;
}

function orders_stats(int $days = 7): array {
    $pdo = db();
    $totalRevenue = (int)$pdo->query("SELECT COALESCE(SUM(total_cents),0) AS c FROM orders WHERE payment_status='bezahlt'")->fetch()['c'];
    $openCount = (int)$pdo->query("SELECT COUNT(*) AS n FROM orders WHERE payment_status != 'bezahlt'")->fetch()['n'];
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(total_cents),0) AS revenue FROM orders WHERE date(created_at) = date('now', ?)");
        $stmt->execute(["-$i days"]);
        $day = $stmt->fetch();
        $series[] = ['dayOffset' => $i, 'orders' => (int)$day['orders'], 'revenue' => (int)$day['revenue']];
    }
    $maxOrders = max(1, ...array_column($series, 'orders'));
    return ['totalRevenue' => $totalRevenue, 'openCount' => $openCount, 'series' => $series, 'maxOrders' => $maxOrders];
}
