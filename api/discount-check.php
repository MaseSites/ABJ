<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$code     = trim($_POST['code'] ?? '');
$country  = strtoupper(trim($_POST['country'] ?? 'CH'));
$currency = setting_get('currency') ?: 'CHF';

if ($code === '') json_response(['ok' => false, 'error' => 'Bitte gib einen Code ein.'], 422);

// Warenwert aus dem aktuellen Warenkorb berechnen
$subtotal = 0;
foreach (cart_get() as $line) {
    $p = product_by_id($line['productId']);
    if (!$p || !$p['is_active']) continue;
    $vr = inv_by_variant($line['productId'], $line['size'] ?? '', '');
    $unit = ($vr && $vr['variant_price_cents'] !== null)
        ? (int)$vr['variant_price_cents']
        : (int)($p['sale_price_cents'] ?? $p['price_cents']);
    $avail   = inv_stock_for_variant($line['productId'], $line['size'] ?? '', '');
    $isBO    = ($avail <= 0) && inv_is_back_order($line['productId'], $line['size'] ?? '', '');
    $safeQty = $isBO ? $line['qty'] : min($line['qty'], max(0, $avail));
    $subtotal += $unit * $safeQty;
}

$check = discount_validate($code, $subtotal);
if (!$check['ok']) json_response(['ok' => false, 'error' => $check['error']], 422);

$discountCents = (int)$check['discount_cents'];
$freeShipping  = !empty($check['free_shipping']);
$shipping      = $freeShipping ? 0 : shipping_cost_cents($country, $subtotal);
$total         = max(0, $subtotal - $discountCents) + $shipping;

json_response([
    'ok'             => true,
    'code'           => strtoupper($code),
    'discount_cents' => $discountCents,
    'discountText'   => $discountCents > 0 ? html_entity_decode('-' . format_price($discountCents, $currency)) : 'Gratisversand',
    'free_shipping'  => $freeShipping,
    'shipping_cents' => $shipping,
    'shippingText'   => $shipping === 0 ? 'Kostenlos' : html_entity_decode(format_price($shipping, $currency)),
    'total_cents'    => $total,
    'totalText'      => html_entity_decode(format_price($total, $currency)),
]);
