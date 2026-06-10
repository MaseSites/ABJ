<?php
function format_price(int $cents, string $currency = 'EUR'): string {
    $symbol = $currency === 'CHF' ? 'CHF ' : '€';
    $val = number_format($cents / 100, 2, ',', '.');
    return $currency === 'CHF' ? "$symbol$val" : "$val $symbol";
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

function redirect(string $url): void {
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
