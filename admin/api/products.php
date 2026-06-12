<?php
require_once __DIR__ . '/../../lib/bootstrap.php';
require_admin();

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

/* ── DELETE: Produkt löschen ── */
if ($method === 'DELETE') {
    if (!$id) { json_response(['ok' => false, 'error' => 'Keine ID'], 400); }
    inv_delete_by_product($id);
    product_delete($id);
    json_response(['ok' => true]);
}

/* ── POST: Erstellen oder Aktualisieren ── */
if ($method === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) { json_response(['ok' => false, 'error' => 'Name fehlt'], 422); }

    $priceCents = api_parse_cents($_POST['price'] ?? '');
    if ($priceCents === null) { json_response(['ok' => false, 'error' => 'Preis fehlt'], 422); }

    $saleCents = api_parse_cents($_POST['sale_price'] ?? '');

    try {
        /* Bilder */
        $images = json_decode($_POST['existing_images'] ?? '[]', true) ?: [];
        $urlLines = array_filter(array_map('trim', explode("\n", $_POST['image_urls'] ?? '')));
        foreach ($urlLines as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $images[] = ['type' => 'url', 'src' => $url];
            }
        }

        /* Varianten */
        $hasVariants  = ($_POST['has_variants'] ?? '0') === '1';
        $optionGroups = json_decode($_POST['option_groups'] ?? '[]', true) ?: [];
        $variants     = $hasVariants ? (json_decode($_POST['variants'] ?? '[]', true) ?: []) : [];
        $stock        = $hasVariants ? 0 : (int)($_POST['stock'] ?? 0);

        $sizes = [];
        foreach ($optionGroups as $g) {
            if (($g['key'] ?? '') === 'size') { $sizes = $g['values'] ?? []; break; }
        }

        $data = [
            'name'             => $name,
            'description'      => trim($_POST['description'] ?? ''),
            'category'         => trim($_POST['category'] ?? '') ?: 'Allgemein',
            'price_cents'      => $priceCents,
            'sale_price_cents' => $saleCents,
            'stock'            => $stock,
            'is_bestseller'    => !empty($_POST['is_bestseller']),
            'is_active'        => !empty($_POST['is_active']),
            'images'           => $images,
            'sizes'            => $sizes,
            'option_groups'    => $optionGroups,
        ];

        if ($id) {
            product_update($id, $data);
            $productId = $id;
        } else {
            $new = product_create($data);
            $productId = $new['id'];
        }

        /* Lager-Einträge speichern */
        if ($hasVariants && !empty($variants)) {
            inv_delete_by_product($productId);
            foreach ($variants as $v) {
                $size  = '';
                $color = '';
                foreach ($v['option_values'] ?? [] as $ov) {
                    if (($ov['key'] ?? '') === 'size')  $size  = $ov['value'] ?? '';
                    if (($ov['key'] ?? '') === 'color') $color = $ov['value'] ?? '';
                }
                $imgUrl = trim($v['image_url'] ?? '');
                inv_upsert([
                    'product_id'          => $productId,
                    'sku'                 => $v['sku'] ?? '',
                    'size'                => $size,
                    'color'               => $color,
                    'option_values'       => $v['option_values'] ?? [],
                    'stock'               => (int)($v['stock'] ?? 0),
                    'reserved'            => 0,
                    'min_stock'           => (int)($v['min_stock'] ?? 3),
                    'next_delivery'       => '',
                    'notes'               => '',
                    'title'               => implode(' / ', array_column($v['option_values'] ?? [], 'value')),
                    'images'              => $imgUrl ? [['src' => $imgUrl]] : [],
                    'variant_price_cents' => ($v['variant_price_cents'] !== null && $v['variant_price_cents'] !== '')
                                             ? (int)$v['variant_price_cents'] : null,
                    'is_default'          => !empty($v['is_default']),
                ]);
            }
        } elseif (!$hasVariants) {
            // Automatischer Lagereintrag: jedes Produkt erscheint in der
            // Lagerverwaltung — bestehende Daten der Standardvariante bleiben erhalten.
            $existing = inv_by_variant($productId, '', '');
            db()->prepare("DELETE FROM inventory WHERE product_id = ? AND NOT (size = '' AND color = '')")
               ->execute([$productId]);
            inv_upsert([
                'product_id'          => $productId,
                'sku'                 => $existing['sku'] ?? '',
                'size'                => '',
                'color'               => '',
                'option_values'       => [],
                'stock'               => $stock,
                'reserved'            => (int)($existing['reserved'] ?? 0),
                'min_stock'           => (int)($existing['min_stock'] ?? 3),
                'next_delivery'       => $existing['next_delivery'] ?? '',
                'notes'               => $existing['notes'] ?? '',
                'title'               => '',
                'images'              => [],
                'variant_price_cents' => null,
                'is_default'          => true,
                'back_order'          => !empty($existing['back_order']),
            ]);
        }

        json_response(['ok' => true, 'id' => $productId]);

    } catch (Throwable $e) {
        json_response(['ok' => false, 'error' => 'DB-Fehler: ' . $e->getMessage()], 500);
    }
}

json_response(['ok' => false, 'error' => 'Method not allowed'], 405);

function api_parse_cents($value): ?int {
    $raw = trim(str_replace(',', '.', (string)$value));
    if ($raw === '') return null;
    $clean = preg_replace('/[^0-9.]/', '', $raw);
    if ($clean === '') return null;
    $val = (float)$clean;
    return is_finite($val) ? (int)round($val * 100) : null;
}
