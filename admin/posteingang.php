<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$adminTitle = 'Posteingang';
include __DIR__ . '/partials/admin-layout-top.php';

$rows = db()->query("
    SELECT om.*, o.customer_name, o.email
    FROM order_messages om
    LEFT JOIN orders o ON o.reference = om.order_reference
    ORDER BY om.created_at DESC, om.id DESC
    LIMIT 500
")->fetchAll();
?>
<div class="admin-head-row" style="margin-bottom:1.4rem">
  <h1>Posteingang</h1>
</div>

<?php if (empty($rows)): ?>
  <p class="muted">Noch keine Bestellnachrichten.</p>
<?php else: foreach ($rows as $m): ?>
  <div class="message-card <?= !empty($m['is_read']) ? '' : 'message-unread' ?>">
    <div class="message-meta">
      <strong><?= h($m['subject'] ?: ($m['is_system'] ? 'System' : 'Nachricht')) ?></strong>
      <a href="<?= url('/admin/bestellung.php?ref=' . urlencode($m['order_reference'])) ?>"><?= h($m['order_reference']) ?></a>
      <span class="muted"><?= h($m['author_name'] ?: $m['author_role']) ?></span>
      <span class="muted"><?= h(substr($m['created_at'], 0, 16)) ?></span>
    </div>
    <p><?= nl2br(h($m['body'])) ?></p>
    <p class="muted" style="font-size:.85rem">
      <?= h($m['customer_name'] ?? '') ?><?= !empty($m['email']) ? ' - ' . h($m['email']) : '' ?>
    </p>
  </div>
<?php endforeach; endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
