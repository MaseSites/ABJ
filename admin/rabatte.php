<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_cap('discounts.manage');
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        discount_delete((int)($_POST['id'] ?? 0));
        redirect('/admin/rabatte.php?saved=1');
    }
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('UPDATE discount_codes SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        redirect('/admin/rabatte.php');
    }
    if ($action === 'create') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = in_array($_POST['type'] ?? '', ['percent','fixed','free_shipping']) ? $_POST['type'] : 'percent';
        $valueRaw = str_replace(',', '.', trim($_POST['value'] ?? '0'));
        $value = $type === 'percent' ? (int)$valueRaw : (int)round((float)$valueRaw * 100);
        $minRaw = str_replace(',', '.', trim($_POST['min_order'] ?? '0'));
        if (!preg_match('/^[A-Z0-9\-_]{2,40}$/', $code)) {
            $err = 'Code: nur Buchstaben, Zahlen, - und _ (2-40 Zeichen).';
        } elseif (discount_by_code($code)) {
            $err = 'Dieser Code existiert bereits.';
        } elseif ($type === 'percent' && ($value < 1 || $value > 100)) {
            $err = 'Prozentwert muss zwischen 1 und 100 liegen.';
        } else {
            discount_create([
                'code' => $code, 'type' => $type, 'value' => $value,
                'min_order_cents' => (int)round((float)$minRaw * 100),
                'max_uses'    => (int)($_POST['max_uses'] ?? 0),
                'valid_until' => trim($_POST['valid_until'] ?? ''),
                'is_active'   => 1,
            ]);
            redirect('/admin/rabatte.php?saved=1');
        }
    }
}

$adminTitle = 'Rabattcodes';
include __DIR__ . '/partials/admin-layout-top.php';

$codes    = discounts_list();
$currency = setting_get('currency') ?: 'CHF';

function discount_value_label(array $c, string $currency): string {
    if ($c['type'] === 'percent') return '-' . (int)$c['value'] . ' %';
    if ($c['type'] === 'fixed')   return '-' . format_price((int)$c['value'], $currency);
    return 'Gratisversand';
}
?>
<p class="admin-kicker">Marketing</p>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Rabattcodes</h1></div>

<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok">Gespeichert.</div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>

<div class="admin-section">
  <h2>Neuen Code anlegen</h2>
  <form method="post" class="admin-form discount-form" data-cap="discounts.manage">
    <input type="hidden" name="action" value="create">
    <div class="form-row-3">
      <label class="field"><span>Code *</span><input type="text" name="code" required maxlength="40" placeholder="SOMMER20" style="text-transform:uppercase"></label>
      <label class="field"><span>Typ</span>
        <select name="type">
          <option value="percent">Prozent (%)</option>
          <option value="fixed">Fester Betrag (<?= h($currency) ?>)</option>
          <option value="free_shipping">Gratisversand</option>
        </select>
      </label>
      <label class="field"><span>Wert (% oder Betrag)</span><input type="text" name="value" placeholder="20" inputmode="decimal"></label>
    </div>
    <div class="form-row-3">
      <label class="field"><span>Mindestbestellwert (<?= h($currency) ?>, 0 = keiner)</span><input type="text" name="min_order" placeholder="0" inputmode="decimal"></label>
      <label class="field"><span>Max. Einlösungen (0 = unbegrenzt)</span><input type="number" name="max_uses" min="0" value="0"></label>
      <label class="field"><span>Gültig bis (leer = unbegrenzt)</span><input type="date" name="valid_until"></label>
    </div>
    <button class="btn btn-primary" type="submit" style="align-self:flex-start">Code anlegen</button>
  </form>
</div>

<div class="admin-section">
  <h2>Bestehende Codes (<?= count($codes) ?>)</h2>
  <?php if (empty($codes)): ?>
    <p class="muted">Noch keine Rabattcodes angelegt.</p>
  <?php else: ?>
  <table class="data-table">
    <thead><tr><th>Code</th><th>Rabatt</th><th>Mindestbestellwert</th><th>Eingelöst</th><th>Gültig bis</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($codes as $c): ?>
      <tr>
        <td><strong style="letter-spacing:.06em"><?= h($c['code']) ?></strong></td>
        <td><?= h(discount_value_label($c, $currency)) ?></td>
        <td><?= (int)$c['min_order_cents'] > 0 ? format_price((int)$c['min_order_cents'], $currency) : '-' ?></td>
        <td><?= (int)$c['used_count'] ?><?= (int)$c['max_uses'] > 0 ? ' / ' . (int)$c['max_uses'] : '' ?></td>
        <td><?= $c['valid_until'] ? h(substr($c['valid_until'], 0, 10)) : '-' ?></td>
        <td><span class="tag <?= $c['is_active'] ? 'tag-ok' : 'tag-off' ?>"><?= $c['is_active'] ? 'aktiv' : 'inaktiv' ?></span></td>
        <td style="white-space:nowrap;display:flex;gap:.4rem">
          <form method="post" data-cap="discounts.manage">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-ghost btn-sm" type="submit"><?= $c['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?></button>
          </form>
          <form method="post" data-cap="discounts.manage" onsubmit="return confirm('Code <?= h($c['code']) ?> löschen?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">Löschen</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
