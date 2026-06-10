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
  <link rel="stylesheet" href="/css/theme.css.php">
</head>
<body>
