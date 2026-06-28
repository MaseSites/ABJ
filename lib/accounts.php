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
    return db()->query('SELECT id, email, name, access_code, created_at FROM accounts ORDER BY created_at DESC')->fetchAll();
}

// ---- Zugangscodes (Pool; einmalig, beim ersten Login an ein Konto gebunden) ----
function code_find(string $code): ?array {
    $code = trim($code);
    if ($code === '') return null;
    $stmt = db()->prepare("SELECT * FROM access_codes WHERE code = ?");
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Neuen, freien Zugangscode erzeugen und speichern. Gibt den Code zurück. */
function code_generate(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    } while (code_find($code));
    db()->prepare("INSERT INTO access_codes (code) VALUES (?)")->execute([$code]);
    return $code;
}

/** Freien Code an ein Konto binden (nur wenn noch frei). */
function code_bind(string $code, int $accountId): void {
    db()->prepare("UPDATE access_codes SET account_id = ?, used_at = datetime('now') WHERE code = ? AND account_id IS NULL")
       ->execute([$accountId, trim($code)]);
}

function code_delete(string $code): void {
    db()->prepare("DELETE FROM access_codes WHERE code = ?")->execute([trim($code)]);
}

function codes_list(): array {
    return db()->query("SELECT ac.*, a.email AS account_email, a.name AS account_name
        FROM access_codes ac LEFT JOIN accounts a ON a.id = ac.account_id
        ORDER BY ac.created_at DESC")->fetchAll();
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
 */
function account_create(string $email, string $password, string $name): array {
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
    db()->prepare('INSERT INTO accounts (email, password_hash, name) VALUES (?, ?, ?)')
       ->execute([$email, $hash, mb_substr($name, 0, 120)]);
    return ['ok' => true, 'id' => (int)db()->lastInsertId()];
}

function account_verify_login(string $email, string $password): ?array {
    $acc = account_by_email($email);
    if ($acc && password_verify($password, $acc['password_hash'])) return $acc;
    return null;
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
