<?php
require_once __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: application/json');

$q   = trim($_GET['q']   ?? '');
$ids = trim($_GET['ids'] ?? '');

if ($ids) {
    $idList   = array_filter(array_map('intval', explode(',', $ids)));
    $products = array_filter(array_map('product_by_id', $idList));
    echo json_encode(array_values(array_map(function($p) {
        return ['id' => $p['id'], 'slug' => $p['slug'], 'name' => $p['name'], 'category' => $p['category'],
                'price_cents' => $p['price_cents'], 'sale_price_cents' => $p['sale_price_cents'],
                'image' => $p['images'][0]['src'] ?? null, 'stock' => $p['stock']];
    }, $products)));
} elseif ($q) {
    echo json_encode(products_list_lite_search($q));
} else {
    echo json_encode(products_list_lite());
}

function products_list_lite_search(string $q): array {
    $results = products_search($q);
    return array_map(function($p) {
        return ['id' => $p['id'], 'slug' => $p['slug'], 'name' => $p['name'], 'category' => $p['category'],
                'price_cents' => $p['price_cents'], 'sale_price_cents' => $p['sale_price_cents'],
                'image' => $p['images'][0]['src'] ?? null, 'stock' => $p['stock']];
    }, $results);
}
