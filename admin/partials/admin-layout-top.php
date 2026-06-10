<?php
require_admin();
$adminPageTitle = isset($adminTitle) ? $adminTitle . ' – Admin' : 'Admin – ABJ Store';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($adminPageTitle) ?></title>
  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-logo">
      <a href="/" class="brand">ABJ</a>
    </div>
    <nav class="admin-nav">
      <?php
      $cur = basename($_SERVER['PHP_SELF']);
      $dir = basename(dirname($_SERVER['PHP_SELF']));
      function alink($href, $label, $cur, $match) {
          $active = str_has($cur, $match) || str_has($href, $cur) ? ' active' : '';
          echo "<a href=\"$href\" class=\"admin-nav-link$active\">$label</a>";
      }
      ?>
      <a href="/admin/" class="admin-nav-link<?= $cur === 'index.php' && $dir === 'admin' ? ' active' : '' ?>">Dashboard</a>
      <a href="/admin/produkte.php" class="admin-nav-link<?= str_has($cur, 'produkt') ? ' active' : '' ?>">Produkte</a>
      <a href="/admin/bestellungen.php" class="admin-nav-link<?= str_has($cur, 'bestellung') ? ' active' : '' ?>">Bestellungen</a>
      <a href="/admin/lager.php" class="admin-nav-link<?= str_has($cur, 'lager') ? ' active' : '' ?>">Lager</a>
      <a href="/admin/nachrichten.php" class="admin-nav-link<?= str_has($cur, 'nachricht') ? ' active' : '' ?>">Nachrichten</a>
      <a href="/admin/newsletter.php" class="admin-nav-link<?= str_has($cur, 'newsletter') ? ' active' : '' ?>">Newsletter</a>
      <a href="/admin/einstellungen.php" class="admin-nav-link<?= str_has($cur, 'einstell') ? ' active' : '' ?>">Einstellungen</a>
      <a href="/admin/logout.php" class="admin-nav-link" style="margin-top:auto">Abmelden</a>
    </nav>
  </aside>
  <div class="admin-content">
