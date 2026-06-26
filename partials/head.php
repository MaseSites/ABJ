<?php
$shopName  = setting_get('shop_name') ?: 'ABJ Store';
$tagline   = setting_get('tagline') ?: '';
$pageTitle = isset($pageTitle) ? $pageTitle . ' – ' . $shopName : $shopName . ($tagline ? ' – ' . $tagline : '');
?>
<!DOCTYPE html>
<html lang="de" data-base-path="<?= h(base_path()) ?>">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0a0a0b">
  <meta name="description" content="<?= h($tagline ?: $shopName) ?>">
  <title><?= h($pageTitle) ?></title>
  <link rel="icon" type="image/jpeg" href="<?= url('/img/abj-logo.jpg') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap">
  <link rel="stylesheet" href="<?= url('/css/styles.css') ?>?v=14">
  <style>
  <?php
  $accent  = setting_get('accent')  ?: '#B89C67';
  $accent2 = setting_get('accent_2') ?: '#B89C67';
  $accent3 = setting_get('accent_3') ?: '#CDB27E';
  echo ":root{--accent:$accent;--accent-2:$accent2;--accent-3:$accent3;--gold:$accent;}";
  ?>
  </style>
</head>
<body>
