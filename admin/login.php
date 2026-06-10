<?php
require_once __DIR__ . '/../lib/bootstrap.php';
if (is_admin()) redirect('/admin/');

$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        admin_login((int)$user['id'], $user['username']);
        redirect('/admin/');
    }
    $error = true;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login – ABJ Store</title>
  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<main class="gate-wrap">
  <div class="gate-card">
    <p class="admin-kicker">ABJ Store</p>
    <h1>Admin-Login</h1>
    <p class="muted">Melde dich an, um das Dashboard zu öffnen.</p>
    <?php if ($error): ?>
      <div class="alert alert-error">Benutzername oder Passwort falsch.</div>
    <?php endif; ?>
    <form method="post" action="/admin/login.php" class="gate-form">
      <div class="field">
        <span>Benutzername</span>
        <input type="text" name="username" autocomplete="username" required autofocus placeholder="admin">
      </div>
      <div class="field">
        <span>Passwort</span>
        <input type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
      </div>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top:.4rem">Anmelden</button>
    </form>
  </div>
</main>
</body>
</html>
