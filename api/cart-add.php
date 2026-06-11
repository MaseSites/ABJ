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
$avail  = inv_stock_for_variant($productId, $size, '');
$isBO   = ($avail <= 0) && inv_is_back_order($productId, $size, '');
if ($avail <= 0 && !$isBO) {
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
    $existing['qty'] = $isBO ? ($existing['qty'] + $qty) : min($avail, $existing['qty'] + $qty);
} else {
    $cart[] = ['productId' => $productId, 'size' => $size, 'qty' => $isBO ? $qty : min($avail, $qty)];
}
cart_set($cart);

if ($isAjax) {
    $currency = setting_get('currency') ?: 'CHF';
    $items    = [];
    $total    = 0;
    foreach (cart_get() as $line) {
        $cp = product_by_id($line['productId']);
        if (!$cp || !$cp['is_active']) continue;
        $vr   = inv_by_variant($line['productId'], $line['size'] ?? '', '');
        $unit = ($vr && $vr['variant_price_cents'] !== null)
            ? (int)$vr['variant_price_cents']
            : (int)($cp['sale_price_cents'] ?? $cp['price_cents']);
        $safeQty = (int)$line['qty'];
        if ($safeQty === 0) continue;
        $img = null;
        if ($vr) { $imgs = safe_parse($vr['images'] ?? '[]', []); $img = $imgs[0]['src'] ?? null; }
        $img = $img ?: ($cp['images'][0]['src'] ?? null);
        $total += $unit * $safeQty;
        $items[] = [
            'productId' => $cp['id'],
            'name'      => $cp['name'],
            'url'       => '/produkt/' . $cp['slug'],
            'size'      => $vr ? ($vr['title'] ?: $line['size']) : ($line['size'] ?? ''),
            'qty'       => $safeQty,
            'image'     => $img,
            'lineText'  => format_price($unit * $safeQty, $currency),
        ];
    }
    json_response([
        'ok'        => true,
        'added'     => $p['name'],
        'count'     => array_sum(array_column($items, 'qty')),
        'totalText' => format_price($total, $currency),
        'items'     => $items,
    ]);
}
redirect('/warenkorb');
