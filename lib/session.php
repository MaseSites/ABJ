<?php
function session_start_once(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_path', '/');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }
}

function cart_token(): string {
    $name = 'abj_cart';
    if (!empty($_COOKIE[$name]) && preg_match('/^[0-9a-f]{32}$/', $_COOKIE[$name])) {
        return $_COOKIE[$name];
    }
    $token = bin2hex(random_bytes(16));
    setcookie($name, $token, ['expires' => time() + 86400 * 90, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE[$name] = $token;
    return $token;
}

function cart_get(): array {
    $token = cart_token();
    $stmt = db()->prepare('SELECT product_id AS productId, size, qty FROM carts WHERE token = ?');
    $stmt->execute([$token]);
    return array_map(fn($r) => ['productId' => (int)$r['productId'], 'size' => (string)($r['size'] ?? ''), 'qty' => (int)$r['qty']], $stmt->fetchAll());
}

function cart_set(array $cart): void {
    $token = cart_token();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM carts WHERE token = ?')->execute([$token]);
        $stmt = $pdo->prepare("INSERT INTO carts (token, product_id, size, qty, updated_at) VALUES (?, ?, ?, ?, datetime('now'))");
        foreach ($cart as $line) {
            if ((int)($line['qty'] ?? 0) <= 0) continue;
            $stmt->execute([$token, (int)$line['productId'], $line['size'] ?? '', (int)$line['qty']]);
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
    }
    if (mt_rand(1, 200) === 1) {
        try { db()->exec("DELETE FROM carts WHERE updated_at < datetime('now', '-90 days')"); } catch (\Throwable $e) {}
    }
}

function cart_count(): int {
    $token = cart_token();
    $stmt = db()->prepare('SELECT COALESCE(SUM(qty), 0) AS n FROM carts WHERE token = ?');
    $stmt->execute([$token]);
    return (int)($stmt->fetch()['n'] ?? 0);
}

function last_order_get(): ?string {
    session_start_once();
    return $_SESSION['lastOrder'] ?? null;
}

function last_order_set(?string $ref): void {
    session_start_once();
    $_SESSION['lastOrder'] = $ref;
}

function admin_login(int $userId, string $username, string $role = 'root'): void {
    session_start_once();
    try { session_regenerate_id(true); } catch (\Throwable $e) {}
    $_SESSION['admin'] = true;
    $_SESSION['admin_id'] = $userId;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_role'] = $role === 'lookup' ? 'lookup' : 'root';
    $_SESSION['admin_ts'] = time();
    session_write_close();
}

/** Rolle des angemeldeten Admins: 'root' (alle Rechte) oder 'lookup' (beschränkt). */
function admin_role(): string {
    session_start_once();
    return is_admin() ? ($_SESSION['admin_role'] ?? 'root') : '';
}

function admin_username(): string {
    session_start_once();
    return (string)($_SESSION['admin_username'] ?? '');
}

function admin_is_root(): bool {
    return admin_role() === 'root';
}

/** Berechtigungen des beschränkten Lookup-Kontos. */
function admin_lookup_caps(): array {
    return ['orders.manage', 'products.manage', 'reviews.manage', 'discounts.manage', 'security.gen_code'];
}

/** Darf der angemeldete Admin die Aktion ausführen? Root darf alles. */
function admin_can(string $cap): bool {
    if (!is_admin()) return false;
    if (admin_is_root()) return true;
    return admin_role() === 'lookup' && in_array($cap, admin_lookup_caps(), true);
}

/** Server-seitige Absicherung: bricht ab, wenn die Berechtigung fehlt. */
function require_cap(string $cap): void {
    if (admin_can($cap)) return;
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if (!headers_sent()) {
        http_response_code(403);
        header('Location: ' . ($ref !== '' ? $ref : (base_path() . '/admin/index.php')) . (strpos($ref, 'denied=') === false ? (strpos($ref, '?') !== false ? '&' : '?') . 'denied=1' : ''));
    }
    exit;
}

function admin_logout(): void {
    session_start_once();
    $_SESSION = [];
    session_destroy();
}

function is_admin(): bool {
    session_start_once();
    return !empty($_SESSION['admin']);
}

function require_admin(): void {
    if (!is_admin()) redirect('/admin/login.php');
}
