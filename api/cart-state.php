<?php
require_once __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: application/json');

$currency = setting_get('currency') ?: 'EUR';
$cart     = cart_get();
$items    = [];
$total    = 0;

foreach ($cart as $line) {
    $p = product_by_id($line['productId']);
    if (!$p || !$p['is_active']) continue;
    $variantRow = inv_by_variant($line['productId'], $line['size'] ?? '', '');
    $unit = ($variantRow && $variantRow['variant_price_cents'] !== null)
        ? (int)$variantRow['variant_price_cents']
        : (int)($p['sale_price_cents'] ?? $p['price_cents']);
    $avail   = inv_stock_for_variant($line['productId'], $line['size'] ?? '', '');
    $safeQty = min($line['qty'], max(0, $avail));
    if ($safeQty === 0) continue;
    $imgSrc = null;
    if ($variantRow) { $imgs = safe_parse($variantRow['images'] ?? '[]', []); $imgSrc = $imgs[0]['src'] ?? null; }
    $imgSrc = $imgSrc ?: ($p['images'][0]['src'] ?? null);
    $total += $unit * $safeQty;
    $items[] = [
        'productId' => $p['id'], 'slug' => $p['slug'], 'name' => $p['name'],
        'size'      => $variantRow ? ($variantRow['title'] ?: $line['size']) : ($line['size'] ?? ''),
        'qty'  => $safeQty, 'unitCents' => $unit, 'lineCents' => $unit * $safeQty,
        'image' => $imgSrc,
        'unitFormatted' => format_price($unit, $currency),
        'lineFormatted' => format_price($unit * $safeQty, $currency),
    ];
}

echo json_encode([
    'items'          => $items,
    'count'          => array_sum(array_column($items, 'qty')),
    'total'          => $total,
    'totalFormatted' => format_price($total, $currency),
]);
