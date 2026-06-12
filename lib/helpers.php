<?php
function format_price(int $cents, string $currency = 'CHF'): string {
    if ($currency === 'CHF') {
        $val = number_format($cents / 100, 2, '.', "'");
        return 'CHF&nbsp;' . $val;
    }
    $val = number_format($cents / 100, 2, ',', '.');
    return $val . '&nbsp;&euro;';
}

function safe_parse(string $json, $fallback = []) {
    $v = json_decode($json, true);
    return $v !== null ? $v : $fallback;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $name): string {
    $s = mb_strtolower($name);
    $s = str_replace(['ä','ö','ü','ß'], ['ae','oe','ue','ss'], $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return substr($s, 0, 60) ?: 'produkt';
}

function unique_slug(string $name, ?int $excludeId = null): string {
    $base = slugify($name);
    $candidate = $base;
    $i = 2;
    while (true) {
        $stmt = db()->prepare('SELECT id FROM products WHERE slug = ? AND (? IS NULL OR id != ?)');
        $stmt->execute([$candidate, $excludeId, $excludeId]);
        if (!$stmt->fetch()) break;
        $candidate = "$base-$i";
        $i++;
    }
    return $candidate;
}

function nano_id(int $len = 8): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $result = '';
    for ($i = 0; $i < $len; $i++) {
        $result .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $result;
}

function discount_percent(int $priceCents, int $salePriceCents): int {
    if (!$priceCents || !$salePriceCents) return 0;
    return (int) round((1 - $salePriceCents / $priceCents) * 100);
}

function placeholder_svg(string $name): string {
    $n = strtoupper(mb_substr(trim($name) ?: '?', 0, 1));
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='750'><rect width='600' height='750' fill='%2316161a'/><text x='300' y='430' font-size='300' font-weight='800' fill='%23a5b4fc' text-anchor='middle' font-family='Arial'>$n</text></svg>";
    return 'data:image/svg+xml,' . $svg;
}

/**
 * Basis-Pfad der Installation (leer bei Root-Deployment, z.B. "/sub/shop"
 * wenn die Seite in einem Unterordner liegt). Macht alle Links/Redirects
 * unabhängig vom Server-Setup.
 */
function base_path(): string {
    static $base = null;
    if ($base !== null) return $base;
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    // dirname() liefert auf Windows einen Backslash -> erneut normalisieren
    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    // Skripte liegen im Root, in /admin, /admin/api oder /api
    $dir = preg_replace('#/(admin/api|admin|api)$#', '', $dir);
    if ($dir === '' || $dir === '/' || $dir === '.') $dir = '';
    $base = $dir;
    return $base;
}

function url(string $path): string {
    return base_path() . $path;
}

/**
 * Stuft eine vom Nutzer eingegebene URL von http:// auf https:// hoch,
 * damit keine Mixed-Content-Warnung entsteht. Relative Pfade, data:- und
 * protokoll-relative URLs bleiben unverändert.
 */
function secure_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (stripos($url, 'http://') === 0) {
        return 'https://' . substr($url, 7);
    }
    return $url;
}

function redirect(string $url): void {
    // Absolute Site-Pfade automatisch um den Basis-Pfad ergänzen
    if ($url !== '' && $url[0] === '/' && strpos($url, '//') !== 0) {
        $url = base_path() . $url;
    }
    header('Location: ' . $url);
    exit;
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function current_path(): string {
    $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return rtrim($p ?: '/', '/') ?: '/';
}

function str_has(string $haystack, string $needle): bool {
    return strpos($haystack, $needle) !== false;
}
