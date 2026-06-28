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
    try {
        db()->prepare("INSERT INTO visits (ip, path, user_agent) VALUES (?, ?, ?)")
            ->execute([client_ip(), mb_substr($path, 0, 300), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300)]);
        // gelegentlich alte Einträge aufräumen
        if (mt_rand(1, 80) === 1) {
            db()->exec("DELETE FROM visits WHERE created_at < datetime('now', '-60 days')");
        }
    } catch (\Throwable $e) { /* Logging darf die Seite nie blockieren */ }
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

/** Letzte Seitenaufrufe (einzeln). */
function visits_recent(int $limit = 60): array {
    try {
        $stmt = db()->prepare("SELECT * FROM visits ORDER BY id DESC LIMIT ?");
        $stmt->execute([max(1, $limit)]);
        return $stmt->fetchAll();
    } catch (\Throwable $e) { return []; }
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
