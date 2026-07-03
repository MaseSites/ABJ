<?php
/**
 * Getarnter 404 ("Seite existiert nicht").
 *
 * Anders als die IP-Sperre ("Zugriff gesperrt") oder die Tarnseite des
 * Sicherheitsmodus zeigt dieser Schalter eine komplett neutrale, weisse
 * "Seite nicht gefunden"-Antwort mit HTTP-Status 404 - als gäbe es die
 * Seite gar nicht. Kein Shop-Layout, kein Branding, keine Hinweise.
 *
 * Zwei Modi (im Admin unter Sicherheit einstellbar):
 *   'all'      - jeder Besucher sieht den 404 (Shop ist "verschwunden").
 *   'selected' - nur die hinterlegten IP-Adressen bzw. Konten (per E-Mail)
 *                sehen den 404; alle anderen sehen den Shop ganz normal.
 *
 * Der Admin-Bereich ist nie betroffen, und angemeldete Admins sehen auch im
 * Shop immer die echte Seite - sonst könnte man den Schalter nicht mehr
 * bedienen. Aufgerufen wird das Ganze in bootstrap.php nur für Shop-Seiten.
 */

/** E-Mail-/IP-Liste aus einer Einstellung (eine pro Zeile oder komma-getrennt). */
function notfound_parse_list(string $key): array {
    $raw = (string)(setting_get($key) ?? '');
    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out;
}

/** IP-Liste, gegen die im Modus 'selected' geprüft wird. */
function notfound_ip_list(): array {
    return notfound_parse_list('notfound_ips');
}

/** Konten-Liste (E-Mail-Adressen, klein geschrieben) für Modus 'selected'. */
function notfound_account_list(): array {
    return array_map('strtolower', notfound_parse_list('notfound_accounts'));
}

/** Trifft der aktuelle Besucher auf die "ausgewählt"-Liste zu? */
function notfound_target_matches(): bool {
    // 1) IP-Treffer
    if (in_array(client_ip(), notfound_ip_list(), true)) return true;
    // 2) Konto-Treffer (angemeldeter Kunde, Abgleich per E-Mail)
    if (function_exists('is_customer') && is_customer()) {
        $email = strtolower(trim((string)(current_customer()['email'] ?? '')));
        if ($email !== '' && in_array($email, notfound_account_list(), true)) return true;
    }
    return false;
}

/** Gibt eine neutrale, weisse 404-Seite aus und beendet die Anfrage. */
function notfound_render_and_exit(): void {
    if (!headers_sent()) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex,nofollow"><title>404 &ndash; Seite nicht gefunden</title>'
       . '<style>html,body{height:100%}body{margin:0;background:#fff;color:#111;'
       . 'font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;'
       . 'display:flex;align-items:center;justify-content:center;text-align:center;padding:1.5rem}'
       . 'h1{font-size:1.6rem;font-weight:600;margin:0 0 .4rem}'
       . 'p{margin:0;color:#555;font-size:.95rem}</style></head>'
       . '<body><div><h1>404 &ndash; Seite nicht gefunden</h1>'
       . '<p>Die angeforderte Seite wurde nicht gefunden.</p></div></body></html>';
    exit;
}

/**
 * Zentrale Prüfung: blendet den Shop je nach eingestelltem Modus als 404 aus.
 * Wird in bootstrap.php für Shop-Seiten (nicht /admin) aufgerufen.
 */
function notfound_guard(): void {
    $mode = setting_get('notfound_mode') ?: '0';
    if ($mode === '0') return;
    // Admins sehen den Shop immer - damit der Schalter bedienbar bleibt.
    if (function_exists('is_admin') && is_admin()) return;

    if ($mode === 'all') {
        notfound_render_and_exit();
    }
    if ($mode === 'selected' && notfound_target_matches()) {
        notfound_render_and_exit();
    }
}
