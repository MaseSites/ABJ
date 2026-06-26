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

        /* Varianten: Farben (mit Bild) × Größen × Bestand pro Kombination */
        $hasVariants = ($_POST['has_variants'] ?? '0') === '1';
        $variants    = json_decode($_POST['variants'] ?? '[]', true) ?: [];

        // Jede Variante: option_values [{key:color|size, value}], stock, image_url.
        // Der zusammengesetzte Name ("Farbe / Größe") ist der eindeutige
        // Variantenschlüssel und wird in inventory.size gespeichert (color leer),
        // damit Warenkorb/Kasse (die nur 'size' kennen) zuverlässig funktionieren.
        $cleanVariants = [];
        foreach ($variants as $v) {
            $color = '';
            $size  = '';
            $ovIn  = is_array($v['option_values'] ?? null) ? $v['option_values'] : [];
            foreach ($ovIn as $ov) {
                $k   = $ov['key'] ?? '';
                $val = trim((string)($ov['value'] ?? ''));
                if ($val === '') continue;
                if ($k === 'color') $color = $val;
                if ($k === 'size')  $size  = $val;
            }
            $label = trim(implode(' / ', array_filter([$color, $size])));
            if ($label === '') continue;
            if (isset($cleanVariants[$label])) continue;
            $ov = [];
            if ($color !== '') $ov[] = ['key' => 'color', 'label' => 'Farbe', 'value' => $color];
            if ($size  !== '') $ov[] = ['key' => 'size',  'label' => 'Größe', 'value' => $size];
            $cleanVariants[$label] = [
                'label'         => $label,
                'option_values' => $ov,
                'stock'         => max(0, (int)($v['stock'] ?? 0)),
                'image_url'     => secure_url(trim((string)($v['image_url'] ?? ''))),
                'is_default'    => !empty($v['is_default']),
            ];
        }
        $cleanVariants = array_values($cleanVariants);

        $useVariants = $hasVariants && !empty($cleanVariants);
        $stock       = $useVariants ? 0 : max(0, (int)($_POST['stock'] ?? 0));

        // Optionsgruppen (Farbe/Größe) IMMER aus den Varianten ableiten,
        // damit Lager und Shop nie auseinanderlaufen (Soldout-Bug).
        $optionGroups = [];
        $sizesList    = [];
        if ($useVariants) {
            $groups = [];
            foreach ($cleanVariants as $v) {
                foreach ($v['option_values'] as $ov) {
                    $k = $ov['key'];
                    if (!isset($groups[$k])) $groups[$k] = ['key' => $k, 'label' => $ov['label'], 'values' => []];
                    if (!in_array($ov['value'], $groups[$k]['values'], true)) $groups[$k]['values'][] = $ov['value'];
                }
            }
            // Reihenfolge: Farbe vor Größe
            $optionGroups = [];
            foreach (['color', 'size'] as $k) { if (isset($groups[$k])) $optionGroups[] = $groups[$k]; }
            foreach ($groups as $k => $g) { if (!in_array($k, ['color', 'size'], true)) $optionGroups[] = $g; }
            $sizesList = $groups['size']['values'] ?? [];
        }

        // Kein Produkt-Hauptbild gesetzt? Dann das Bild der Standard-Variante
        // (oder der ersten Variante mit Bild) als Hauptbild übernehmen.
        if (empty($images) && $useVariants) {
            $picked = '';
            foreach ($cleanVariants as $v) { if ($v['is_default'] && $v['image_url']) { $picked = $v['image_url']; break; } }
            if ($picked === '') { foreach ($cleanVariants as $v) { if ($v['image_url']) { $picked = $v['image_url']; break; } } }
            if ($picked !== '') $images[] = ['type' => 'url', 'src' => $picked];
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
            'sizes'            => $sizesList,
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
                    'size'                => $v['label'],   // zusammengesetzter Variantenschlüssel
                    'color'               => '',
                    'option_values'       => $v['option_values'],
                    'stock'               => $v['stock'],
                    'reserved'            => 0,
                    'min_stock'           => 3,
                    'next_delivery'       => '',
                    'notes'               => '',
                    'title'               => $v['label'],
                    'images'              => $v['image_url'] ? [['type' => 'url', 'src' => $v['image_url']]] : [],
                    'variant_price_cents' => null,
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
