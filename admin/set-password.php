<?php
// Einmaliger Helfer um das erste Admin-Passwort zu setzen.
// NACH BENUTZUNG DIESE DATEI LÖSCHEN!
require_once __DIR__ . '/../lib/bootstrap.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = trim($_POST['password'] ?? '');
    if (strlen($pw) >= 8) {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        setting_set('gate_password_hash', $hash);
        $msg = 'Passwort gesetzt! Bitte diese Datei jetzt löschen.';
    } else {
        $msg = 'Passwort muss mindestens 8 Zeichen lang sein.';
    }
}
?>
<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Passwort setzen</title>
<style>body{font-family:sans-serif;max-width:400px;margin:3rem auto;padding:1rem}input,button{display:block;width:100%;margin:.5rem 0;padding:.5rem}</style>
</head><body>
<h1>Admin-Passwort setzen</h1>
<?php if ($msg): ?><p style="color:green"><?= h($msg) ?></p><?php endif; ?>
<form method="post">
  <input type="password" name="password" placeholder="Neues Passwort (min. 8 Zeichen)" required minlength="8">
  <button type="submit">Setzen</button>
</form>
</body></html>
