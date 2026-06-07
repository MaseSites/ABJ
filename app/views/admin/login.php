<?php include APP_PATH . '/views/partials/head.php'; ?>
<section class="panel narrow">
  <h1>Admin Login</h1>
  <?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <input name="username" placeholder="Benutzername" required>
    <input name="password" type="password" placeholder="Passwort" required>
    <button class="button" type="submit">Anmelden</button>
  </form>
</section>
<?php include APP_PATH . '/views/partials/footer.php'; ?>
