<?php
/**
 * Router für den PHP-Built-in-Server (lokale Entwicklung):
 *
 *   php -S localhost:8000 router.php
 *
 * Bildet die Rewrite-Regeln aus .htaccess / docs/nginx.conf ab, damit die
 * Seite lokal exakt wie auf dem Server funktioniert (saubere URLs, /admin, APIs).
 * In Produktion (Apache/nginx) wird diese Datei nicht benötigt.
 */

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$root = __DIR__;

// Gesperrte Verzeichnisse
if (preg_match('#^/(data|lib|partials|src|scripts|node_modules)/#', $uri) || preg_match('#/\.#', $uri)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Statische Assets aus public/
if (preg_match('#^/(css|js|img|assets|uploads)/(.+)$#', $uri, $m)) {
    $file = "$root/public/{$m[1]}/{$m[2]}";
    if (is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $types = [
            'css' => 'text/css', 'js' => 'application/javascript', 'mjs' => 'application/javascript',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2',
        ];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        readfile($file);
        return true;
    }
    // Fallback: img/ auch im Projekt-Root erlauben (Logo etc.)
    $file = "$root/{$m[1]}/{$m[2]}";
    if (is_file($file)) return false;
    http_response_code(404);
    return true;
}

// Saubere URLs -> PHP-Dateien
$routes = [
    '#^/$#'                          => '/index.php',
    '#^/shop/?$#'                    => '/shop.php',
    '#^/warenkorb/?$#'               => '/warenkorb.php',
    '#^/kasse/?$#'                   => '/kasse.php',
    '#^/kontakt/?$#'                 => '/kontakt.php',
    '#^/impressum/?$#'               => '/impressum.php',
    '#^/datenschutz/?$#'             => '/datenschutz.php',
    '#^/agb/?$#'                     => '/agb.php',
    '#^/widerruf/?$#'                => '/widerruf.php',
    '#^/wunschliste/?$#'             => '/wunschliste.php',
    '#^/newsletter/?$#'              => '/newsletter.php',
    '#^/bestellung/?$#'              => '/bestellung.php',
    '#^/warenkorb/api/add$#'         => '/api/cart-add.php',
    '#^/warenkorb/api/state$#'       => '/api/cart-state.php',
    '#^/warenkorb/api/update$#'      => '/api/cart-update.php',
    '#^/api/cart-update$#'           => '/api/cart-update.php',
    '#^/api/checkout$#'              => '/api/checkout.php',
    '#^/api/stripe-webhook$#'        => '/api/stripe-webhook.php',
    '#^/api/produkte$#'              => '/api/produkte.php',
    '#^/api/review$#'                => '/api/review.php',
    '#^/api/discount-check$#'        => '/api/discount-check.php',
    '#^/admin/?$#'                   => '/admin/index.php',
    '#^/admin/login/?$#'             => '/admin/login.php',
    '#^/admin/logout/?$#'            => '/admin/logout.php',
    '#^/admin/produkte/?$#'          => '/admin/produkte.php',
    '#^/admin/bestellungen/?$#'      => '/admin/bestellungen.php',
    '#^/admin/lager/?$#'             => '/admin/lager.php',
    '#^/admin/nachrichten/?$#'       => '/admin/nachrichten.php',
    '#^/admin/newsletter/?$#'        => '/admin/newsletter.php',
    '#^/admin/einstellungen/?$#'     => '/admin/einstellungen.php',
    '#^/admin/rabatte/?$#'           => '/admin/rabatte.php',
    '#^/admin/analytics/?$#'         => '/admin/analytics.php',
    '#^/admin/bewertungen/?$#'       => '/admin/bewertungen.php',
    '#^/admin/kunden/?$#'            => '/admin/kunden.php',
    '#^/admin/api/products$#'        => '/admin/api/products.php',
    '#^/admin/api/upload$#'          => '/admin/api/upload.php',
    '#^/admin/api/stock-adjust$#'    => '/admin/api/stock-adjust.php',
    '#^/admin/api/inventory-save$#'  => '/admin/api/inventory-save.php',
];
foreach ($routes as $pattern => $target) {
    if (preg_match($pattern, $uri)) {
        require $root . $target;
        return true;
    }
}

// Dynamische Routen mit Parametern
if (preg_match('#^/produkt/([^/]+)/?$#', $uri, $m)) {
    $_GET['slug'] = urldecode($m[1]);
    require "$root/produkt.php";
    return true;
}
if (preg_match('#^/bestellung/([^/]+)/?$#', $uri, $m)) {
    $_GET['ref'] = urldecode($m[1]);
    require "$root/bestellung.php";
    return true;
}
if (preg_match('#^/admin/produkt/neu/?$#', $uri)) {
    require "$root/admin/produkt-edit.php";
    return true;
}
if (preg_match('#^/admin/produkt/([0-9]+)/?$#', $uri, $m)) {
    $_GET['id'] = $m[1];
    require "$root/admin/produkt-edit.php";
    return true;
}
if (preg_match('#^/admin/bestellung/([^/]+)/?$#', $uri, $m)) {
    $_GET['ref'] = urldecode($m[1]);
    require "$root/admin/bestellung.php";
    return true;
}
if (preg_match('#^/admin/lager/([0-9]+)/?$#', $uri, $m)) {
    $_GET['id'] = $m[1];
    require "$root/admin/lager-edit.php";
    return true;
}
if (preg_match('#^/admin/api/products/([0-9]+)$#', $uri, $m)) {
    $_GET['id'] = $m[1];
    require "$root/admin/api/products.php";
    return true;
}

// Existierende PHP-Datei oder statische Datei direkt ausliefern
if (is_file($root . $uri)) {
    return false; // Built-in-Server übernimmt
}

// 404
http_response_code(404);
require "$root/404.php";
return true;
