<?php
require_once __DIR__ . '/../../lib/bootstrap.php';
require_admin();
if (!admin_can('inventory.manage')) { json_response(['ok' => false, 'error' => 'Keine Berechtigung'], 403); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'POST required'], 405);
}

$productId = (int)($_POST['product_id'] ?? 0);
if (!$productId || !product_by_id($productId)) {
    json_response(['ok' => false, 'error' => 'Produkt nicht gefunden'], 404);
}

$variants = json_decode($_POST['variants'] ?? '[]', true);
if (!is_array($variants)) {
    json_response(['ok' => false, 'error' => 'Ungültige Daten'], 422);
}

$keep = [];
foreach ($variants as $v) {
    $size  = trim((string)($v['size'] ?? ''));
    $color = trim((string)($v['color'] ?? ''));
    $key   = $size . '|' . $color;
    if (isset($keep[$key])) continue; // Duplikate überspringen
    $keep[$key] = true;
    inv_upsert([
        'product_id'          => $productId,
        'size'                => $size,
        'color'               => $color,
        'sku'                 => trim((string)($v['sku'] ?? '')),
        'stock'               => max(0, (int)($v['stock'] ?? 0)),
        'min_stock'           => max(0, (int)($v['min_stock'] ?? 3)),
        'next_delivery'       => trim((string)($v['next_delivery'] ?? '')),
        'notes'               => trim((string)($v['notes'] ?? '')),
        'title'               => trim((string)($v['title'] ?? '')),
        'option_values'       => is_array($v['option_values'] ?? null) ? $v['option_values'] : [],
        'images'              => is_array($v['images'] ?? null) ? $v['images'] : [],
        'variant_price_cents' => $v['variant_price_cents'] ?? null,
        'is_default'          => !empty($v['is_default']),
        'back_order'          => !empty($v['back_order']),
    ]);
}

// Entfernte Zeilen löschen
$existing = inv_by_product($productId);
$del = db()->prepare('DELETE FROM inventory WHERE id = ?');
foreach ($existing as $row) {
    $key = ($row['size'] ?? '') . '|' . ($row['color'] ?? '');
    if (!isset($keep[$key])) $del->execute([$row['id']]);
}

json_response(['ok' => true]);
