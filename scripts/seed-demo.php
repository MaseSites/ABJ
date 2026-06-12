<?php
// Demo-Daten für lokale Entwicklung: php scripts/seed-demo.php
// Legt nur Daten an, wenn noch keine Produkte existieren.
require_once __DIR__ . '/../lib/bootstrap.php';

$count = (int)db()->query('SELECT COUNT(*) AS n FROM products')->fetch()['n'];
if ($count > 0) {
    echo "Es existieren bereits $count Produkte – nichts zu tun.\n";
    exit;
}

$demo = [
    ['Stone Island Sweatshirt', 'Hoodies', 18900, 12900, ['S','M','L','XL'], 1],
    ['Trapstar Irongate Jacke', 'Jacken', 24900, null, ['M','L'], 1],
    ['Nike Tech Fleece Hose', 'Hosen', 9900, 7900, ['S','M','L'], 1],
    ['Ralph Lauren Polo', 'Shirts', 7900, null, ['S','M','L','XL'], 0],
    ['C.P. Company Goggle Hoodie', 'Hoodies', 21900, 16900, ['M','L','XL'], 1],
    ['Moncler Daunenjacke', 'Jacken', 49900, null, ['M','L'], 0],
    ['Carhartt WIP Cargohose', 'Hosen', 11900, 8900, ['S','M','L'], 0],
    ['Stussy 8-Ball Tee', 'Shirts', 5900, 3900, ['S','M','L','XL'], 1],
];

foreach ($demo as [$name, $cat, $price, $sale, $sizes, $bestseller]) {
    $p = product_create([
        'name' => $name, 'description' => "<p>Authentifiziertes Piece in sehr gutem Zustand. $name – geprüft und versandbereit.</p>",
        'category' => $cat, 'price_cents' => $price, 'sale_price_cents' => $sale,
        'sizes' => $sizes, 'option_groups' => [], 'images' => [],
        'stock' => 10, 'is_bestseller' => $bestseller, 'is_active' => 1,
    ]);
    foreach ($sizes as $i => $sz) {
        inv_upsert([
            'product_id' => $p['id'], 'size' => $sz, 'color' => '',
            'sku' => 'ABJ-' . $p['id'] . '-' . $sz, 'stock' => 3 + $i,
            'min_stock' => 2, 'next_delivery' => '', 'notes' => '', 'title' => $sz,
            'option_values' => [['key' => 'groesse', 'label' => 'Grösse', 'value' => $sz]],
            'images' => [], 'variant_price_cents' => null,
            'is_default' => $i === 0, 'back_order' => false,
        ]);
    }
    echo "Produkt angelegt: $name\n";
}

discount_create(['code' => 'WILLKOMMEN10', 'type' => 'percent', 'value' => 10, 'min_order_cents' => 0, 'max_uses' => 0, 'valid_until' => '', 'is_active' => 1]);
echo "Rabattcode WILLKOMMEN10 (-10%) angelegt.\n";
echo "Fertig.\n";
