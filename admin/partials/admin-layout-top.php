<?php
require_admin();
$adminPageTitle = isset($adminTitle) ? $adminTitle . ' – Admin' : 'Admin – ABJ Store';
$_cur = strtolower(basename($_SERVER['PHP_SELF'], '.php'));

// Badges
$_newOrders   = 0;
$_unreadMsgs  = 0;
$_pendingRevs = 0;
try {
    $_newOrders   = (int)db()->query("SELECT COUNT(*) AS n FROM orders WHERE is_seen=0")->fetch()['n'];
    $_unreadMsgs  = messages_unread_count();
    $_pendingRevs = reviews_pending_count();
} catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="de" data-base-path="<?= h(base_path()) ?>">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($adminPageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="<?= url('/css/styles.css') ?>?v=31">
  <link rel="stylesheet" href="<?= url('/css/admin.css') ?>?v=31">
</head>
<body>
<div class="admin-shell">

  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <a href="<?= url('/admin/index.php') ?>">
        <span class="sidebar-logo-name"><?= h(setting_get('shop_name') ?: 'ABJ') ?></span>
        <span class="sidebar-logo-sub">Admin</span>
      </a>
    </div>

    <nav class="sidebar-nav">
      <span class="sidebar-section-label">Übersicht</span>

      <a href="<?= url('/admin/index.php') ?>" class="<?= ($_cur === 'index') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="1" width="6" height="6" rx="1.5"/><rect x="9" y="1" width="6" height="6" rx="1.5"/>
          <rect x="1" y="9" width="6" height="6" rx="1.5"/><rect x="9" y="9" width="6" height="6" rx="1.5"/>
        </svg>
        Dashboard
      </a>

      <a href="<?= url('/admin/analytics.php') ?>" class="<?= str_has($_cur, 'analytics') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1.5 14.5h13"/><path d="M3 11l3-3.5 2.5 2L13 5"/><circle cx="13" cy="5" r="1.2"/>
        </svg>
        Analytics
      </a>

      <a href="<?= url('/admin/finanzen.php') ?>" class="<?= str_has($_cur, 'finanzen') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="8" cy="8" r="6.5"/><path d="M8 4.5v7M6.2 6.3h2.4a1.3 1.3 0 010 2.5H6.2M6.2 9.4h2.6"/>
        </svg>
        Finanzen
      </a>

      <a href="<?= url('/admin/bestellungen.php') ?>" class="<?= str_has($_cur, 'bestellung') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1.5 1.5h2l1.8 8h7.4l1.3-5.5H4.5"/>
          <circle cx="6.5" cy="13" r="1"/><circle cx="12" cy="13" r="1"/>
        </svg>
        Bestellungen
        <?php if ($_newOrders > 0): ?><span class="nav-badge"><?= $_newOrders ?></span><?php endif; ?>
      </a>

      <a href="<?= url('/admin/kunden.php') ?>" class="<?= str_has($_cur, 'kunden') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="6" cy="5" r="2.6"/><path d="M1.5 14c.5-2.8 2.3-4.2 4.5-4.2s4 1.4 4.5 4.2"/>
          <path d="M11 3.2a2.6 2.6 0 010 4.6M12.5 9.9c1.2.6 2 1.8 2.3 3.6"/>
        </svg>
        Kunden
      </a>

      <span class="sidebar-section-label">Katalog</span>

      <a href="<?= url('/admin/produkte.php') ?>" class="<?= str_has($_cur, 'produkt') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 4l1.5-2.5h9L14 4"/><rect x="1" y="4" width="14" height="10" rx="1.5"/>
          <path d="M5 4v1.5a3 3 0 006 0V4"/>
        </svg>
        Produkte
      </a>

      <a href="<?= url('/admin/lager.php') ?>" class="<?= str_has($_cur, 'lager') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 6l6-4 6 4v8H2V6z"/><path d="M5.5 14V9.5h5V14"/>
        </svg>
        Lager
      </a>

      <a href="<?= url('/admin/preisrechner.php') ?>" class="<?= str_has($_cur, 'preisrechner') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2.5" y="1" width="11" height="14" rx="1.5"/><path d="M5 4h6"/><path d="M5 7h.01M8 7h.01M11 7h.01M5 10h.01M8 10h.01M11 10v2.5"/>
        </svg>
        Preisrechner
      </a>

      <a href="<?= url('/admin/bewertungen.php') ?>" class="<?= str_has($_cur, 'bewertung') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8 1.5l2 4 4.4.6-3.2 3 .8 4.4L8 11.4l-4 2.1.8-4.4-3.2-3L6 5.5z"/>
        </svg>
        Bewertungen
        <?php if ($_pendingRevs > 0): ?><span class="nav-badge"><?= $_pendingRevs ?></span><?php endif; ?>
      </a>

      <span class="sidebar-section-label">Marketing</span>

      <a href="<?= url('/admin/rabatte.php') ?>" class="<?= str_has($_cur, 'rabatt') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M2 8.5V2h6.5L15 8.5 8.5 15 2 8.5z"/><circle cx="5.5" cy="5.5" r="1"/>
        </svg>
        Rabattcodes
      </a>

      <a href="<?= url('/admin/newsletter.php') ?>" class="<?= str_has($_cur, 'newsletter') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="3" width="14" height="10" rx="1.5"/><path d="M1 3l7 5.5L15 3"/>
        </svg>
        Newsletter
      </a>

      <a href="<?= url('/admin/nachrichten.php') ?>" class="<?= str_has($_cur, 'nachricht') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 10.5a1.5 1.5 0 01-1.5 1.5H4L1.5 14.5V2.5A1.5 1.5 0 013 1h10.5A1.5 1.5 0 0115 2.5v8z"/>
        </svg>
        Nachrichten
        <?php if ($_unreadMsgs > 0): ?><span class="nav-badge"><?= $_unreadMsgs ?></span><?php endif; ?>
      </a>

      <span class="sidebar-section-label">System</span>

      <a href="<?= url('/admin/einstellungen.php') ?>" class="<?= str_has($_cur, 'einstell') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="8" cy="8" r="2.5"/>
          <path d="M8 1v1.5M8 13.5V15M1 8h1.5M13.5 8H15M3.05 3.05l1.06 1.06M11.89 11.89l1.06 1.06M12.95 3.05l-1.06 1.06M4.11 11.89l-1.06 1.06"/>
        </svg>
        Einstellungen
      </a>

      <a href="<?= url('/admin/sicherheit.php') ?>" class="<?= str_has($_cur, 'sicherheit') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8 1.5l5.5 2.2V7c0 3.5-2.3 6-5.5 7-3.2-1-5.5-3.5-5.5-7V3.7z"/><path d="M6 8l1.5 1.5L10.5 6.5"/>
        </svg>
        Sicherheit
      </a>

      <a href="<?= url('/') ?>" target="_blank" rel="noopener">
        <svg class="nav-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M7 2H2.5A1.5 1.5 0 001 3.5v10A1.5 1.5 0 002.5 15h10a1.5 1.5 0 001.5-1.5V9"/>
          <path d="M10 1h5v5M15 1L7.5 8.5"/>
        </svg>
        Shop ansehen
      </a>
    </nav>

    <div class="sidebar-footer">
      <span class="sidebar-footer-user">Angemeldet als <strong><?= h($_SESSION['admin_username'] ?? 'admin') ?></strong></span>
      <form method="post" action="<?= url('/admin/logout.php') ?>">
        <button class="sidebar-logout" type="submit">Abmelden</button>
      </form>
    </div>
  </aside>

  <div class="admin-overlay" data-admin-overlay></div>

  <div class="admin-content">
    <header class="admin-topbar">
      <button class="admin-burger" data-admin-burger aria-label="Menü öffnen" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
      <a class="admin-topbar-brand" href="<?= url('/admin/index.php') ?>">
        <strong><?= h(setting_get('shop_name') ?: 'ABJ') ?></strong>
        <span><?= h($adminTitle ?? 'Admin') ?></span>
      </a>
    </header>
    <main class="admin-main">
