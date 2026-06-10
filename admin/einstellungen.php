<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['shop_name','tagline','currency','hero_title','hero_subtitle','contact_email','announcement','members_count','ratings_count','sale_ends_at','hero_image','accent','accent_2','accent_3','stripe_publishable_key','stripe_secret_key','stripe_webhook_secret'];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');
    if (!empty($_POST['new_password'])) {
        $data['gate_password_hash'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    }
    settings_set_many($data);
    redirect('/admin/einstellungen.php?saved=1');
}

$adminTitle = 'Einstellungen';
include __DIR__ . '/partials/admin-layout-top.php';
$s = settings_all();
?>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Einstellungen</h1></div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>

<form method="post" class="admin-form" style="max-width:600px">
  <h2 style="margin-bottom:1rem">Shop</h2>
  <label class="field"><span>Shop-Name</span><input type="text" name="shop_name" value="<?= h($s['shop_name']) ?>"></label>
  <label class="field"><span>Tagline</span><input type="text" name="tagline" value="<?= h($s['tagline']) ?>"></label>
  <label class="field"><span>Währung</span><input type="text" name="currency" value="<?= h($s['currency']) ?>" maxlength="3"></label>
  <label class="field"><span>Kontakt-E-Mail</span><input type="email" name="contact_email" value="<?= h($s['contact_email']) ?>"></label>
  <label class="field"><span>Ankündigungstext (Header-Banner)</span><input type="text" name="announcement" value="<?= h($s['announcement']) ?>"></label>

  <h2 style="margin:1.5rem 0 1rem">Hero</h2>
  <label class="field"><span>Hero-Titel (HTML möglich)</span><input type="text" name="hero_title" value="<?= h($s['hero_title']) ?>"></label>
  <label class="field"><span>Hero-Untertitel</span><textarea name="hero_subtitle" rows="3"><?= h($s['hero_subtitle']) ?></textarea></label>
  <label class="field"><span>Hero-Bild (Pfad oder URL)</span><input type="text" name="hero_image" value="<?= h($s['hero_image']) ?>"></label>
  <label class="field"><span>Sale endet am (ISO-Datum, z.B. 2026-12-31T23:59:59)</span><input type="text" name="sale_ends_at" value="<?= h($s['sale_ends_at']) ?>"></label>
  <label class="field"><span>Kundenzahl</span><input type="text" name="members_count" value="<?= h($s['members_count']) ?>"></label>
  <label class="field"><span>Bewertungszahl</span><input type="text" name="ratings_count" value="<?= h($s['ratings_count']) ?>"></label>

  <h2 style="margin:1.5rem 0 1rem">Design</h2>
  <div class="form-row-2">
    <label class="field"><span>Akzentfarbe 1</span><input type="color" name="accent" value="<?= h($s['accent']) ?>"></label>
    <label class="field"><span>Akzentfarbe 2</span><input type="color" name="accent_2" value="<?= h($s['accent_2']) ?>"></label>
  </div>

  <h2 style="margin:1.5rem 0 1rem">Zahlungen (Stripe)</h2>
  <p style="font-size:.82rem;color:var(--ink-dim);margin-bottom:.8rem">
    Schlüssel findest du im <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Stripe Dashboard</a>.
    Nutze <code>pk_test_…</code> / <code>sk_test_…</code> zum Testen, <code>pk_live_…</code> / <code>sk_live_…</code> für echte Zahlungen.
  </p>
  <label class="field"><span>Publishable Key (pk_live_… oder pk_test_…)</span><input type="text" name="stripe_publishable_key" value="<?= h($s['stripe_publishable_key'] ?? '') ?>" placeholder="pk_live_…" autocomplete="off"></label>
  <label class="field"><span>Secret Key (sk_live_… oder sk_test_…)</span><input type="password" name="stripe_secret_key" value="<?= h($s['stripe_secret_key'] ?? '') ?>" placeholder="sk_live_…" autocomplete="new-password"></label>
  <label class="field">
    <span>Webhook Secret (whsec_…)</span>
    <input type="password" name="stripe_webhook_secret" value="<?= h($s['stripe_webhook_secret'] ?? '') ?>" placeholder="whsec_…" autocomplete="new-password">
    <small style="color:var(--ink-dim);font-size:.75rem">Webhook-Endpoint: <code><?= h((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'deineshop.de') . '/api/stripe-webhook') ?></code></small>
  </label>

  <h2 style="margin:1.5rem 0 1rem">Sicherheit</h2>
  <label class="field"><span>Neues Admin-Passwort (leer = unverändert)</span><input type="password" name="new_password" autocomplete="new-password"></label>

  <button class="btn btn-primary" type="submit">Speichern</button>
</form>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
