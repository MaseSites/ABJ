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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_ref']) && isset($_POST['order_body'])) {
    require_cap('orders.manage');
    order_message_create([
        'order_reference' => trim($_POST['order_ref']),
        'author_role' => 'admin',
        'author_name' => 'ABJ Team',
        'subject' => trim($_POST['order_subject'] ?? ''),
        'body' => trim($_POST['order_body'] ?? ''),
        'is_system' => 0,
        'is_read' => 0,
    ]);
    redirect('/admin/nachrichten.php');
}

$adminTitle = 'Nachrichten';
include __DIR__ . '/partials/admin-layout-top.php';

$msgs = db()->query('SELECT * FROM messages ORDER BY created_at DESC')->fetchAll();
$orders = db()->query('SELECT om.*, o.customer_name, o.email FROM order_messages om LEFT JOIN orders o ON o.reference = om.order_reference ORDER BY om.created_at DESC, om.id DESC LIMIT 200')->fetchAll();
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

<div class="admin-head-row" style="margin:2rem 0 1rem"><h2>Bestell-Posteingang</h2></div>
<?php if (empty($orders)): ?>
  <p class="muted">Keine Bestellnachrichten.</p>
<?php else: foreach ($orders as $m): ?>
  <div class="message-card <?= !empty($m['is_read']) ? '' : 'message-unread' ?>">
    <div class="message-meta">
      <strong><?= h($m['subject'] ?: ($m['is_system'] ? 'System' : 'Nachricht')) ?></strong>
      <a href="<?= url('/admin/bestellung.php?ref=' . urlencode($m['order_reference'])) ?>"><?= h($m['order_reference']) ?></a>
      <span class="muted"><?= h($m['author_name'] ?: $m['author_role']) ?></span>
      <span class="muted"><?= h(substr($m['created_at'], 0, 16)) ?></span>
    </div>
    <p><?= nl2br(h($m['body'])) ?></p>
    <p class="muted" style="font-size:.85rem"><?= h($m['customer_name'] ?? '') ?><?= !empty($m['email']) ? ' · ' . h($m['email']) : '' ?></p>
  </div>
<?php endforeach; endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
