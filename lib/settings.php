<?php
$SETTINGS_DEFAULTS = [
    'shop_name'       => 'ABJ Store',
    'tagline'         => 'Authentifizierte Designer- & Streetwear-Pieces',
    'currency'        => 'CHF',
    'hero_title'      => 'Premium Streetwear & Designer',
    'hero_subtitle'   => 'Kuratierte, authentifizierte Pieces der gefragtesten Marken – sicher geliefert in ganz Europa.',
    'sale_ends_at'    => '',
    'members_count'   => '20000',
    'ratings_count'   => '1000',
    'contact_email'   => 'kontakt@example.com',
    'announcement'    => 'Authentizität garantiert  ·  Versicherter Versand in der EU  ·  Käuferschutz',
    'gate_password_hash' => '',
    'accent'                  => '#B89C67',
    'accent_2'                => '#B89C67',
    'accent_3'                => '#CDB27E',
    'hero_image'              => '/img/img.png',
    'stripe_publishable_key'  => '',
    'stripe_secret_key'       => '',
    'stripe_webhook_secret'   => '',
];

function setting_get(string $key): ?string {
    global $SETTINGS_DEFAULTS;
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if ($row && $row['value'] !== null) return $row['value'];
    return $SETTINGS_DEFAULTS[$key] ?? null;
}

function setting_set(string $key, string $value): void {
    $stmt = db()->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

function settings_all(): array {
    global $SETTINGS_DEFAULTS;
    $stmt = db()->query('SELECT key, value FROM settings');
    $stored = [];
    foreach ($stmt->fetchAll() as $row) $stored[$row['key']] = $row['value'];
    return array_merge($SETTINGS_DEFAULTS, $stored);
}

function settings_set_many(array $data): void {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
    foreach ($data as $k => $v) $stmt->execute([$k, $v ?? '']);
}
