<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
// Fehler werden protokolliert, dem Besucher aber nur generisch gemeldet
// (keine Dateipfade/Stacktraces nach aussen -> kein Information-Leak).
function _abj_render_error_page(): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Server-Fehler</title>'
       . '<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1.5rem;line-height:1.6">'
       . '<h2>Es ist ein Fehler aufgetreten</h2>'
       . '<p>Bitte versuche es in einem Moment erneut. Falls das Problem bestehen bleibt, melde dich bei uns.</p>'
       . '</body></html>';
}
set_exception_handler(function (\Throwable $e) {
    $msg = '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage()
         . ' in ' . $e->getFile() . ':' . $e->getLine();
    @file_put_contents(__DIR__ . '/../data/error.log', $msg . "\n", FILE_APPEND);
    _abj_render_error_page();
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $msg = '[' . date('Y-m-d H:i:s') . '] FATAL: ' . $err['message']
             . ' in ' . $err['file'] . ':' . $err['line'];
        @file_put_contents(__DIR__ . '/../data/error.log', $msg . "\n", FILE_APPEND);
        _abj_render_error_page();
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
require_once __DIR__ . '/requests.php';
require_once __DIR__ . '/account-messages.php';
require_once __DIR__ . '/newsletter.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/discounts.php';
require_once __DIR__ . '/reviews.php';
require_once __DIR__ . '/shipping.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/promo.php';
require_once __DIR__ . '/visits.php';
require_once __DIR__ . '/notfound.php';

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

// Admin-Passwort aus .env durchsetzen, falls dort gesetzt (sonst keine Änderung).
admin_apply_env_password('admin_user_root', 'ADMIN_ROOT_PASSWORD');
admin_apply_env_password('admin_user_lookup', 'ADMIN_LOOKUP_PASSWORD');

// IP-Sperre (nur Shop-Bereich; Admin bleibt erreichbar, damit man entsperren kann)
// und Besucher-Log.
$__reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Verdächtige Scanner (z.B. /passkey, /.env, /wp-admin, Backup-/Secret-Dateien)
// sofort sperren – ausser bei Admins, freigeschalteten IPs oder den Inhabern.
if (PHP_SAPI !== 'cli') {
    security_autoban_guard($__reqPath);
}

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

    // Getarnter 404: Shop je nach Einstellung für alle oder nur für
    // ausgewählte Besucher (IP/Konto) als "Seite nicht gefunden" ausblenden.
    notfound_guard();

}
