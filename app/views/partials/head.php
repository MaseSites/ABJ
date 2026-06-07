<?php
$settings = $settings ?? [];
$shopName = $settings['shop_name'] ?? 'ABJ Shop';
$currency = $settings['currency'] ?? 'CHF';
$announcement = $settings['announcement'] ?? '';
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(($title ?? $shopName) . ' - ' . $shopName) ?></title>
  <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
<?php if ($announcement): ?>
  <div class="announcement"><?= htmlspecialchars($announcement) ?></div>
<?php endif; ?>
<header class="site-header">
  <a class="brand" href="?route=home"><?= htmlspecialchars($shopName) ?></a>
  <nav class="site-nav">
    <a href="?route=shop">Shop</a>
    <a href="?route=cart">Warenkorb</a>
    <a href="?route=contact">Kontakt</a>
    <a href="?route=admin">Admin</a>
  </nav>
</header>
<main class="page">
