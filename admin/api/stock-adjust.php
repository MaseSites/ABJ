<?php
require_once __DIR__ . '/../../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'POST required'], 405);
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) json_response(['ok' => false, 'error' => 'Keine ID'], 400);

$stmt = db()->prepare("SELECT i.*, p.price_cents, p.sale_price_cents
    FROM inventory i JOIN products p ON p.id = i.product_id WHERE i.id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) json_response(['ok' => false, 'error' => 'Lagereintrag nicht gefunden'], 404);

// Absoluter Wert ('stock') hat Vorrang, sonst Delta (+1/-1)
if (isset($_POST['stock']) && $_POST['stock'] !== '') {
    $newStock = max(0, (int)$_POST['stock']);
} else {
    $delta    = (int)($_POST['delta'] ?? 0);
    $newStock = max(0, (int)$row['stock'] + $delta);
}

db()->prepare("UPDATE inventory SET stock = ?, updated_at = datetime('now') WHERE id = ?")
   ->execute([$newStock, $id]);

$reserved  = (int)$row['reserved'];
$available = max(0, $newStock - $reserved);
$unit      = $row['variant_price_cents'] !== null
           ? (int)$row['variant_price_cents']
           : (int)($row['sale_price_cents'] ?? $row['price_cents']);
$value     = $available * $unit;
$minStock  = (int)$row['min_stock'];
$currency  = setting_get('currency') ?: 'CHF';

json_response([
    'ok'           => true,
    'stock'        => $newStock,
    'available'    => $available,
    'is_out'       => $available <= 0,
    'is_low'       => $available > 0 && $available <= $minStock,
    'valueText'    => html_entity_decode(format_price($value, $currency)),
    'totalStock'   => inv_total_all(),
    'totalValueText' => html_entity_decode(format_price(inv_total_value(), $currency)),
]);
