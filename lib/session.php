<?php
function session_start_once(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
}

function cart_get(): array {
    session_start_once();
    return $_SESSION['cart'] ?? [];
}

function cart_set(array $cart): void {
    session_start_once();
    $_SESSION['cart'] = $cart;
}

function cart_count(): int {
    return array_sum(array_column(cart_get(), 'qty'));
}

function last_order_get(): ?string {
    session_start_once();
    return $_SESSION['lastOrder'] ?? null;
}

function last_order_set(?string $ref): void {
    session_start_once();
    $_SESSION['lastOrder'] = $ref;
}

function admin_login(int $userId, string $username): void {
    session_start_once();
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['admin_id'] = $userId;
    $_SESSION['admin_username'] = $username;
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
