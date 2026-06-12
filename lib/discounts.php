<?php
// Rabattcodes: Validierung & Verwaltung

function discount_by_code(string $code): ?array {
    $stmt = db()->prepare('SELECT * FROM discount_codes WHERE upper(code) = upper(?)');
    $stmt->execute([trim($code)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Prüft einen Code gegen einen Warenwert.
 * Rückgabe: ['ok'=>bool, 'error'=>?, 'discount_cents'=>int, 'free_shipping'=>bool, 'code'=>row]
 */
function discount_validate(string $code, int $subtotalCents): array {
    $row = discount_by_code($code);
    if (!$row || !$row['is_active']) {
        return ['ok' => false, 'error' => 'Dieser Code ist ungültig.'];
    }
    if ($row['valid_until'] && strtotime($row['valid_until']) < time()) {
        return ['ok' => false, 'error' => 'Dieser Code ist abgelaufen.'];
    }
    if ((int)$row['max_uses'] > 0 && (int)$row['used_count'] >= (int)$row['max_uses']) {
        return ['ok' => false, 'error' => 'Dieser Code wurde bereits zu oft eingelöst.'];
    }
    if ((int)$row['min_order_cents'] > 0 && $subtotalCents < (int)$row['min_order_cents']) {
        return ['ok' => false, 'error' => 'Mindestbestellwert: ' . format_price((int)$row['min_order_cents'], setting_get('currency') ?: 'CHF')];
    }
    $discount = 0;
    $freeShipping = false;
    if ($row['type'] === 'percent') {
        $discount = (int)round($subtotalCents * min(100, max(0, (int)$row['value'])) / 100);
    } elseif ($row['type'] === 'fixed') {
        $discount = min($subtotalCents, (int)$row['value']);
    } elseif ($row['type'] === 'free_shipping') {
        $freeShipping = true;
    }
    return ['ok' => true, 'discount_cents' => $discount, 'free_shipping' => $freeShipping, 'code' => $row];
}

function discount_redeem(string $code): void {
    db()->prepare('UPDATE discount_codes SET used_count = used_count + 1 WHERE upper(code) = upper(?)')
       ->execute([trim($code)]);
}

function discounts_list(): array {
    return db()->query('SELECT * FROM discount_codes ORDER BY created_at DESC')->fetchAll();
}

function discount_create(array $d): void {
    db()->prepare('INSERT INTO discount_codes (code,type,value,min_order_cents,max_uses,valid_until,is_active) VALUES (?,?,?,?,?,?,?)')
       ->execute([
           strtoupper(trim($d['code'])), $d['type'] ?? 'percent', (int)($d['value'] ?? 0),
           (int)($d['min_order_cents'] ?? 0), (int)($d['max_uses'] ?? 0),
           trim($d['valid_until'] ?? ''), !empty($d['is_active']) ? 1 : 0,
       ]);
}

function discount_update(int $id, array $d): void {
    db()->prepare('UPDATE discount_codes SET code=?, type=?, value=?, min_order_cents=?, max_uses=?, valid_until=?, is_active=? WHERE id=?')
       ->execute([
           strtoupper(trim($d['code'])), $d['type'] ?? 'percent', (int)($d['value'] ?? 0),
           (int)($d['min_order_cents'] ?? 0), (int)($d['max_uses'] ?? 0),
           trim($d['valid_until'] ?? ''), !empty($d['is_active']) ? 1 : 0, $id,
       ]);
}

function discount_delete(int $id): void {
    db()->prepare('DELETE FROM discount_codes WHERE id=?')->execute([$id]);
}
