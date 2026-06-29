<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_cap('messages.manage');
    db()->prepare('DELETE FROM messages WHERE id=?')->execute([(int)$_POST['delete_id']]);
    redirect('/admin/nachrichten.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['read_id'])) {
    require_cap('messages.manage');
    db()->prepare('UPDATE messages SET is_read=1 WHERE id=?')->execute([(int)$_POST['read_id']]);
    redirect('/admin/nachrichten.php');
}
$adminTitle = 'Nachrichten';
include __DIR__ . '/partials/admin-layout-top.php';

$msgs = db()->query('SELECT * FROM messages ORDER BY created_at DESC')->fetchAll();
?>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Nachrichten</h1></div>

<?php if (empty($msgs)): ?>
  <p class="muted">Keine Nachrichten.</p>
<?php else: foreach ($msgs as $m): ?>
  <div class="message-card <?= $m['is_read'] ? '' : 'message-unread' ?>">
    <div class="message-meta">
      <strong><?= h($m['name']) ?></strong>
      <a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a>
      <span class="muted"><?= h(substr($m['created_at'], 0, 16)) ?></span>
    </div>
    <p><?= nl2br(h($m['message'])) ?></p>
    <div style="display:flex;gap:.5rem">
      <?php if (!$m['is_read']): ?>
      <form method="post"><input type="hidden" name="read_id" value="<?= $m['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">Als gelesen markieren</button></form>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('Löschen?')"><input type="hidden" name="delete_id" value="<?= $m['id'] ?>"><button class="btn btn-ghost btn-sm btn-danger" type="submit">Löschen</button></form>
    </div>
  </div>
<?php endforeach; endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
