<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$productId = (int)($_POST['productId'] ?? 0);
$size      = trim($_POST['size'] ?? '');
$qty       = max(1, (int)($_POST['qty'] ?? 1));
$slug      = trim($_POST['slug'] ?? '');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_has($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

$p = product_by_id($productId);
if (!$p || !$p['is_active']) {
    if ($isAjax) json_response(['ok' => false, 'error' => 'unavailable'], 400);
    redirect('/produkt/' . urlencode($slug) . '?error=unavailable');
}
if (inv_has_variants($productId) && !$size) {
    if ($isAjax) json_response(['ok' => false, 'error' => 'variant'], 400);
    redirect('/produkt/' . urlencode($slug) . '?error=variant');
}
$avail = inv_stock_for_variant($productId, $size, '');
if ($avail <= 0) {
    if ($isAjax) json_response(['ok' => false, 'error' => 'soldout'], 400);
    redirect('/produkt/' . urlencode($slug) . '?error=soldout');
}

$cart     = cart_get();
$existing = null;
foreach ($cart as &$line) {
    if ($line['productId'] === $productId && ($line['size'] ?? '') === $size) {
        $existing = &$line; break;
    }
}
if ($existing) {
    $existing['qty'] = min($avail, $existing['qty'] + $qty);
} else {
    $cart[] = ['productId' => $productId, 'size' => $size, 'qty' => min($avail, $qty)];
}
cart_set($cart);

if ($isAjax) {
    $count = cart_count();
    json_response(['ok' => true, 'cartCount' => $count]);
}
redirect('/warenkorb');
