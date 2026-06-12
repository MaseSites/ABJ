<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    db()->prepare('DELETE FROM newsletter WHERE id=?')->execute([(int)$_POST['delete_id']]);
    redirect('/admin/newsletter.php');
}

$subs = newsletter_list();

// CSV-Export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['E-Mail', 'Angemeldet am'], ';');
    foreach ($subs as $s) fputcsv($out, [$s['email'], substr($s['created_at'], 0, 16)], ';');
    fclose($out);
    exit;
}

$adminTitle = 'Newsletter';
include __DIR__ . '/partials/admin-layout-top.php';
?>
<p class="admin-kicker">Marketing</p>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Newsletter (<?= count($subs) ?>)</h1>
  <a class="btn btn-ghost btn-sm" href="<?= url('/admin/newsletter.php?export=csv') ?>">CSV exportieren</a>
</div>

<?php if (empty($subs)): ?>
  <p class="muted">Noch keine Abonnenten.</p>
<?php else: ?>
<table class="data-table">
  <thead><tr><th>E-Mail</th><th>Datum</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($subs as $s): ?>
    <tr>
      <td><?= h($s['email']) ?></td>
      <td><?= h(substr($s['created_at'], 0, 16)) ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Löschen?')">
          <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">Löschen</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
