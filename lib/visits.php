<?php
// Besucher-Log (IP, Zeit, Seite) + IP-Sperre

/** Client-IP ermitteln (hinter Proxy: erster X-Forwarded-For-Eintrag). */
function client_ip(): string {
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** Aktuellen Seitenaufruf protokollieren (nur Shop-Seiten, keine Assets/API/Admin). */
function visit_log(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (preg_match('#^/(admin|api)\b#', $path)) return;
    if (preg_match('#\.(css|js|mjs|map|png|jpe?g|webp|gif|svg|ico|woff2?|ttf|json|txt)$#i', $path)) return;
    if (preg_match('#^/(css|js|img|assets|uploads)/#', $path)) return;
    $accId = is_customer() ? (int)(current_customer()['id'] ?? 0) : 0;
    try {
        db()->prepare("INSERT INTO visits (ip, path, user_agent, account_id) VALUES (?, ?, ?, ?)")
            ->execute([client_ip(), mb_substr($path, 0, 300), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300), $accId]);
        // gelegentlich alte Einträge aufräumen
        if (mt_rand(1, 80) === 1) {
            db()->exec("DELETE FROM visits WHERE created_at < datetime('now', '-60 days')");
        }
    } catch (\Throwable $e) { /* Logging darf die Seite nie blockieren */ }
}

// ──────────────── Auto-Sperre verdächtiger Scanner ────────────────

/**
 * Vertrauenswürdiger Besucher? Admins, freigeschaltete IPs und die bekannten
 * Inhaber-Konten (Loma, Camillo, Lewis) werden NIE automatisch gesperrt.
 */
function security_is_trusted_visitor(): bool {
    if (function_exists('is_admin') && is_admin()) return true;
    if (ip_is_allowed(client_ip())) return true;
    if (function_exists('is_customer') && is_customer()) {
        $name = strtolower(trim((string)(current_customer()['name'] ?? '')));
        if ($name !== '') {
            foreach (['loma', 'camillo', 'lewis'] as $t) {
                if (strpos($name, $t) !== false) return true;
            }
        }
    }
    return false;
}

/** Sieht der Pfad nach einem Hack-/Scanner-Versuch aus? (nie eine echte Shop-URL) */
function security_path_is_malicious(string $path): bool {
    $p = strtolower(rawurldecode($path));
    // Pfad-Traversal / Nullbyte
    if (strpos($p, '..') !== false || strpos($p, "\0") !== false || strpos($p, '%00') !== false) return true;
    // Bekannte Angriffs-/Scan-Pfade (kommen in diesem Shop niemals legitim vor)
    static $needles = [
        'passkey', 'passwd', '.env', '.git', '.svn', '.hg', '.ssh', 'id_rsa', 'id_dsa', '.aws', '.npmrc',
        'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'wordpress', 'wp-json', 'xmlrpc.php', 'wlwmanifest',
        'phpmyadmin', 'phpadmin', 'mysqladmin', 'adminer', 'dbadmin', '/pma/', 'administrator/',
        '.htpasswd', '.htaccess', 'eval-stdin', 'phpunit', 'phpinfo', 'base64_', 'allow_url',
        'webshell', 'c99.php', 'r57.php', 'wso.php', 'alfa.php', 'cgi-bin', 'shell.php', 'cmd.php',
        'autodiscover', '/owa/', '/actuator', 'solr/', 'jenkins', 'struts2',
        'config.php', 'configuration.php', 'config.json', 'web.config', 'database.yml', 'settings.py',
        'etc/passwd', '/proc/self', 'vendor/phpunit', 'sftp-config', 'credentials', 'aws/credentials',
    ];
    foreach ($needles as $n) { if (strpos($p, $n) !== false) return true; }
    // Verdächtige Dateiendungen (Backups, Dumps, Secrets, fremde Skripte)
    if (preg_match('#\.(env|git|sql|sqlite|db|bak|old|save|swp|swo|ini|sh|log|zip|tar|tgz|gz|rar|7z|dump|pem|key|crt|p12|pfx|conf|cfg|yml|yaml|asp|aspx|jsp|cgi|exe|dll)(\?|$)#', $p)) return true;
    return false;
}

/**
 * Sperrt die IP sofort, wenn ein verdächtiger Pfad von einem nicht
 * vertrauenswürdigen Besucher aufgerufen wird, und zeigt die Sperr-Seite.
 */
function security_autoban_guard(string $path): void {
    if (!security_path_is_malicious($path)) return;     // zuerst billig prüfen (keine Session)
    if (security_is_trusted_visitor()) return;
    $ip = client_ip();
    if (!ip_is_blocked($ip)) {
        ip_block($ip, 'Auto-Sperre: verdächtiger Zugriff auf ' . mb_substr($path, 0, 150));
    }
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Zugriff gesperrt</title></head>'
       . '<body style="font-family:system-ui,sans-serif;background:#0d0d12;color:#e8e8ee;display:grid;place-items:center;min-height:100vh;margin:0">'
       . '<div style="text-align:center;padding:2rem;max-width:420px"><h1 style="margin:0 0 .5rem">Zugriff gesperrt</h1>'
       . '<p style="color:#9a9aa5">Verdächtige Aktivität erkannt. Deine IP-Adresse wurde gesperrt.</p></div></body></html>';
    exit;
}

function ip_is_blocked(string $ip): bool {
    if ($ip === '') return false;
    try {
        $stmt = db()->prepare("SELECT 1 FROM ip_blocks WHERE ip = ?");
        $stmt->execute([$ip]);
        return (bool)$stmt->fetch();
    } catch (\Throwable $e) { return false; }
}

function ip_block(string $ip, string $note = ''): bool {
    $ip = trim($ip);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    try {
        db()->prepare("INSERT OR IGNORE INTO ip_blocks (ip, note) VALUES (?, ?)")
            ->execute([$ip, mb_substr(trim($note), 0, 200)]);
        return true;
    } catch (\Throwable $e) { return false; }
}

function ip_unblock(string $ip): void {
    try { db()->prepare("DELETE FROM ip_blocks WHERE ip = ?")->execute([trim($ip)]); } catch (\Throwable $e) {}
}

function ip_blocks_list(): array {
    try { return db()->query("SELECT * FROM ip_blocks ORDER BY created_at DESC")->fetchAll(); }
    catch (\Throwable $e) { return []; }
}

// ---- Sicherheitsmodus: IP-Freischaltung (Allowlist) ----
function ip_is_allowed(string $ip): bool {
    if ($ip === '') return false;
    try {
        $stmt = db()->prepare("SELECT 1 FROM ip_allow WHERE ip = ?");
        $stmt->execute([$ip]);
        return (bool)$stmt->fetch();
    } catch (\Throwable $e) { return false; }
}

function ip_allow_add(string $ip, int $accountId = 0): bool {
    $ip = trim($ip);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    try {
        db()->prepare("INSERT OR IGNORE INTO ip_allow (ip, account_id) VALUES (?, ?)")->execute([$ip, $accountId]);
        // Falls die IP schon frei war, aber noch keinem Konto zugeordnet: nachtragen.
        if ($accountId > 0) {
            db()->prepare("UPDATE ip_allow SET account_id = ? WHERE ip = ? AND (account_id IS NULL OR account_id = 0)")
                ->execute([$accountId, $ip]);
        }
        return true;
    } catch (\Throwable $e) { return false; }
}

function ip_allow_remove(string $ip): void {
    try { db()->prepare("DELETE FROM ip_allow WHERE ip = ?")->execute([trim($ip)]); } catch (\Throwable $e) {}
}

function ip_allow_list(): array {
    try { return db()->query("SELECT * FROM ip_allow ORDER BY created_at DESC")->fetchAll(); }
    catch (\Throwable $e) { return []; }
}

/** Letzte Seitenaufrufe (einzeln). */
function visits_recent(int $limit = 60): array {
    try {
        $stmt = db()->prepare("SELECT * FROM visits ORDER BY id DESC LIMIT ?");
        $stmt->execute([max(1, $limit)]);
        return $stmt->fetchAll();
    } catch (\Throwable $e) { return []; }
}

/**
 * Ordnet IP-Adressen einem Nutzer zu. Rückgabe: ['1.2.3.4' => ['name'=>, 'email'=>], …].
 * Quelle: freigeschaltete IPs (ip_allow.account_id), sonst der letzte
 * eingeloggte Besuch dieser IP (visits.account_id).
 */
function ip_user_map(array $ips): array {
    $ips = array_values(array_unique(array_filter(array_map('trim', $ips))));
    if (!$ips) return [];
    $map = [];
    try {
        $ph = implode(',', array_fill(0, count($ips), '?'));
        // 1) aus der Allowlist (direkte Zuordnung beim Freischalten)
        $stmt = db()->prepare("SELECT al.ip, a.name, a.email FROM ip_allow al
            JOIN accounts a ON a.id = al.account_id
            WHERE al.account_id > 0 AND al.ip IN ($ph)");
        $stmt->execute($ips);
        foreach ($stmt->fetchAll() as $r) $map[$r['ip']] = ['name' => $r['name'], 'email' => $r['email']];

        // 2) für den Rest: letzter eingeloggter Besuch dieser IP
        $rest = array_values(array_diff($ips, array_keys($map)));
        if ($rest) {
            $ph2 = implode(',', array_fill(0, count($rest), '?'));
            $stmt = db()->prepare("SELECT v.ip, a.name, a.email FROM visits v
                JOIN accounts a ON a.id = v.account_id
                WHERE v.account_id > 0 AND v.ip IN ($ph2)
                  AND v.id IN (SELECT MAX(id) FROM visits WHERE account_id > 0 GROUP BY ip)");
            $stmt->execute($rest);
            foreach ($stmt->fetchAll() as $r) {
                if (!isset($map[$r['ip']])) $map[$r['ip']] = ['name' => $r['name'], 'email' => $r['email']];
            }
        }
    } catch (\Throwable $e) { return $map; }
    return $map;
}

/** Pro IP zusammengefasst: Anzahl, erste/letzte Aktivität. */
function visits_ip_summary(int $limit = 60): array {
    try {
        $limit = max(1, (int)$limit);
        return db()->query("SELECT ip, COUNT(*) AS hits, MAX(created_at) AS last_seen, MIN(created_at) AS first_seen,
                (SELECT path FROM visits v2 WHERE v2.ip = v.ip ORDER BY v2.id DESC LIMIT 1) AS last_path
            FROM visits v GROUP BY ip ORDER BY last_seen DESC LIMIT $limit")->fetchAll();
    } catch (\Throwable $e) { return []; }
}
