<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    db()->prepare('DELETE FROM newsletter WHERE id=?')->execute([(int)$_POST['delete_id']]);
    redirect('/admin/newsletter.php');
}

$adminTitle = 'Newsletter';
include __DIR__ . '/partials/admin-layout-top.php';

$subs = db()->query('SELECT * FROM newsletter ORDER BY created_at DESC')->fetchAll();
?>
<div class="admin-header">
  <h1>Newsletter (<?= count($subs) ?>)</h1>
</div>

<table class="admin-table">
  <thead><tr><th>E-Mail</th><th>Datum</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($subs as $s): ?>
    <tr>
      <td><?= h($s['email']) ?></td>
      <td><?= h(substr($s['created_at'], 0, 16)) ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Löschen?')">
          <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
          <button class="btn btn-ghost btn-sm btn-danger" type="submit">Löschen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
