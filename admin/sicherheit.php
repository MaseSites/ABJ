<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Lookup-Konto darf NUR Zugangscodes generieren – alles andere ist Root.
    require_cap($action === 'gen_code' ? 'security.gen_code' : 'security.admin');
    if ($action === 'save_mode') {
        setting_set('security_mode', !empty($_POST['security_mode']) ? '1' : '0');
    } elseif ($action === 'save_notfound') {
        $nf = in_array($_POST['notfound_mode'] ?? '', ['all', 'selected'], true) ? $_POST['notfound_mode'] : '0';
        setting_set('notfound_mode', $nf);
        setting_set('notfound_ips', trim((string)($_POST['notfound_ips'] ?? '')));
        setting_set('notfound_accounts', trim((string)($_POST['notfound_accounts'] ?? '')));
    } elseif ($action === 'gen_code') {
        code_generate();
    } elseif ($action === 'del_code') {
        code_delete(trim($_POST['code'] ?? ''));
    } elseif ($action === 'allow_my_ip') {
        ip_allow_add(client_ip());
    } elseif ($action === 'allow_add_ip') {
        ip_allow_add(trim($_POST['ip'] ?? ''));
    } elseif ($action === 'remove_allow_ip') {
        ip_allow_remove(trim($_POST['ip'] ?? ''));
    } elseif ($action === 'block_ip') {
        ip_block(trim($_POST['ip'] ?? ''), trim($_POST['note'] ?? ''));
    } elseif ($action === 'unblock_ip') {
        ip_unblock(trim($_POST['ip'] ?? ''));
    } elseif ($action === 'password' && !empty($_POST['new_password'])) {
        $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        db()->prepare("UPDATE users SET password_hash=? WHERE username=?")->execute([$hash, admin_username()]);
    }
    redirect('/admin/sicherheit.php?saved=1');
}

$adminTitle = 'Sicherheit';
include __DIR__ . '/partials/admin-layout-top.php';

$mode    = setting_get('security_mode') === '1';
$nfMode  = setting_get('notfound_mode') ?: '0';
$nfIps   = (string)(setting_get('notfound_ips') ?? '');
$nfAcc   = (string)(setting_get('notfound_accounts') ?? '');
$codes   = promo_codes_all();
$myIp    = client_ip();
$allowed = ip_allow_list();
$blocked = ip_blocks_list();
$myAllowed = false;
foreach ($allowed as $a) { if ($a['ip'] === $myIp) { $myAllowed = true; break; } }
$ipUsers    = ip_user_map(array_merge(array_column($allowed, 'ip'), array_column($blocked, 'ip')));
$ipUserCell = function (string $ip) use ($ipUsers): string {
    $u = $ipUsers[$ip] ?? null;
    if (!$u) return '<span class="muted">unbekannt</span>';
    $name = trim((string)($u['name'] ?? ''));
    return ($name !== '' ? '<strong>' . h($name) . '</strong> ' : '') . '<span class="muted">' . h($u['email'] ?? '') . '</span>';
};
?>
<p class="admin-kicker">System</p>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Sicherheit</h1></div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>

<?php if ($mode && !$myAllowed): ?>
<div class="alert alert-error" style="margin-bottom:1.2rem">
  <strong>Achtung:</strong> Der Sicherheitsmodus ist an, aber deine aktuelle IP (<?= h($myIp) ?>) ist <strong>nicht freigeschaltet</strong>.
  Schalte sie unten frei, sonst siehst du im Shop die Tarnseite.
</div>
<?php endif; ?>

<!-- Sicherheitsmodus -->
<div class="admin-section">
  <h2>Sicherheitsmodus</h2>
  <p style="font-size:.84rem;color:#8a8a95;margin:0 0 1rem">
    Ist der Modus <strong>an</strong>, sehen Besucher statt des Shops eine neutrale Tarnseite („Belegassistent").
    Nur wer dort im Feld <strong>„Belegnummer"</strong> einen gültigen <strong>Zugangscode</strong> eingibt, kann sich
    registrieren/anmelden und gelangt auf den echten Shop. Jeder Code ist <strong>einmal verwendbar</strong> und wird
    fest dem Konto zugewiesen, das ihn nutzt. Wird ein bereits vergebener Code erneut eingegeben, wird die IP
    <strong>sofort gesperrt</strong>. Der Admin-Bereich ist nie betroffen.
  </p>
  <form method="post">
    <input type="hidden" name="action" value="save_mode">
    <label class="switch-row" style="margin-bottom:1rem">
      <input type="checkbox" name="security_mode" value="1" <?= $mode ? 'checked' : '' ?>>
      <div>
        <strong>Sicherheitsmodus aktivieren</strong>
        <small>Standard: aus. Vergib unten Zugangscodes an Kunden (oder schalte deine IP frei), bevor du aktivierst.</small>
      </div>
    </label>
    <button class="btn btn-primary" type="submit">Speichern</button>
  </form>
</div>

<!-- Getarnter 404 -->
<div class="admin-section">
  <h2>Seite als „404" tarnen</h2>
  <p style="font-size:.84rem;color:#8a8a95;margin:0 0 1rem">
    Zeigt statt des Shops eine komplett neutrale, weisse <strong>„Seite nicht gefunden"</strong>-Antwort
    (HTTP&nbsp;404) — als gäbe es die Seite gar nicht. Kein Layout, kein Branding, keine Hinweise.
    <strong>Für alle:</strong> der ganze Shop ist für jeden Besucher „verschwunden".
    <strong>Nur ausgewählte:</strong> nur die unten hinterlegten IP-Adressen bzw. Konten sehen den 404,
    alle anderen den normalen Shop. Angemeldete Admins und der Admin-Bereich sind <strong>nie</strong> betroffen.
  </p>
  <?php if ($nfMode === 'all'): ?>
    <div class="alert alert-error" style="margin-bottom:1rem"><strong>Aktiv für ALLE Besucher:</strong> Der Shop zeigt aktuell für jeden ausser dir (Admin) einen 404.</div>
  <?php elseif ($nfMode === 'selected'): ?>
    <div class="alert alert-ok" style="margin-bottom:1rem"><strong>Aktiv für ausgewählte:</strong> Nur die unten hinterlegten IPs/Konten sehen einen 404.</div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="action" value="save_notfound">
    <label class="switch-row" style="margin-bottom:.6rem">
      <input type="radio" name="notfound_mode" value="0" <?= $nfMode === '0' ? 'checked' : '' ?>>
      <div><strong>Aus</strong><small>Shop ist normal erreichbar.</small></div>
    </label>
    <label class="switch-row" style="margin-bottom:.6rem">
      <input type="radio" name="notfound_mode" value="all" <?= $nfMode === 'all' ? 'checked' : '' ?>>
      <div><strong>Für alle Besucher</strong><small>Jeder sieht einen 404 – der Shop existiert für Besucher nicht mehr.</small></div>
    </label>
    <label class="switch-row" style="margin-bottom:1rem">
      <input type="radio" name="notfound_mode" value="selected" <?= $nfMode === 'selected' ? 'checked' : '' ?>>
      <div><strong>Nur für ausgewählte Konten / IP-Adressen</strong><small>Nur die unten Gelisteten sehen einen 404, alle anderen den Shop.</small></div>
    </label>
    <div style="display:grid;gap:1rem;grid-template-columns:1fr 1fr;max-width:640px">
      <label class="field" style="margin:0">
        <span>IP-Adressen <span class="muted">(eine pro Zeile)</span></span>
        <textarea name="notfound_ips" rows="4" placeholder="203.0.113.5&#10;198.51.100.20"><?= h($nfIps) ?></textarea>
      </label>
      <label class="field" style="margin:0">
        <span>Konten <span class="muted">(E-Mail, eine pro Zeile)</span></span>
        <textarea name="notfound_accounts" rows="4" placeholder="kunde@beispiel.ch"><?= h($nfAcc) ?></textarea>
      </label>
    </div>
    <button class="btn btn-primary" type="submit" style="margin-top:1rem">Speichern</button>
  </form>
</div>

<!-- Zugangscodes (Pool) -->
<div class="admin-section">
  <div class="admin-head-row" style="margin-bottom:1rem">
    <h2 style="margin:0">Zugangscodes</h2>
    <form method="post" data-cap="security.gen_code"><input type="hidden" name="action" value="gen_code"><button class="btn btn-primary btn-sm" type="submit">+ Code generieren</button></form>
  </div>
  <p style="font-size:.82rem;color:#8a8a95;margin:0 0 1rem">
    Ein Code bringt eine Person in den Shop (Sicherheitsmodus) und ist <strong>einmal verwendbar</strong>.
    Codes erstellst <strong>du selbst</strong> oder <strong>Kunden in ihrem Profil</strong> (Promo-Code). Wer sich mit
    dem Code eines Kunden neu registriert, wird dessen Empfehlung — der Kunde bekommt dann Promo-Punkte pro Bestellung.
  </p>
  <?php if (empty($codes)): ?>
    <p class="muted">Noch keine Codes. Klicke auf „Code generieren" — oder Kunden generieren eigene.</p>
  <?php else: ?>
  <div class="table-card"><table class="data-table">
    <thead><tr><th>Code</th><th>Erstellt von</th><th>Status</th><th>Erstellt</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($codes as $cd): $owner = (int)($cd['account_id'] ?? 0); $used = !empty($cd['used_by']); ?>
      <tr<?= $used ? ' style="opacity:.7"' : '' ?>>
        <td><strong style="font-variant-numeric:tabular-nums;letter-spacing:.12em;font-size:1rem"><?= h($cd['code']) ?></strong></td>
        <td>
          <?php if ($owner > 0): ?>
            <span class="tag tag-ok">Kunde</span> <?= h($cd['owner_name'] ?: '') ?> <span class="muted"><?= h($cd['owner_email'] ?: ('#' . $owner)) ?></span>
          <?php else: ?>
            <span class="tag">Admin</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($used): ?>
            <span class="tag">verwendet</span> <span class="muted"><?= h(($cd['used_name'] ?? '') ?: ($cd['used_email'] ?? '')) ?></span>
          <?php else: ?>
            <span class="tag tag-ok">frei</span>
          <?php endif; ?>
        </td>
        <td class="muted"><?= h(substr($cd['created_at'], 0, 16)) ?></td>
        <td class="cell-actions">
          <form method="post" onsubmit="return confirm('Code <?= h($cd['code']) ?> löschen?')"><input type="hidden" name="action" value="del_code"><input type="hidden" name="code" value="<?= h($cd['code']) ?>">
            <button class="btn btn-ghost btn-sm" type="submit">Löschen</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<!-- Freigeschaltete IPs -->
<div class="admin-section">
  <h2>Freigeschaltete IP-Adressen</h2>
  <p style="font-size:.82rem;color:#8a8a95;margin:0 0 1rem">Diese IPs sehen immer den echten Shop.</p>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem">
    <form method="post">
      <input type="hidden" name="action" value="allow_my_ip">
      <button class="btn <?= $myAllowed ? 'btn-ghost' : 'btn-primary' ?> btn-sm" type="submit"<?= $myAllowed ? ' disabled' : '' ?>>
        <?= $myAllowed ? 'Deine IP ist freigeschaltet' : 'Meine IP freischalten (' . h($myIp) . ')' ?>
      </button>
    </form>
    <form method="post" style="display:flex;gap:.5rem;align-items:flex-end">
      <input type="hidden" name="action" value="allow_add_ip">
      <label class="field" style="max-width:200px;margin:0"><span>IP manuell freischalten</span><input type="text" name="ip" placeholder="z.B. 203.0.113.5"></label>
      <button class="btn btn-sm" type="submit">Hinzufügen</button>
    </form>
  </div>
  <?php if (empty($allowed)): ?>
    <p class="muted">Noch keine freigeschalteten IPs.</p>
  <?php else: ?>
  <div class="table-card"><table class="data-table">
    <thead><tr><th>IP</th><th>Nutzer</th><th>Seit</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($allowed as $a): ?>
      <tr>
        <td data-label="IP"><strong style="font-variant-numeric:tabular-nums"><?= h($a['ip']) ?></strong><?= $a['ip'] === $myIp ? ' <span class="tag tag-ok">du</span>' : '' ?></td>
        <td data-label="Nutzer"><?= $ipUserCell($a['ip']) ?></td>
        <td data-label="Seit" class="muted"><?= h(substr($a['created_at'], 0, 16)) ?></td>
        <td class="cell-actions"><form method="post"><input type="hidden" name="action" value="remove_allow_ip"><input type="hidden" name="ip" value="<?= h($a['ip']) ?>"><button class="btn btn-ghost btn-sm" type="submit">Entfernen</button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<!-- Gesperrte IPs -->
<div class="admin-section">
  <h2>Gesperrte IP-Adressen</h2>
  <p style="font-size:.82rem;color:#8a8a95;margin:0 0 1rem">Diese IPs erhalten im Shop „Zugriff gesperrt". (Besucher siehst du auch unter <a href="<?= url('/admin/analytics.php') ?>" style="color:#7e8bb8">Analytics</a>.)</p>
  <form method="post" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem">
    <input type="hidden" name="action" value="block_ip">
    <label class="field" style="max-width:200px;margin:0"><span>IP sperren</span><input type="text" name="ip" placeholder="z.B. 203.0.113.5" required></label>
    <label class="field" style="max-width:200px;margin:0"><span>Notiz</span><input type="text" name="note" placeholder="optional"></label>
    <button class="btn btn-danger" type="submit">Sperren</button>
  </form>
  <?php if (empty($blocked)): ?>
    <p class="muted">Keine gesperrten IPs.</p>
  <?php else: ?>
  <div class="table-card"><table class="data-table">
    <thead><tr><th>IP</th><th>Nutzer</th><th>Notiz</th><th>Seit</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($blocked as $b): ?>
      <tr>
        <td data-label="IP"><strong style="font-variant-numeric:tabular-nums"><?= h($b['ip']) ?></strong></td>
        <td data-label="Nutzer"><?= $ipUserCell($b['ip']) ?></td>
        <td data-label="Notiz" class="muted"><?= h($b['note'] ?: '–') ?></td>
        <td data-label="Seit" class="muted"><?= h(substr($b['created_at'], 0, 16)) ?></td>
        <td class="cell-actions"><form method="post"><input type="hidden" name="action" value="unblock_ip"><input type="hidden" name="ip" value="<?= h($b['ip']) ?>"><button class="btn btn-ghost btn-sm" type="submit">Entsperren</button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<!-- Admin-Passwort -->
<div class="admin-section">
  <h2>Admin-Passwort</h2>
  <form method="post" style="display:flex;gap:.7rem;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="action" value="password">
    <label class="field" style="max-width:300px;margin:0"><span>Neues Passwort</span><input type="password" name="new_password" autocomplete="new-password" placeholder="••••••••"></label>
    <button class="btn btn-primary" type="submit">Passwort ändern</button>
  </form>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
