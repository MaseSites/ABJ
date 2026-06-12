<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'shop_name','tagline','currency','hero_title','hero_subtitle','contact_email','announcement',
        'members_count','ratings_count','sale_ends_at','hero_image','accent','accent_2','accent_3',
        'stripe_publishable_key','stripe_secret_key','stripe_webhook_secret',
        'bank_recipient','bank_iban','bank_bic','bank_name',
        'instagram_url','tiktok_url',
    ];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

    // Versandkosten: Eingabe in CHF -> Rappen
    foreach (['shipping_ch' => 'shipping_ch_cents', 'shipping_intl' => 'shipping_intl_cents', 'free_shipping_from' => 'free_shipping_from_cents'] as $in => $key) {
        $raw = str_replace(',', '.', trim($_POST[$in] ?? ''));
        $data[$key] = (string)max(0, (int)round((float)$raw * 100));
    }

    // Sale-Countdown (datetime-local liefert "YYYY-MM-DDTHH:MM")
    if (!empty($data['sale_ends_at']) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $data['sale_ends_at'])) {
        $data['sale_ends_at'] .= ':00';
    }

    if (!empty($_POST['new_password'])) {
        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        db()->prepare("UPDATE users SET password_hash=? WHERE username='admin'")->execute([$hash]);
    }
    settings_set_many($data);
    redirect('/admin/einstellungen.php?saved=1');
}

$adminTitle = 'Einstellungen';
include __DIR__ . '/partials/admin-layout-top.php';
$s = settings_all();
?>
<p class="admin-kicker">System</p>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Einstellungen</h1></div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>

<form method="post" class="admin-form" style="max-width:640px">

  <div class="admin-section">
    <h2>Shop</h2>
    <label class="field"><span>Shop-Name</span><input type="text" name="shop_name" value="<?= h($s['shop_name']) ?>"></label>
    <label class="field"><span>Tagline</span><input type="text" name="tagline" value="<?= h($s['tagline']) ?>"></label>
    <div class="form-row-2">
      <label class="field"><span>Währung</span><input type="text" name="currency" value="<?= h($s['currency']) ?>" maxlength="3"></label>
      <label class="field"><span>Kontakt-E-Mail</span><input type="email" name="contact_email" value="<?= h($s['contact_email']) ?>"></label>
    </div>
    <label class="field"><span>Ankündigungstext (Header-Banner, leer = ausblenden)</span><input type="text" name="announcement" value="<?= h($s['announcement']) ?>"></label>
    <div class="form-row-2">
      <label class="field"><span>Instagram-URL</span><input type="url" name="instagram_url" value="<?= h($s['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/…"></label>
      <label class="field"><span>TikTok-URL</span><input type="url" name="tiktok_url" value="<?= h($s['tiktok_url'] ?? '') ?>" placeholder="https://tiktok.com/@…"></label>
    </div>
  </div>

  <div class="admin-section">
    <h2>Startseite / Hero</h2>
    <label class="field"><span>Hero-Untertitel</span><textarea name="hero_subtitle" rows="3"><?= h($s['hero_subtitle']) ?></textarea></label>
    <label class="field"><span>Hero-Bild (Pfad oder URL)</span><input type="text" name="hero_image" value="<?= h($s['hero_image']) ?>"></label>
    <label class="field">
      <span>Sale-Countdown endet am</span>
      <input type="datetime-local" name="sale_ends_at" value="<?= h(substr($s['sale_ends_at'] ?? '', 0, 16)) ?>">
      <small style="color:#8a8a95;font-size:.75rem">Steuert den Timer auf der Startseite. Leer lassen = Timer zeigt 00:00.</small>
    </label>
    <div class="form-row-2">
      <label class="field"><span>Kundenzahl (Social Proof)</span><input type="text" name="members_count" value="<?= h($s['members_count']) ?>"></label>
      <label class="field"><span>Bewertungszahl (Social Proof)</span><input type="text" name="ratings_count" value="<?= h($s['ratings_count']) ?>"></label>
    </div>
    <input type="hidden" name="hero_title" value="<?= h($s['hero_title']) ?>">
  </div>

  <div class="admin-section">
    <h2>Versand</h2>
    <div class="form-row-2">
      <label class="field"><span>Versand Schweiz (<?= h($s['currency']) ?>)</span><input type="text" name="shipping_ch" value="<?= number_format(((int)($s['shipping_ch_cents'] ?? 590)) / 100, 2, '.', '') ?>" inputmode="decimal"></label>
      <label class="field"><span>Versand International (<?= h($s['currency']) ?>)</span><input type="text" name="shipping_intl" value="<?= number_format(((int)($s['shipping_intl_cents'] ?? 1990)) / 100, 2, '.', '') ?>" inputmode="decimal"></label>
    </div>
    <label class="field"><span>Gratisversand ab (<?= h($s['currency']) ?>, 0 = nie)</span><input type="text" name="free_shipping_from" value="<?= number_format(((int)($s['free_shipping_from_cents'] ?? 0)) / 100, 2, '.', '') ?>" inputmode="decimal"></label>
  </div>

  <div class="admin-section">
    <h2>Bankverbindung (Vorkasse)</h2>
    <p style="font-size:.82rem;color:#8a8a95;margin:0 0 .4rem">Wird Kunden bei Zahlung per Banküberweisung angezeigt. Ohne IBAN wird nur ein Hinweis gezeigt.</p>
    <div class="form-row-2">
      <label class="field"><span>Empfänger</span><input type="text" name="bank_recipient" value="<?= h($s['bank_recipient'] ?? '') ?>"></label>
      <label class="field"><span>Bank</span><input type="text" name="bank_name" value="<?= h($s['bank_name'] ?? '') ?>"></label>
    </div>
    <div class="form-row-2">
      <label class="field"><span>IBAN</span><input type="text" name="bank_iban" value="<?= h($s['bank_iban'] ?? '') ?>" placeholder="CH00 0000 0000 0000 0000 0"></label>
      <label class="field"><span>BIC</span><input type="text" name="bank_bic" value="<?= h($s['bank_bic'] ?? '') ?>"></label>
    </div>
  </div>

  <div class="admin-section">
    <h2>Design</h2>
    <div class="form-row-2">
      <label class="field"><span>Akzentfarbe 1</span><input type="color" name="accent" value="<?= h($s['accent']) ?>"></label>
      <label class="field"><span>Akzentfarbe 2</span><input type="color" name="accent_2" value="<?= h($s['accent_2']) ?>"></label>
    </div>
    <input type="hidden" name="accent_3" value="<?= h($s['accent_3']) ?>">
  </div>

  <div class="admin-section">
    <h2>Zahlungen (Stripe)</h2>
    <p style="font-size:.82rem;color:#8a8a95;margin-bottom:.4rem">
      Schlüssel findest du im <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener" style="color:#b89c67">Stripe Dashboard</a>.
      Nutze <code>pk_test_…</code> / <code>sk_test_…</code> zum Testen, <code>pk_live_…</code> / <code>sk_live_…</code> für echte Zahlungen.
    </p>
    <label class="field"><span>Publishable Key</span><input type="text" name="stripe_publishable_key" value="<?= h($s['stripe_publishable_key'] ?? '') ?>" placeholder="pk_live_…" autocomplete="off"></label>
    <label class="field"><span>Secret Key</span><input type="password" name="stripe_secret_key" value="<?= h($s['stripe_secret_key'] ?? '') ?>" placeholder="sk_live_…" autocomplete="new-password"></label>
    <label class="field">
      <span>Webhook Secret (whsec_…)</span>
      <input type="password" name="stripe_webhook_secret" value="<?= h($s['stripe_webhook_secret'] ?? '') ?>" placeholder="whsec_…" autocomplete="new-password">
      <small style="color:#8a8a95;font-size:.75rem">Webhook-Endpoint: <code><?= h((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'deineshop.ch') . url('/api/stripe-webhook.php')) ?></code></small>
    </label>
  </div>

  <div class="admin-section">
    <h2>Sicherheit</h2>
    <label class="field"><span>Neues Admin-Passwort (leer = unverändert)</span><input type="password" name="new_password" autocomplete="new-password"></label>
  </div>

  <button class="btn btn-primary" type="submit">Alle Einstellungen speichern</button>
</form>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
