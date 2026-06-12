<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$productId = (int)($_POST['productId'] ?? 0);
$size      = trim($_POST['size'] ?? '');
$qty       = max(0, (int)($_POST['qty'] ?? 0)); // 0 = entfernen

$cart = cart_get();
$found = false;
foreach ($cart as $i => $line) {
    if ($line['productId'] === $productId && ($line['size'] ?? '') === $size) {
        if ($qty === 0) {
            array_splice($cart, $i, 1);
        } else {
            $avail = inv_stock_for_variant($productId, $size, '');
            $isBO  = ($avail <= 0) && inv_is_back_order($productId, $size, '');
            $cart[$i]['qty'] = $isBO ? $qty : min(max(1, $avail), $qty);
        }
        $found = true;
        break;
    }
}
if (!$found && $qty > 0) {
    json_response(['ok' => false, 'error' => 'Artikel nicht im Warenkorb'], 404);
}
cart_set($cart);

// Aktuellen Stand zurückgeben (gleiches Format wie cart-state.php)
$currency = setting_get('currency') ?: 'CHF';
$items    = [];
$total    = 0;
foreach (cart_get() as $line) {
    $p = product_by_id($line['productId']);
    if (!$p || !$p['is_active']) continue;
    $vr = inv_by_variant($line['productId'], $line['size'] ?? '', '');
    $unit = ($vr && $vr['variant_price_cents'] !== null)
        ? (int)$vr['variant_price_cents']
        : (int)($p['sale_price_cents'] ?? $p['price_cents']);
    $safeQty = (int)$line['qty'];
    if ($safeQty === 0) continue;
    $img = null;
    if ($vr) { $imgs = safe_parse($vr['images'] ?? '[]', []); $img = $imgs[0]['src'] ?? null; }
    $img = $img ?: ($p['images'][0]['src'] ?? null);
    $total += $unit * $safeQty;
    $items[] = [
        'productId' => $p['id'],
        'name'      => $p['name'],
        'url'       => url('/produkt.php?slug=' . urlencode($p['slug'])),
        'size'      => $line['size'] ?? '',
        'sizeLabel' => $vr ? ($vr['title'] ?: $line['size']) : ($line['size'] ?? ''),
        'qty'       => $safeQty,
        'image'     => $img,
        'lineText'  => html_entity_decode(format_price($unit * $safeQty, $currency)),
    ];
}

json_response([
    'ok'        => true,
    'items'     => $items,
    'count'     => array_sum(array_column($items, 'qty')),
    'totalText' => html_entity_decode(format_price($total, $currency)),
]);
