<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$id  = (int)($_GET['id'] ?? 0);
$acc = $id ? account_by_id($id) : null;
if (!$acc) redirect('/admin/kunden.php');

$COUNTRIES = [
    ['CH','Schweiz'],['LI','Liechtenstein'],['AT','Österreich'],['DE','Deutschland'],
    ['FR','Frankreich'],['IT','Italien'],['ES','Spanien'],['NL','Niederlande'],['GB','Grossbritannien'],['US','USA'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_cap('customers.manage');
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $address = [
            'firstname' => trim($_POST['firstname'] ?? ''),
            'lastname'  => trim($_POST['lastname'] ?? ''),
            'street'    => trim($_POST['street'] ?? ''),
            'housenr'   => trim($_POST['housenr'] ?? ''),
            'zip'       => trim($_POST['zip'] ?? ''),
            'city'      => trim($_POST['city'] ?? ''),
            'country'   => trim($_POST['country'] ?? 'CH'),
        ];
        account_update_profile($id, trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? ''), $address);
        $newEmail = trim($_POST['email'] ?? '');
        if ($newEmail !== '' && strtolower($newEmail) !== strtolower($acc['email'])) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || account_by_email($newEmail)) {
                redirect('/admin/kunde.php?id=' . $id . '&err=email');
            }
            db()->prepare('UPDATE accounts SET email=? WHERE id=?')->execute([$newEmail, $id]);
        }
        redirect('/admin/kunde.php?id=' . $id . '&saved=1');
    }

    if ($action === 'message') {
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            account_message_create([
                'account_id' => $id,
                'sender_role' => 'admin',
                'subject' => $subject !== '' ? $subject : 'Nachricht',
                'body' => $body,
                'is_read' => 0,
            ]);
            redirect('/admin/kunde.php?id=' . $id . '&saved=msg');
        }
        redirect('/admin/kunde.php?id=' . $id . '&err=msg');
    }

    if ($action === 'activate') {
        account_activate($id);
        redirect('/admin/kunde.php?id=' . $id . '&saved=activated');
    }

    if ($action === 'points') {
        $pts = max(0, (int)($_POST['points'] ?? 0));
        db()->prepare('UPDATE accounts SET promo_points=? WHERE id=?')->execute([$pts, $id]);
        redirect('/admin/kunde.php?id=' . $id . '&saved=points');
    }

    if ($action === 'password') {
        if (account_update_password($id, $_POST['new_password'] ?? '')) {
            redirect('/admin/kunde.php?id=' . $id . '&saved=pw');
        }
        redirect('/admin/kunde.php?id=' . $id . '&err=pw');
    }

    if ($action === 'message_delete') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) account_message_delete($id, $messageId);
        redirect('/admin/kunde.php?id=' . $id . '&saved=msgdel');
    }

    if ($action === 'delete') {
        account_delete($id);
        redirect('/admin/kunden.php?deleted=1');
    }
}

$currency = setting_get('currency') ?: 'CHF';
$addr     = account_address($acc);
$points   = promo_points($id);
$refStats = promo_referral_stats($id);
$refBy    = !empty($acc['referred_by']) ? account_by_id((int)$acc['referred_by']) : null;

$os = db()->prepare("SELECT reference, total_cents, amount_paid_cents, payment_status, status, created_at
                     FROM orders WHERE lower(email) = lower(?) ORDER BY created_at DESC");
$os->execute([$acc['email']]);
$custOrders = $os->fetchAll();
$ordRevenue = 0;
foreach ($custOrders as $o) if (($o['payment_status'] ?? '') === 'bezahlt') $ordRevenue += (int)$o['total_cents'];

$inbox = account_messages_by_account($id);

$adminTitle = 'Kunde: ' . ($acc['name'] ?: $acc['email']);
include __DIR__ . '/partials/admin-layout-top.php';
?>
<p class="admin-kicker">Kunden</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1><?= h($acc['name'] ?: 'Unbenannt') ?> <?php if (!account_is_activated($acc)): ?><span class="tag tag-partial" style="vertical-align:middle;font-size:.7rem">eingeschränkt</span><?php endif; ?></h1>
  <a class="btn btn-ghost" href="<?= url('/admin/kunden.php') ?>">← Zurück</a>
</div>

<?php if (($_GET['saved'] ?? '') === '1'): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>
<?php if (($_GET['saved'] ?? '') === 'msg'): ?><div class="alert alert-ok" style="margin-bottom:1rem">Nachricht gesendet.</div><?php endif; ?>
<?php if (($_GET['saved'] ?? '') === 'msgdel'): ?><div class="alert alert-ok" style="margin-bottom:1rem">Nachricht gelöscht.</div><?php endif; ?>
<?php if (($_GET['saved'] ?? '') === 'activated'): ?><div class="alert alert-ok" style="margin-bottom:1rem">Konto aktiviert. Zurückgehaltene Bestellungen wurden freigegeben.</div><?php endif; ?>
<?php if (($_GET['err'] ?? '') === 'email'): ?><div class="alert alert-error" style="margin-bottom:1rem">E-Mail ungültig oder bereits vergeben.</div><?php endif; ?>
<?php if (($_GET['err'] ?? '') === 'pw'): ?><div class="alert alert-error" style="margin-bottom:1rem">Passwort muss mindestens 8 Zeichen lang sein.</div><?php endif; ?>
<?php if (($_GET['err'] ?? '') === 'msg'): ?><div class="alert alert-error" style="margin-bottom:1rem">Bitte eine Nachricht eingeben.</div><?php endif; ?>

<div class="stat-grid" style="margin-bottom:1.6rem">
  <div class="stat-card stat-highlight"><span class="stat-num"><?= $points ?></span><span class="stat-label">Promo Punkte</span></div>
  <div class="stat-card"><span class="stat-num"><?= count($custOrders) ?></span><span class="stat-label">Bestellungen</span></div>
  <div class="stat-card"><span class="stat-num"><?= format_price($ordRevenue, $currency) ?></span><span class="stat-label">Umsatz (bezahlt)</span></div>
  <div class="stat-card"><span class="stat-num"><?= (int)$refStats['referrals'] ?></span><span class="stat-label">Geworbene Kunden</span></div>
</div>

<div class="admin-2col">
  <div class="admin-section">
    <h2>Profil &amp; Adresse</h2>
    <form method="post" data-cap="customers.manage" class="admin-form">
      <input type="hidden" name="action" value="profile">
      <div class="form-row-2">
        <label class="field"><span>Name</span><input type="text" name="name" maxlength="120" value="<?= h($acc['name'] ?? '') ?>"></label>
        <label class="field"><span>Telefon</span><input type="text" name="phone" maxlength="40" value="<?= h($acc['phone'] ?? '') ?>"></label>
      </div>
      <label class="field"><span>E-Mail</span><input type="email" name="email" maxlength="160" value="<?= h($acc['email']) ?>"></label>
      <div class="form-row-2">
        <label class="field"><span>Vorname</span><input type="text" name="firstname" value="<?= h($addr['firstname'] ?? '') ?>"></label>
        <label class="field"><span>Nachname</span><input type="text" name="lastname" value="<?= h($addr['lastname'] ?? '') ?>"></label>
      </div>
      <div class="form-row-2" style="grid-template-columns:2fr 1fr">
        <label class="field"><span>Strasse</span><input type="text" name="street" value="<?= h($addr['street'] ?? '') ?>"></label>
        <label class="field"><span>Nr.</span><input type="text" name="housenr" value="<?= h($addr['housenr'] ?? '') ?>"></label>
      </div>
      <div class="form-row-2" style="grid-template-columns:1fr 2fr">
        <label class="field"><span>PLZ</span><input type="text" name="zip" value="<?= h($addr['zip'] ?? '') ?>"></label>
        <label class="field"><span>Stadt</span><input type="text" name="city" value="<?= h($addr['city'] ?? '') ?>"></label>
      </div>
      <label class="field"><span>Land</span>
        <select name="country">
          <?php foreach ($COUNTRIES as [$code, $cname]): ?>
            <option value="<?= h($code) ?>"<?= ($addr['country'] ?? 'CH') === $code ? ' selected' : '' ?>><?= h($cname) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary" type="submit" style="align-self:flex-start">Speichern</button>
    </form>
  </div>

  <div>
    <div class="admin-section">
      <h2>Nachricht senden</h2>
      <p class="muted" style="font-size:.84rem;margin-top:-.4rem">Die Nachricht landet direkt im Posteingang dieses Kunden.</p>
      <form method="post" data-cap="customers.manage" class="admin-form">
        <input type="hidden" name="action" value="message">
        <label class="field"><span>Betreff</span><input type="text" name="subject" maxlength="120" placeholder="z. B. Rückfrage zur Bestellung"></label>
        <label class="field"><span>Nachricht</span><textarea name="body" rows="4" placeholder="Text für den Kunden"></textarea></label>
        <button class="btn btn-primary" type="submit" style="align-self:flex-start">Senden</button>
      </form>
    </div>

    <div class="admin-section">
      <h2>Promo Punkte</h2>
      <p class="muted" style="font-size:.84rem;margin-top:-.4rem">Aktuell: <strong><?= $points ?></strong> Punkte.<?php if ($refBy): ?> Geworben von <strong><?= h($refBy['name'] ?: $refBy['email']) ?></strong>.<?php endif; ?></p>
      <form method="post" data-cap="customers.manage" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="action" value="points">
        <label class="field" style="max-width:160px;margin:0"><span>Punkte setzen</span><input type="number" name="points" min="0" max="100000" value="<?= $points ?>"></label>
        <button class="btn btn-primary" type="submit">Übernehmen</button>
      </form>
    </div>

    <div class="admin-section">
      <h2>Passwort zurücksetzen</h2>
      <p class="muted" style="font-size:.84rem;margin-top:-.4rem">Falls der Kunde sein Passwort vergessen hat, setze hier ein neues.</p>
      <form method="post" data-cap="customers.manage" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="action" value="password">
        <label class="field" style="max-width:240px;margin:0"><span>Neues Passwort <small class="muted">(min. 8)</small></span><input type="text" name="new_password" minlength="8" placeholder="neues Passwort"></label>
        <button class="btn btn-primary" type="submit">Setzen</button>
      </form>
    </div>

    <div class="admin-section" style="<?= account_is_activated($acc) ? '' : 'border-color:rgba(224,182,74,.4)' ?>">
      <h2>Kontostatus</h2>
      <?php if (account_is_activated($acc)): ?>
        <p class="muted" style="font-size:.84rem;margin-top:-.4rem">Dieses Konto ist <span class="tag tag-ok">freigeschaltet</span>. Bestellungen laufen normal ins Dashboard.</p>
      <?php else: ?>
        <p class="muted" style="font-size:.84rem;margin-top:-.4rem">Dieses Konto ist <span class="tag tag-partial">eingeschränkt</span>. Bestellungen werden zurückgehalten, bis das Konto aktiviert ist. Beim Aktivieren laufen zurückgehaltene Bestellungen automatisch ein.</p>
        <form method="post" data-cap="customers.manage" style="margin:0">
          <input type="hidden" name="action" value="activate">
          <button class="btn btn-primary" type="submit">Konto jetzt aktivieren</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="admin-section" style="border-color:rgba(226,96,76,.3)">
      <h2>Konto löschen</h2>
      <p class="muted" style="font-size:.84rem;margin-top:-.4rem">Entfernt den Zugang. Bestellungen bleiben als Historie erhalten.</p>
      <form method="post" data-cap="customers.manage" onsubmit="return confirm('Konto von <?= h($acc['name'] ?: $acc['email']) ?> wirklich löschen?')">
        <input type="hidden" name="action" value="delete">
        <button class="btn btn-danger" type="submit">Konto endgültig löschen</button>
      </form>
    </div>
  </div>
</div>

<div class="admin-section" style="margin-top:1.6rem">
  <h2>Posteingang des Kunden (<?= count($inbox) ?>)</h2>
  <p class="muted" style="font-size:.84rem;margin-top:-.4rem">Alle Nachrichten, die dieser Kunde in seinem Konto sieht.</p>
  <?php if (empty($inbox)): ?>
    <p class="muted" style="margin:0">Noch keine Nachrichten.</p>
  <?php else: foreach ($inbox as $m): ?>
    <div class="message-card <?= !empty($m['is_read']) ? '' : 'message-unread' ?>">
      <div class="message-meta">
        <strong><?= h($m['subject'] ?: 'Nachricht') ?></strong>
        <?php if (!empty($m['order_reference'])): ?><span class="muted"><?= h($m['order_reference']) ?></span><?php endif; ?>
        <span class="muted"><?= h($m['sender_role'] ?: 'admin') ?></span>
        <span class="muted"><?= h(substr($m['created_at'], 0, 16)) ?></span>
        <?php if (empty($m['is_read'])): ?><span class="tag">ungelesen</span><?php endif; ?>
      </div>
      <p style="white-space:pre-line"><?= nl2br(h($m['body'])) ?></p>
      <form method="post" data-cap="customers.manage" onsubmit="return confirm('Nachricht wirklich löschen?')" style="margin:0">
        <input type="hidden" name="action" value="message_delete">
        <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
        <button class="btn btn-ghost btn-sm btn-danger" type="submit">Löschen</button>
      </form>
    </div>
  <?php endforeach; endif; ?>
</div>

<div class="admin-section" style="margin-top:1.6rem">
  <h2>Bestellungen (<?= count($custOrders) ?>)</h2>
  <?php if (empty($custOrders)): ?>
    <p class="muted" style="margin:0">Noch keine Bestellungen.</p>
  <?php else: ?>
  <div class="table-card"><table class="data-table">
    <thead><tr><th>Referenz</th><th>Datum</th><th>Summe</th><th>Status</th><th>Zahlung</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($custOrders as $o): ?>
      <tr>
        <td data-label="Referenz"><strong><?= h($o['reference']) ?></strong></td>
        <td data-label="Datum" class="muted"><?= h(substr($o['created_at'], 0, 16)) ?></td>
        <td data-label="Summe"><?= format_price((int)$o['total_cents'], $currency) ?></td>
        <td data-label="Status"><span class="tag"><?= h($o['status']) ?></span></td>
        <td data-label="Zahlung"><span class="tag <?= order_payment_class($o) ?>"><?= h(order_payment_label($o)) ?></span></td>
        <td class="cell-actions"><a class="btn btn-ghost btn-sm" href="<?= url('/admin/bestellung.php?ref=' . urlencode($o['reference'])) ?>">Details</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
