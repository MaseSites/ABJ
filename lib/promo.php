<?php
// Promo-/Empfehlungsprogramm: Codes weitergeben, Punkte sammeln, einlösen.

function promo_points(int $accountId): int {
    $stmt = db()->prepare('SELECT promo_points FROM accounts WHERE id = ?');
    $stmt->execute([$accountId]);
    $row = $stmt->fetch();
    return (int)($row['promo_points'] ?? 0);
}

function promo_add_points(int $accountId, int $delta): void {
    db()->prepare('UPDATE accounts SET promo_points = MAX(0, COALESCE(promo_points,0) + ?) WHERE id = ?')
       ->execute([$delta, $accountId]);
}

/** Eigene Promo-Codes eines Kontos. */
function promo_codes_for(int $accountId): array {
    $stmt = db()->prepare('SELECT * FROM promo_codes WHERE account_id = ? ORDER BY created_at DESC');
    $stmt->execute([$accountId]);
    return $stmt->fetchAll();
}

/** Inhaber-Konto-ID eines Promo-Codes (oder null). */
function promo_owner_of_code(string $code): ?int {
    $code = trim($code);
    if ($code === '') return null;
    $stmt = db()->prepare('SELECT account_id FROM promo_codes WHERE upper(code) = upper(?)');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ? (int)$row['account_id'] : null;
}

/** Neuen Promo-Code für ein Konto erzeugen. */
function promo_code_generate(int $accountId): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    } while (promo_owner_of_code($code));
    db()->prepare('INSERT INTO promo_codes (account_id, code) VALUES (?, ?)')->execute([$accountId, $code]);
    return $code;
}

/** Statistik: geworbene Kunden + deren Bestellungen. */
function promo_referral_stats(int $accountId): array {
    $r = db()->prepare('SELECT COUNT(*) n FROM accounts WHERE referred_by = ?');
    $r->execute([$accountId]);
    $refCount = (int)$r->fetch()['n'];
    $o = db()->prepare("SELECT COUNT(*) n FROM orders WHERE lower(email) IN (SELECT lower(email) FROM accounts WHERE referred_by = ?)");
    $o->execute([$accountId]);
    $orderCount = (int)$o->fetch()['n'];
    return ['referrals' => $refCount, 'orders' => $orderCount];
}

/** Punkte gutschreiben, wenn ein geworbener Kunde bestellt. */
function promo_award_for_buyer(int $buyerAccountId): void {
    $stmt = db()->prepare('SELECT referred_by FROM accounts WHERE id = ?');
    $stmt->execute([$buyerAccountId]);
    $row = $stmt->fetch();
    $referrer = (int)($row['referred_by'] ?? 0);
    if ($referrer > 0 && $referrer !== $buyerAccountId) {
        $pts = (int)(setting_get('promo_points_per_order') ?: 10);
        if ($pts > 0) promo_add_points($referrer, $pts);
    }
}

/** Verfügbare Prämien im Promo-Shop. */
function promo_rewards(): array {
    return [
        'ship' => ['label' => 'Gratis Versand', 'cost' => 50,  'type' => 'free_shipping', 'value' => 0,  'desc' => 'Gutschein für kostenlosen Versand bei einer Bestellung.'],
        'p10'  => ['label' => '10% Rabatt',     'cost' => 80,  'type' => 'percent',       'value' => 10, 'desc' => '10% Rabattcode für eine Bestellung.'],
        'p20'  => ['label' => '20% Rabatt',     'cost' => 150, 'type' => 'percent',       'value' => 20, 'desc' => '20% Rabattcode für eine Bestellung.'],
    ];
}

/** Prämie einlösen: Punkte abziehen, persönlichen Rabattcode erstellen. */
function promo_redeem(int $accountId, string $rewardKey): array {
    $rewards = promo_rewards();
    if (!isset($rewards[$rewardKey])) return ['ok' => false, 'error' => 'Unbekannte Prämie.'];
    $reward = $rewards[$rewardKey];
    $points = promo_points($accountId);
    if ($points < $reward['cost']) {
        return ['ok' => false, 'error' => 'Nicht genug Punkte. Du brauchst ' . $reward['cost'] . ', hast ' . $points . '.'];
    }
    // eindeutigen Rabattcode erzeugen
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = 'PROMO';
        for ($i = 0; $i < 5; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    } while (discount_by_code($code));
    discount_create([
        'code' => $code, 'type' => $reward['type'], 'value' => $reward['value'],
        'min_order_cents' => 0, 'max_uses' => 1, 'valid_until' => '', 'is_active' => 1,
    ]);
    promo_add_points($accountId, -$reward['cost']);
    db()->prepare('INSERT INTO promo_redemptions (account_id, reward, code, cost) VALUES (?, ?, ?, ?)')
       ->execute([$accountId, $reward['label'], $code, $reward['cost']]);
    return ['ok' => true, 'code' => $code, 'reward' => $reward['label']];
}

function promo_redemptions_for(int $accountId): array {
    $stmt = db()->prepare('SELECT * FROM promo_redemptions WHERE account_id = ? ORDER BY created_at DESC');
    $stmt->execute([$accountId]);
    return $stmt->fetchAll();
}
