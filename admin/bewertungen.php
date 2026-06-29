<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_cap('reviews.manage');
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && $action === 'approve') review_set_approved($id, true);
    if ($id && $action === 'unapprove') review_set_approved($id, false);
    if ($id && $action === 'delete') review_delete($id);
    redirect('/admin/bewertungen.php' . (!empty($_POST['filter']) ? '?filter=' . urlencode($_POST['filter']) : ''));
}

$adminTitle = 'Bewertungen';
include __DIR__ . '/partials/admin-layout-top.php';

$filter  = $_GET['filter'] ?? 'pending';
$reviews = $filter === 'all' ? reviews_list_admin() : reviews_list_admin($filter === 'approved' ? 1 : 0);
$pending = reviews_pending_count();
?>
<p class="admin-kicker">Katalog</p>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Bewertungen</h1></div>

<div class="chip-row" style="margin-bottom:1.4rem">
  <a class="chip<?= $filter === 'pending' ? ' active' : '' ?>" href="<?= url('/admin/bewertungen.php?filter=pending') ?>">Ausstehend (<?= $pending ?>)</a>
  <a class="chip<?= $filter === 'approved' ? ' active' : '' ?>" href="<?= url('/admin/bewertungen.php?filter=approved') ?>">Freigegeben</a>
  <a class="chip<?= $filter === 'all' ? ' active' : '' ?>" href="<?= url('/admin/bewertungen.php?filter=all') ?>">Alle</a>
</div>

<?php if (empty($reviews)): ?>
  <p class="muted">Keine Bewertungen in dieser Ansicht.</p>
<?php else: foreach ($reviews as $r): ?>
  <div class="message-card <?= $r['is_approved'] ? '' : 'message-unread' ?>">
    <div class="message-meta">
      <strong><?= h($r['author']) ?></strong>
      <span style="color:#b89c67;letter-spacing:2px"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
      <?php if ($r['product_name']): ?>
        <a href="<?= url('/produkt.php?slug=' . urlencode($r['slug'])) ?>" target="_blank" rel="noopener"><?= h($r['product_name']) ?></a>
      <?php else: ?>
        <span class="muted">Produkt gelöscht</span>
      <?php endif; ?>
      <span class="muted"><?= h(substr($r['created_at'], 0, 16)) ?></span>
      <span class="tag <?= $r['is_approved'] ? 'tag-ok' : 'tag-warn' ?>"><?= $r['is_approved'] ? 'freigegeben' : 'ausstehend' ?></span>
    </div>
    <p><?= nl2br(h($r['text'])) ?></p>
    <div style="display:flex;gap:.5rem">
      <?php if (!$r['is_approved']): ?>
      <form method="post" data-cap="reviews.manage">
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="filter" value="<?= h($filter) ?>">
        <button class="btn btn-primary btn-sm" type="submit">Freigeben</button>
      </form>
      <?php else: ?>
      <form method="post" data-cap="reviews.manage">
        <input type="hidden" name="action" value="unapprove">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="filter" value="<?= h($filter) ?>">
        <button class="btn btn-ghost btn-sm" type="submit">Verbergen</button>
      </form>
      <?php endif; ?>
      <form method="post" data-cap="reviews.manage" onsubmit="return confirm('Bewertung löschen?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <input type="hidden" name="filter" value="<?= h($filter) ?>">
        <button class="btn btn-danger btn-sm" type="submit">Löschen</button>
      </form>
    </div>
  </div>
<?php endforeach; endif; ?>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
