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
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/newsletter.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/discounts.php';
require_once __DIR__ . '/reviews.php';
require_once __DIR__ . '/shipping.php';

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
