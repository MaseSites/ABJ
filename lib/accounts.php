<?php
// Kundenkonten: Registrierung, Login, Session (getrennt vom Admin-Login)

function account_by_email(string $email): ?array {
    $stmt = db()->prepare('SELECT * FROM accounts WHERE lower(email) = lower(?)');
    $stmt->execute([trim($email)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function account_by_id(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM accounts WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Alle registrierten Kundenkonten (neueste zuerst). */
function accounts_list(): array {
    return db()->query('SELECT id, email, name, access_code, activated, created_at FROM accounts ORDER BY created_at DESC')->fetchAll();
}

/** Ist das Konto freigeschaltet? Fehlende Spalte/Null gilt als freigeschaltet. */
function account_is_activated(?array $acc): bool {
    if (!$acc) return true;
    return !array_key_exists('activated', $acc) || (int)$acc['activated'] === 1;
}

/**
 * Schaltet ein eingeschränktes Konto frei: markiert es als aktiviert und lässt
 * alle zurückgehaltenen Bestellungen "einlaufen" (held=0, wieder als neu markiert),
 * damit der Admin sie im Dashboard sieht. Gibt true zurück, wenn etwas geändert wurde.
 */
function account_activate(int $id): bool {
    $acc = account_by_id($id);
    if (!$acc || account_is_activated($acc)) return false;
    db()->prepare('UPDATE accounts SET activated = 1 WHERE id = ?')->execute([$id]);
    // Zurückgehaltene Bestellungen dieses Kontos freigeben und als neu markieren.
    try {
        db()->prepare("UPDATE orders SET held = 0, is_seen = 0, updated_at = datetime('now')
                       WHERE held = 1 AND lower(email) = lower(?)")->execute([$acc['email']]);
    } catch (\Throwable $e) { /* Spalten evtl. noch nicht vorhanden */ }
    account_message_create([
        'account_id' => $id,
        'sender_role' => 'system',
        'subject' => 'Konto aktiviert ✅',
        'body' => "Dein Konto ist jetzt freigeschaltet! Alle Funktionen stehen dir voll zur Verfügung und deine Bestellungen werden nun bearbeitet.",
        'is_read' => 0,
    ]);
    return true;
}

/**
 * Versucht, ein Konto mit einem (Aktivierungs-/Promo-)Code freizuschalten.
 * Rückgabe: ['ok'=>bool, 'error'=>?].
 */
function account_activate_with_code(int $id, string $code): array {
    $code = trim($code);
    if ($code === '') return ['ok' => false, 'error' => 'Bitte gib einen Code ein.'];
    $row = code_find($code);
    if (!code_is_usable($row)) {
        return ['ok' => false, 'error' => 'Dieser Code ist ungültig oder wurde bereits verwendet.'];
    }
    code_mark_used($code, $id);
    $owner = promo_owner_of_code($code);
    if ($owner) account_set_referrer($id, $owner);
    account_activate($id);
    return ['ok' => true];
}

// ---- Zugangs-/Promo-Codes: EIN System (Tabelle promo_codes) ----
// account_id = 0  -> vom Admin erstellter Code (kein Werber)
// account_id > 0  -> Promo-Code eines Kunden (Werber bekommt Punkte)
function code_find(string $code): ?array {
    $code = trim($code);
    if ($code === '') return null;
    $stmt = db()->prepare("SELECT * FROM promo_codes WHERE upper(code) = upper(?)");
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Vom Admin erstellten Code (ohne Werber) erzeugen. */
function code_generate(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    } while (code_find($code));
    db()->prepare("INSERT INTO promo_codes (account_id, code) VALUES (0, ?)")->execute([$code]);
    return $code;
}

function code_delete(string $code): void {
    db()->prepare("DELETE FROM promo_codes WHERE upper(code) = upper(?)")->execute([trim($code)]);
}

/** Ist der Code noch frei (einmal verwendbar, noch nicht eingelöst)? */
function code_is_usable(?array $row): bool {
    return $row !== null && empty($row['used_by']);
}

/** Markiert einen Code als verwendet (durch Konto $userId). Nur falls noch frei. */
function code_mark_used(string $code, int $userId): void {
    db()->prepare("UPDATE promo_codes SET used_by = ?, used_at = datetime('now')
                   WHERE upper(code) = upper(?) AND used_by IS NULL")
       ->execute([$userId, trim($code)]);
}

function accounts_count(): int {
    return (int)db()->query('SELECT COUNT(*) AS n FROM accounts')->fetch()['n'];
}

/** Kundenkonto löschen. Bestellungen bleiben als Historie erhalten. */
function account_delete(int $id): bool {
    $stmt = db()->prepare('DELETE FROM accounts WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

/**
 * Legt ein Konto an. Rückgabe: ['ok'=>bool, 'error'=>?, 'id'=>?].
 * $activated=false legt ein eingeschränktes Konto an (muss noch aktiviert werden).
 */
function account_create(string $email, string $password, string $name, bool $activated = true): array {
    $email = trim($email);
    $name  = trim($name);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Bitte gib eine gültige E-Mail-Adresse ein.'];
    }
    if (mb_strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Das Passwort muss mindestens 8 Zeichen lang sein.'];
    }
    if (account_by_email($email)) {
        return ['ok' => false, 'error' => 'Für diese E-Mail existiert bereits ein Konto. Bitte melde dich an.'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    db()->prepare('INSERT INTO accounts (email, password_hash, name, activated) VALUES (?, ?, ?, ?)')
       ->execute([$email, $hash, mb_substr($name, 0, 120), $activated ? 1 : 0]);
    $id = (int)db()->lastInsertId();
    account_message_create([
        'account_id' => $id,
        'sender_role' => 'system',
        'subject' => 'Herzlich willkommen bei ABJ 🎉',
        'body' => "Schön, dass du da bist! Dein Konto ist startklar.\n\nStöbere in Ruhe durch unsere Produkte - und falls du etwas Bestimmtes suchst, das du nicht findest, stell einfach eine Produktanfrage. Wir freuen uns auf dich!",
        'is_read' => 0,
    ]);
    return ['ok' => true, 'id' => $id];
}

/**
 * Setzt das Admin-Passwort aus einer Umgebungsvariable/.env durch, FALLS gesetzt.
 * So ist das Passwort ausserhalb des Codes ablegbar. Aus Performancegründen wird
 * höchstens einmal pro Stunde geprüft (bcrypt ist teuer). Ist die Variable leer,
 * passiert nichts - die bestehende Anmeldung bleibt unverändert.
 */
function admin_apply_env_password(string $username, string $envKey): void {
    $pw = (string)(function_exists('env_get') ? env_get($envKey) : getenv($envKey));
    if ($pw === '') return;
    $ckKey = 'admin_envpw_check_' . $username;
    if (time() - (int)(setting_get($ckKey) ?: 0) < 3600) return; // max. 1x/Stunde
    setting_set($ckKey, (string)time());
    try {
        $s = db()->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $s->execute([$username]);
        $row = $s->fetch();
        if ($row && password_verify($pw, $row['password_hash'])) return; // schon aktuell
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        if ($row) {
            db()->prepare("UPDATE users SET password_hash = ? WHERE username = ?")->execute([$hash, $username]);
        } else {
            $role = $username === 'admin_user_lookup' ? 'lookup' : 'root';
            db()->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")->execute([$username, $hash, $role]);
        }
    } catch (\Throwable $e) { /* nie die Seite blockieren */ }
}

function account_verify_login(string $email, string $password): ?array {
    $acc = account_by_email($email);
    if ($acc && password_verify($password, $acc['password_hash'])) return $acc;
    return null;
}

/** Setzt, von wem dieses Konto geworben wurde (Promo-Programm). */
function account_set_referrer(int $id, int $referrerId): void {
    if ($referrerId <= 0 || $referrerId === $id) return;
    db()->prepare('UPDATE accounts SET referred_by = ? WHERE id = ? AND referred_by IS NULL')
       ->execute([$referrerId, $id]);
}

function account_update_password(int $id, string $newPassword): bool {
    if (mb_strlen($newPassword) < 8) return false;
    db()->prepare('UPDATE accounts SET password_hash = ? WHERE id = ?')
       ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    return true;
}

/** Profil (Name, Telefon, Standard-Lieferadresse) aktualisieren. */
function account_update_profile(int $id, string $name, string $phone, array $address): void {
    db()->prepare('UPDATE accounts SET name = ?, phone = ?, address = ? WHERE id = ?')
       ->execute([mb_substr(trim($name), 0, 120), mb_substr(trim($phone), 0, 40), json_encode($address), $id]);
}

/** Gespeicherte Standard-Adresse eines Kontos als Array (oder leer). */
function account_address(?array $account): array {
    if (!$account || empty($account['address'])) return [];
    $a = json_decode($account['address'], true);
    return is_array($a) ? $a : [];
}

// ---- Session ----
function customer_login(int $id, string $email, string $name): void {
    session_start_once();
    try { session_regenerate_id(true); } catch (\Throwable $e) {}
    $_SESSION['customer'] = ['id' => $id, 'email' => $email, 'name' => $name];
    session_write_close();
}

function customer_logout(): void {
    session_start_once();
    unset($_SESSION['customer']);
}

function is_customer(): bool {
    session_start_once();
    return !empty($_SESSION['customer']['id']);
}

function current_customer(): ?array {
    session_start_once();
    return $_SESSION['customer'] ?? null;
}

function require_customer(): void {
    if (!is_customer()) redirect('/anmelden.php?weiter=' . urlencode($_SERVER['REQUEST_URI'] ?? '/konto.php'));
}
