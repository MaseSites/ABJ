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
        // Bestehende Bild-URLs ebenfalls auf https:// hochstufen (Mixed-Content-Schutz)
        foreach ($images as &$img) {
            if (!empty($img['src'])) $img['src'] = secure_url($img['src']);
        }
        unset($img);
        $urlLines = array_filter(array_map('trim', explode("\n", $_POST['image_urls'] ?? '')));
        foreach ($urlLines as $url) {
            $url = secure_url($url);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $images[] = ['type' => 'url', 'src' => $url];
            }
        }

        /* Varianten (vereinfacht: nur Grössen mit eigenem Bestand) */
        $hasVariants = ($_POST['has_variants'] ?? '0') === '1';
        $variants    = json_decode($_POST['variants'] ?? '[]', true) ?: [];

        // Nur gültige Grössen-Varianten (mit Namen) behalten.
        $cleanVariants = [];
        foreach ($variants as $v) {
            $size = '';
            foreach ($v['option_values'] ?? [] as $ov) {
                if (($ov['key'] ?? '') === 'size') $size = trim((string)($ov['value'] ?? ''));
            }
            if ($size === '') continue;
            $cleanVariants[$size] = [
                'size'                => $size,
                'stock'               => max(0, (int)($v['stock'] ?? 0)),
                'variant_price_cents' => ($v['variant_price_cents'] !== null && $v['variant_price_cents'] !== '')
                                         ? max(0, (int)$v['variant_price_cents']) : null,
                'is_default'          => !empty($v['is_default']),
            ];
        }
        $cleanVariants = array_values($cleanVariants);

        // Varianten zählen nur, wenn der Schalter an ist UND echte Grössen da sind.
        $useVariants = $hasVariants && !empty($cleanVariants);
        $stock       = $useVariants ? 0 : max(0, (int)($_POST['stock'] ?? 0));

        // WICHTIG: Optionsgruppen werden IMMER aus den Varianten abgeleitet,
        // damit Lager und Shop-Anzeige nie auseinanderlaufen (Soldout-Bug).
        $sizes        = $useVariants ? array_column($cleanVariants, 'size') : [];
        $optionGroups = $useVariants ? [['key' => 'size', 'label' => 'Grösse', 'values' => $sizes]] : [];

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

        /* Lager IMMER vollständig mit dem Formular synchronisieren */
        inv_delete_by_product($productId);
        if ($useVariants) {
            $hasDefault = false;
            foreach ($cleanVariants as $v) { if ($v['is_default']) { $hasDefault = true; break; } }
            foreach ($cleanVariants as $i => $v) {
                inv_upsert([
                    'product_id'          => $productId,
                    'sku'                 => '',
                    'size'                => $v['size'],
                    'color'               => '',
                    'option_values'       => [['key' => 'size', 'label' => 'Grösse', 'value' => $v['size']]],
                    'stock'               => $v['stock'],
                    'reserved'            => 0,
                    'min_stock'           => 3,
                    'next_delivery'       => '',
                    'notes'               => '',
                    'title'               => $v['size'],
                    'images'              => [],
                    'variant_price_cents' => $v['variant_price_cents'],
                    'is_default'          => $v['is_default'] || (!$hasDefault && $i === 0),
                ]);
            }
        } else {
            // Ein einziger Lagereintrag mit dem Gesamtbestand.
            inv_upsert([
                'product_id'          => $productId,
                'sku'                 => '',
                'size'                => '',
                'color'               => '',
                'option_values'       => [],
                'stock'               => $stock,
                'reserved'            => 0,
                'min_stock'           => 3,
                'next_delivery'       => '',
                'notes'               => '',
                'title'               => '',
                'images'              => [],
                'variant_price_cents' => null,
                'is_default'          => true,
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
