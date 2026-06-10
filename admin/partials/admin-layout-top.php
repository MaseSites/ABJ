<?php
require_admin();
$adminPageTitle = isset($adminTitle) ? $adminTitle . ' – Admin' : 'Admin – ABJ Store';
$_cur = strtolower(basename($_SERVER['PHP_SELF'], '.php'));
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
<body>
<div class="admin-shell">

  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <a href="/admin/">
        <span class="sidebar-logo-name"><?= h(setting_get('shop_name') ?: 'ABJ') ?></span>
        <span class="sidebar-logo-sub">Admin</span>
      </a>
    </div>

    <nav class="sidebar-nav">
      <span class="sidebar-section-label">Navigation</span>

      <a href="/admin/" class="<?= ($_cur === 'index') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="1" width="6" height="6" rx="1.5"/><rect x="9" y="1" width="6" height="6" rx="1.5"/>
          <rect x="1" y="9" width="6" height="6" rx="1.5"/><rect x="9" y="9" width="6" height="6" rx="1.5"/>
        </svg>
        Übersicht
      </a>

      <a href="/admin/produkte.php" class="<?= str_has($_cur, 'produkt') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 4l1.5-2.5h9L14 4"/><rect x="1" y="4" width="14" height="10" rx="1.5"/>
          <path d="M5 4v1.5a3 3 0 006 0V4"/>
        </svg>
        Produkte
      </a>

      <a href="/admin/lager.php" class="<?= str_has($_cur, 'lager') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 6l6-4 6 4v8H2V6z"/><path d="M5.5 14V9.5h5V14"/>
        </svg>
        Lager
      </a>

      <a href="/admin/bestellungen.php" class="<?= str_has($_cur, 'bestellung') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1.5 1.5h2l1.8 8h7.4l1.3-5.5H4.5"/>
          <circle cx="6.5" cy="13" r="1"/><circle cx="12" cy="13" r="1"/>
        </svg>
        Bestellungen
      </a>

      <a href="/admin/nachrichten.php" class="<?= str_has($_cur, 'nachricht') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 10.5a1.5 1.5 0 01-1.5 1.5H4L1.5 14.5V2.5A1.5 1.5 0 013 1h10.5A1.5 1.5 0 0115 2.5v8z"/>
        </svg>
        Nachrichten
      </a>

      <a href="/admin/newsletter.php" class="<?= str_has($_cur, 'newsletter') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="3" width="14" height="10" rx="1.5"/><path d="M1 3l7 5.5L15 3"/>
        </svg>
        Newsletter
      </a>

      <a href="/admin/einstellungen.php" class="<?= str_has($_cur, 'einstell') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="8" cy="8" r="2.5"/>
          <path d="M8 1v1.5M8 13.5V15M1 8h1.5M13.5 8H15M3.05 3.05l1.06 1.06M11.89 11.89l1.06 1.06M12.95 3.05l-1.06 1.06M4.11 11.89l-1.06 1.06"/>
        </svg>
        Einstellungen
      </a>

      <hr style="border:none;border-top:1px solid rgba(255,255,255,.06);margin:.7rem .9rem">

      <a href="/" target="_blank" rel="noopener">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M7 2H2.5A1.5 1.5 0 001 3.5v10A1.5 1.5 0 002.5 15h10a1.5 1.5 0 001.5-1.5V9"/>
          <path d="M10 1h5v5M15 1L7.5 8.5"/>
        </svg>
        Shop ansehen
      </a>
    </nav>

    <div class="sidebar-footer">
      <span class="sidebar-footer-user">Angemeldet als <strong><?= h($_SESSION['admin_username'] ?? 'admin') ?></strong></span>
      <form method="post" action="/admin/logout.php">
        <button class="sidebar-logout" type="submit">Abmelden</button>
      </form>
    </div>
  </aside>

  <div class="admin-content">
    <main class="admin-main">
