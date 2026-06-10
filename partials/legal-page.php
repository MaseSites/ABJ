<?php
// expects $legalKey (string) and $pageTitle (string)
require_once __DIR__ . '/../lib/legal.php';
$_s    = settings_all();
$_pages = legal_pages($_s);
$_page  = $_pages[$legalKey] ?? null;
$_title = $_page['title'] ?? $pageTitle;

$cartCount   = cart_count();
$currentPath = '/' . $legalKey;
$pageTitle   = $_title;
include __DIR__ . '/head.php';
include __DIR__ . '/header.php';
?>
<main id="main" class="container section narrow">
  <h1><?= h($_title) ?></h1>
  <?php if ($_page): foreach ($_page['sections'] as $sec): ?>
    <h2 style="margin-top:2rem;font-size:1rem"><?= h($sec['h']) ?></h2>
    <p style="white-space:pre-line;color:var(--ink-soft)"><?= h($sec['body']) ?></p>
  <?php endforeach; endif; ?>
</main>
<?php include __DIR__ . '/footer.php'; ?>
