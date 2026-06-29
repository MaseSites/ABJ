<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_exception_handler(function (\Throwable $e) {
    $msg = '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage()
         . ' in ' . $e->getFile() . ':' . $e->getLine();
    @file_put_contents(__DIR__ . '/../data/error.log', $msg . "\n", FILE_APPEND);
    if (!headers_sent()) http_response_code(500);
    echo '<html><body style="font-family:sans-serif;padding:2rem">'
       . '<h2>Server-Fehler</h2><pre style="background:#f5f5f5;padding:1rem">'
       . htmlspecialchars($msg) . '</pre></body></html>';
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $msg = '[' . date('Y-m-d H:i:s') . '] FATAL: ' . $err['message']
             . ' in ' . $err['file'] . ':' . $err['line'];
        @file_put_contents(__DIR__ . '/../data/error.log', $msg . "\n", FILE_APPEND);
        if (!headers_sent()) { http_response_code(500); }
        echo '<html><body style="font-family:sans-serif;padding:2rem">'
           . '<h2>Fatal Error</h2><pre style="background:#f5f5f5;padding:1rem">'
           . htmlspecialchars($msg) . '</pre></body></html>';
    }
});
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/inventory.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/order-messages.php';
require_once __DIR__ . '/account-messages.php';
require_once __DIR__ . '/newsletter.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/discounts.php';
require_once __DIR__ . '/reviews.php';
require_once __DIR__ . '/shipping.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/promo.php';
require_once __DIR__ . '/visits.php';

// HTTPS / Mixed-Content-Schutz: weist den Browser an, jede unsichere
// http://-Subressource (Bilder, Skripte, Styles, Fonts, fetch) automatisch
// auf https:// hochzustufen. Greift auch bei dynamisch per JS eingefügten
// oder im Admin gespeicherten Bild-URLs -> keine "nicht sicher"-Warnung.
if (!headers_sent()) {
    header('Content-Security-Policy: upgrade-insecure-requests');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

db(); // initialise connection & tables

// IP-Sperre (nur Shop-Bereich; Admin bleibt erreichbar, damit man entsperren kann)
// und Besucher-Log.
$__reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI !== 'cli' && strpos($__reqPath, '/admin') !== 0) {
    if (ip_is_blocked(client_ip())) {
        if (!headers_sent()) { http_response_code(403); header('Content-Type: text/html; charset=utf-8'); }
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Zugriff gesperrt</title></head>'
           . '<body style="font-family:system-ui,sans-serif;background:#0d0d12;color:#e8e8ee;display:grid;place-items:center;min-height:100vh;margin:0">'
           . '<div style="text-align:center;padding:2rem"><h1 style="margin:0 0 .5rem">Zugriff gesperrt</h1>'
           . '<p style="color:#9a9aa5">Deine IP-Adresse wurde für diese Seite gesperrt.</p></div></body></html>';
        exit;
    }
    visit_log();

    // Sicherheitsmodus: Zugang nur für freigeschaltete IP-Adressen. Wer per
    // Zugangscode reinkommt (und sich anmeldet/registriert), dessen IP wird
    // freigeschaltet. Alle anderen sehen eine neutrale Tarnseite.
    if (setting_get('security_mode') === '1' && !ip_is_allowed(client_ip())) {
        require __DIR__ . '/../partials/security-gate.php';
        exit;
    }
}
