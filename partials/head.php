<?php
$shopName = setting_get('shop_name') ?: 'ABJ Store';
$pageTitle = isset($pageTitle) ? $pageTitle . ' – ' . $shopName : $shopName;
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#08090e">
  <title><?= h($pageTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
  <link rel="stylesheet" href="/css/styles.css">
  <style>
  <?php
  $accent  = setting_get('accent')  ?: '#B89C67';
  $accent2 = setting_get('accent_2') ?: '#B89C67';
  $accent3 = setting_get('accent_3') ?: '#CDB27E';
  echo ":root{--accent:$accent;--accent-2:$accent2;--accent-3:$accent3;}";
  ?>
  </style>
</head>
<body>
