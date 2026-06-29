<?php
require_once __DIR__ . '/../lib/bootstrap.php';
if (is_admin()) redirect('/admin/index.php');

$error = false;
$locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!login_throttle_allowed('admin', 8, 15)) {
        $locked = true;
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_throttle_clear('admin');
            admin_login((int)$user['id'], $user['username'], $user['role'] ?? 'root');
            redirect('/admin/index.php');
        }
        login_throttle_hit('admin');
        $error = true;
    }
}
$shopName = setting_get('shop_name') ?: 'ABJ Store';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
  <?= csrf_meta() ?>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin Login – <?= h($shopName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="<?= url('/css/styles.css') ?>?v=44">
  <link rel="stylesheet" href="<?= url('/css/admin.css') ?>?v=44">
</head>
<body>
<main class="gate-wrap">
  <div class="gate-card">
    <p class="admin-kicker"><?= h($shopName) ?></p>
    <h1>Admin-Login</h1>
    <p class="muted" style="font-size:.9rem">Melde dich an, um das Dashboard zu öffnen.</p>
    <?php if ($locked): ?>
      <div class="alert alert-error" style="margin-top:1rem">Zu viele Login-Versuche. Bitte warte etwa 15 Minuten und versuche es erneut.</div>
    <?php elseif ($error): ?>
      <div class="alert alert-error" style="margin-top:1rem">Benutzername oder Passwort falsch.</div>
    <?php endif; ?>
    <form method="post" action="<?= url('/admin/login.php') ?>" class="gate-form">
      <label class="field">
        <span>Benutzername</span>
        <input type="text" name="username" autocomplete="username" required autofocus placeholder="admin">
      </label>
      <label class="field">
        <span>Passwort</span>
        <input type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
      </label>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top:.4rem">Anmelden</button>
    </form>
  </div>
</main>
</body>
</html>
