<?php
require_once __DIR__ . '/../lib/bootstrap.php';
if (is_admin()) redirect('/admin/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw   = $_POST['password'] ?? '';
    $hash = setting_get('gate_password_hash') ?: '';
    if ($hash && password_verify($pw, $hash)) {
        admin_login($hash);
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
<body class="admin-login-page">
<main class="login-wrap">
  <div class="login-card">
    <h1>Admin Login</h1>
    <?php if (!empty($error)): ?>
      <div class="alert alert-error">Falsches Passwort.</div>
    <?php endif; ?>
    <form method="post" action="/admin/login.php">
      <label class="field"><span>Passwort</span>
        <input type="password" name="password" autofocus required>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Anmelden</button>
    </form>
  </div>
</main>
</body>
</html>
