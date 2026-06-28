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
    // Versand
    'shipping_ch_cents'        => '590',
    'shipping_intl_cents'      => '1990',
    'free_shipping_from_cents' => '0',
    // Bankverbindung (Vorkasse)
    'bank_recipient' => 'ABJ Store',
    'bank_iban'      => '',
    'bank_bic'       => '',
    'bank_name'      => '',
    // Social Media (Footer)
    'instagram_url'  => '',
    'tiktok_url'     => '',
    // Finanzen (manuell pflegbar)
    'finance_account_cents'  => '0',
    'finance_invested_cents' => '0',
    // Preisrechner
    'calc_usd_chf'     => '0.81',
    'calc_flat'        => '1.5',
    'calc_ship_per_kg' => '25',
    'calc_vk_factor'   => '1.8',
    'calc_min_factor'  => '1.55',
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
