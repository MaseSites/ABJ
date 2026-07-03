<?php
$shopName  = setting_get('shop_name') ?: 'ABJ Store';
$tagline   = setting_get('tagline') ?: '';
$pageTitle = isset($pageTitle) ? $pageTitle . ' – ' . $shopName : $shopName . ($tagline ? ' – ' . $tagline : '');
$customer = is_customer() ? current_customer() : null;
$customerAccount = $customer ? account_by_id((int)$customer['id']) : null;
$customerNeedsActivation = $customerAccount ? !account_is_confirmed($customerAccount) : false;
$bodyClasses = trim(($bodyClasses ?? '') . ' ' . ($customerNeedsActivation ? 'has-activation-bar' : ''));
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
  <link rel="stylesheet" href="<?= url('/css/styles.css') ?>?v=45">
  <style>
  <?php
  $accent  = setting_get('accent')  ?: '#B89C67';
  $accent2 = setting_get('accent_2') ?: '#B89C67';
  $accent3 = setting_get('accent_3') ?: '#CDB27E';
  echo ":root{--accent:$accent;--accent-2:$accent2;--accent-3:$accent3;--gold:$accent;}";
  ?>
  </style>
</head>
<body<?= $bodyClasses !== '' ? ' class="' . h($bodyClasses) . '"' : '' ?>>
<?php if ($customerNeedsActivation): ?>
<div class="activation-strip" role="note" aria-label="Konto aktivieren">
  <div class="container activation-strip-inner">
    <div class="activation-strip-left">
      <span class="activation-strip-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01"/><path d="M10.3 4.4 2.8 18a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3l-7.5-13.6a2 2 0 0 0-3.4 0z"/></svg>
      </span>
      <span class="activation-strip-text"><strong>Achtung!</strong> Konto aktivieren</span>
    </div>
    <form class="activation-strip-form" method="post" action="<?= url('/konto.php') ?>">
      <input type="hidden" name="action" value="activate_code">
      <label class="sr-only" for="activation-code">Aktivierungscode</label>
      <input id="activation-code" type="text" name="access_code" maxlength="20" autocomplete="off" placeholder="Code">
      <button class="btn btn-primary btn-sm" type="submit">Konto aktivieren</button>
    </form>
  </div>
</div>
<?php endif; ?>
