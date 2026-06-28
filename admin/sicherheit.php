<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_mode') {
        setting_set('security_mode', !empty($_POST['security_mode']) ? '1' : '0');
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
        db()->prepare("UPDATE users SET password_hash=? WHERE username='admin'")->execute([$hash]);
    }
    redirect('/admin/sicherheit.php?saved=1');
}

$adminTitle = 'Sicherheit';
include __DIR__ . '/partials/admin-layout-top.php';

$mode    = setting_get('security_mode') === '1';
$codes   = codes_list();
$promoAll = promo_codes_all();
$myIp    = client_ip();
$allowed = ip_allow_list();
$blocked = ip_blocks_list();
$myAllowed = false;
foreach ($allowed as $a) { if ($a['ip'] === $myIp) { $myAllowed = true; break; } }
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
    Nur wer dort im Feld <strong>„Belegnummer"</strong> seinen <strong>persönlichen Zugangscode</strong> eingibt, wird
    automatisch in sein Konto eingeloggt und gelangt auf den echten Shop. Jeder Code gehört zu genau einem Kunden.
    Der Admin-Bereich ist nie betroffen.
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

<!-- Zugangscodes (Pool) -->
<div class="admin-section">
  <div class="admin-head-row" style="margin-bottom:1rem">
    <h2 style="margin:0">Zugangscodes</h2>
    <form method="post"><input type="hidden" name="action" value="gen_code"><button class="btn btn-primary btn-sm" type="submit">+ Code generieren</button></form>
  </div>
  <p style="font-size:.82rem;color:#8a8a95;margin:0 0 1rem">
    Generiere Codes und gib sie weiter. Ein Code ist <strong>einmal verwendbar</strong>: Beim ersten Eintippen auf der
    Tarnseite muss sich die Person anmelden/registrieren — danach gehört der Code <strong>fest zu diesem Konto</strong>.
    Tippt jemand den Code erneut ein und meldet sich falsch an, wird seine IP automatisch gesperrt.
  </p>
  <?php if (empty($codes)): ?>
    <p class="muted">Noch keine Codes. Klicke auf „Code generieren".</p>
  <?php else: ?>
  <div class="table-card"><table class="data-table">
    <thead><tr><th>Code</th><th>Status</th><th>Erstellt</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($codes as $cd): ?>
      <tr>
        <td><strong style="font-variant-numeric:tabular-nums;letter-spacing:.12em;font-size:1rem"><?= h($cd['code']) ?></strong></td>
        <td>
          <?php if (!empty($cd['account_id'])): ?>
            <span class="tag tag-ok">zugewiesen</span> <span class="muted"><?= h($cd['account_email'] ?: ('#' . $cd['account_id'])) ?></span>
          <?php else: ?>
            <span class="tag tag-warn">frei</span>
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

<!-- Promo-Codes (von Kunden erstellt) -->
<div class="admin-section">
  <h2>Promo-Codes <span class="muted" style="font-weight:400;font-size:.9rem">(von Kunden erstellt)</span></h2>
  <p style="font-size:.82rem;color:#8a8a95;margin:0 0 1rem">Empfehlungscodes, die Kunden in ihrem Profil generiert haben.</p>
  <?php if (empty($promoAll)): ?>
    <p class="muted">Noch keine Promo-Codes.</p>
  <?php else: ?>
  <div class="table-card"><table class="data-table">
    <thead><tr><th>Promo-Code</th><th>Erstellt von</th><th>Erstellt</th></tr></thead>
    <tbody>
      <?php foreach ($promoAll as $pc): ?>
      <tr>
        <td><strong style="font-variant-numeric:tabular-nums;letter-spacing:.12em;font-size:1rem"><?= h($pc['code']) ?></strong></td>
        <td><?= h($pc['owner_name'] ?: '—') ?> <span class="muted"><?= h($pc['owner_email'] ?: ('#' . $pc['account_id'])) ?></span></td>
        <td class="muted"><?= h(substr($pc['created_at'], 0, 16)) ?></td>
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
    <thead><tr><th>IP</th><th>Seit</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($allowed as $a): ?>
      <tr>
        <td><strong style="font-variant-numeric:tabular-nums"><?= h($a['ip']) ?></strong><?= $a['ip'] === $myIp ? ' <span class="tag tag-ok">du</span>' : '' ?></td>
        <td class="muted"><?= h(substr($a['created_at'], 0, 16)) ?></td>
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
    <thead><tr><th>IP</th><th>Notiz</th><th>Seit</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($blocked as $b): ?>
      <tr>
        <td><strong style="font-variant-numeric:tabular-nums"><?= h($b['ip']) ?></strong></td>
        <td class="muted"><?= h($b['note'] ?: '–') ?></td>
        <td class="muted"><?= h(substr($b['created_at'], 0, 16)) ?></td>
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
