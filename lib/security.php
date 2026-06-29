<?php
// Zentrale Sicherheits-Helfer: CSRF-Schutz, Login-Bremse (Brute-Force),
// HTTPS-Erkennung und Open-Redirect-Schutz.

/** Läuft die aktuelle Anfrage über HTTPS (auch hinter einem Proxy)? */
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    if (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    return false;
}

// ──────────────────────────── CSRF ────────────────────────────

/** Liefert (und erzeugt bei Bedarf) das CSRF-Token der aktuellen Session. */
function csrf_token(): string {
    session_start_once();
    if (empty($_SESSION['csrf_token']) || !preg_match('/^[a-f0-9]{64}$/', (string)$_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verstecktes Formularfeld mit dem CSRF-Token. */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

/** Meta-Tag mit dem CSRF-Token (von fetch()/JS gelesen). */
function csrf_meta(): string {
    return '<meta name="csrf-token" content="' . h(csrf_token()) . '">';
}

/** Holt das übermittelte Token aus POST-Body oder Request-Header. */
function csrf_submitted_token(): string {
    if (isset($_POST['_csrf']) && is_string($_POST['_csrf'])) return $_POST['_csrf'];
    if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) return $_POST['csrf_token'];
    foreach (['HTTP_X_CSRF_TOKEN', 'HTTP_X_XSRF_TOKEN'] as $k) {
        if (!empty($_SERVER[$k]) && is_string($_SERVER[$k])) return $_SERVER[$k];
    }
    return '';
}

/** Stimmt das übermittelte Token mit dem der Session überein? */
function csrf_verify(): bool {
    $expected = $_SESSION['csrf_token'] ?? '';
    $given    = csrf_submitted_token();
    return is_string($expected) && $expected !== '' && $given !== '' && hash_equals($expected, $given);
}

/** Hängt automatisch ein _csrf-Feld in jedes POST-Formular des HTML-Outputs. */
function csrf_inject_forms(string $html): string {
    if (stripos($html, '<form') === false) return $html;
    $field = csrf_field();
    $out = preg_replace_callback('/<form\b[^>]*>/i', function ($m) use ($field) {
        $tag = $m[0];
        if (!preg_match('/\bmethod\s*=\s*["\']?\s*post/i', $tag)) return $tag; // nur POST-Formulare
        if (stripos($tag, 'data-no-csrf') !== false) return $tag;
        return $tag . $field;
    }, $html);
    return $out ?? $html;
}

/**
 * Globale CSRF-Prüfung für zustandsändernde Anfragen. Bricht mit 403 ab,
 * wenn kein gültiges Token vorliegt.
 */
function csrf_guard(): void {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;
    if (csrf_verify()) return;

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $wantsJson = strpos($path, '/api/') !== false
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
        || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

    if (!headers_sent()) http_response_code(403);
    if ($wantsJson) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Sicherheits-Token ungültig oder abgelaufen. Bitte Seite neu laden.']);
    } else {
        if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="de"><meta charset="utf-8"><title>Anfrage abgelehnt</title>'
           . '<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1.5rem;line-height:1.6">'
           . '<h2>Sitzung abgelaufen</h2><p>Aus Sicherheitsgründen wurde diese Anfrage abgelehnt. '
           . 'Bitte lade die Seite neu und versuche es erneut.</p></body></html>';
    }
    exit;
}

// ─────────────────── Login-Bremse (Brute-Force-Schutz) ───────────────────

/** Sind für diesen Bereich/diese IP noch Login-Versuche erlaubt? */
function login_throttle_allowed(string $scope, int $maxAttempts = 8, int $windowMinutes = 15): bool {
    try {
        $stmt = db()->prepare("SELECT COUNT(*) AS n FROM login_throttle
                               WHERE scope = ? AND ip = ? AND created_at > datetime('now', ?)");
        $stmt->execute([$scope, client_ip(), '-' . max(1, $windowMinutes) . ' minutes']);
        return (int)($stmt->fetch()['n'] ?? 0) < $maxAttempts;
    } catch (\Throwable $e) {
        return true; // im Fehlerfall nicht aussperren
    }
}

/** Verbucht einen fehlgeschlagenen Login-Versuch. */
function login_throttle_hit(string $scope): void {
    try {
        db()->prepare("INSERT INTO login_throttle (scope, ip) VALUES (?, ?)")
            ->execute([$scope, client_ip()]);
        if (mt_rand(1, 50) === 1) {
            db()->exec("DELETE FROM login_throttle WHERE created_at < datetime('now', '-1 day')");
        }
    } catch (\Throwable $e) {}
}

/** Setzt die Login-Versuche nach erfolgreichem Login zurück. */
function login_throttle_clear(string $scope): void {
    try {
        db()->prepare("DELETE FROM login_throttle WHERE scope = ? AND ip = ?")
            ->execute([$scope, client_ip()]);
    } catch (\Throwable $e) {}
}

// ─────────────────── Open-Redirect-Schutz ───────────────────

/**
 * Gibt nur seiteninterne Redirect-Ziele zurück (verhindert //evil.com,
 * http(s)://… und Backslash-Tricks). Sonst den Fallback.
 */
function safe_redirect_target(?string $target, string $fallback = '/konto.php'): string {
    $t = trim((string)$target);
    if ($t === '' || $t[0] !== '/') return $fallback;
    if (strncmp($t, '//', 2) === 0) return $fallback;          // protokoll-relativ
    if (strpos($t, '\\') !== false) return $fallback;          // Backslash-Trick
    if (preg_match('#^/+[a-z][a-z0-9+.\-]*:#i', $t)) return $fallback; // /javascript: o.ä.
    return $t;
}
