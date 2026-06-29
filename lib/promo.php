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

/** Eigene Promo-Codes eines Kontos – inkl. „eingelöst von" (Name/E-Mail). */
function promo_codes_for(int $accountId): array {
    $stmt = db()->prepare("SELECT pc.*, u.email AS used_email, u.name AS used_name
        FROM promo_codes pc LEFT JOIN accounts u ON u.id = pc.used_by
        WHERE pc.account_id = ? ORDER BY pc.created_at DESC");
    $stmt->execute([$accountId]);
    return $stmt->fetchAll();
}

/** Alle Promo-Codes mit Ersteller + Einlöser (für den Admin). */
function promo_codes_all(): array {
    return db()->query("SELECT pc.*, a.email AS owner_email, a.name AS owner_name,
            u.email AS used_email, u.name AS used_name
        FROM promo_codes pc
        LEFT JOIN accounts a ON a.id = pc.account_id
        LEFT JOIN accounts u ON u.id = pc.used_by
        ORDER BY pc.created_at DESC")->fetchAll();
}

/** Werber-Konto-ID eines Codes (oder null; Admin-Codes haben account_id 0). */
function promo_owner_of_code(string $code): ?int {
    $row = code_find($code);
    return ($row && (int)$row['account_id'] > 0) ? (int)$row['account_id'] : null;
}

/** Neuen Promo-Code für ein Konto erzeugen. */
function promo_code_generate(int $accountId): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    } while (code_find($code));
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

/** Punkte je 100 CHF Bestellwert (einstellbar). */
function promo_points_per_100(): int {
    return max(0, (int)(setting_get('promo_points_per_100') ?: 10));
}

/**
 * Punkte gutschreiben, wenn ein geworbener Kunde bestellt.
 * Punkte richten sich nach dem Bestellwert: pro 100 CHF gibt es N Punkte.
 */
function promo_award_for_buyer(int $buyerAccountId, int $orderTotalCents): void {
    $stmt = db()->prepare('SELECT referred_by FROM accounts WHERE id = ?');
    $stmt->execute([$buyerAccountId]);
    $row = $stmt->fetch();
    $referrer = (int)($row['referred_by'] ?? 0);
    if ($referrer <= 0 || $referrer === $buyerAccountId || $orderTotalCents <= 0) return;
    $pts = (int)floor(($orderTotalCents / 10000) * promo_points_per_100());
    if ($pts > 0) promo_add_points($referrer, $pts);
}

/** Verfügbare Prämien im Promo-Shop. */
function promo_rewards(): array {
    return [
        'ship'  => ['label' => 'Gratis Versand',  'cost' => 50,  'type' => 'free_shipping', 'value' => 0,    'desc' => 'Eine Bestellung versandkostenfrei.', 'icon' => '🚚'],
        'p5'    => ['label' => '5% Rabatt',        'cost' => 40,  'type' => 'percent',       'value' => 5,    'desc' => '5% auf eine Bestellung.',           'icon' => '%'],
        'p10'   => ['label' => '10% Rabatt',       'cost' => 80,  'type' => 'percent',       'value' => 10,   'desc' => '10% auf eine Bestellung.',          'icon' => '%'],
        'p15'   => ['label' => '15% Rabatt',       'cost' => 120, 'type' => 'percent',       'value' => 15,   'desc' => '15% auf eine Bestellung.',          'icon' => '%'],
        'p20'   => ['label' => '20% Rabatt',       'cost' => 160, 'type' => 'percent',       'value' => 20,   'desc' => '20% auf eine Bestellung.',          'icon' => '%'],
        'chf10' => ['label' => 'CHF 10 Gutschein', 'cost' => 100, 'type' => 'fixed',        'value' => 1000, 'desc' => 'CHF 10 Rabatt auf eine Bestellung.', 'icon' => '💰'],
        'chf25' => ['label' => 'CHF 25 Gutschein', 'cost' => 230, 'type' => 'fixed',        'value' => 2500, 'desc' => 'CHF 25 Rabatt auf eine Bestellung.', 'icon' => '💰'],
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
