<?php
require_once __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: application/json');

$q   = trim($_GET['q']   ?? '');
$ids = trim($_GET['ids'] ?? '');

function api_product_lite(array $p): array {
    $currency = setting_get('currency') ?: 'CHF';
    $sale = !empty($p['sale_price_cents']) ? (int)$p['sale_price_cents'] : null;
    return [
        'id'               => (int)$p['id'],
        'slug'             => $p['slug'],
        'name'             => $p['name'],
        'category'         => $p['category'],
        'price_cents'      => (int)$p['price_cents'],
        'sale_price_cents' => $sale,
        'image'            => $p['images'][0]['src'] ?? ($p['image'] ?? null),
        'stock'            => inv_total_stock((int)$p['id']),
        'url'              => url('/produkt.php?slug=' . urlencode($p['slug'])),
        'priceText'        => html_entity_decode(format_price($sale ?: (int)$p['price_cents'], $currency)),
        'oldPriceText'     => $sale ? html_entity_decode(format_price((int)$p['price_cents'], $currency)) : null,
    ];
}

if ($ids) {
    $idList   = array_filter(array_map('intval', explode(',', $ids)));
    $products = array_filter(array_map('product_by_id', $idList));
    $items = array_values(array_map('api_product_lite', array_filter($products, fn($p) => $p['is_active'])));
} elseif ($q) {
    $items = array_map('api_product_lite', products_search($q));
} else {
    $items = array_map('api_product_lite', products_list_public());
}

echo json_encode(['items' => $items]);
