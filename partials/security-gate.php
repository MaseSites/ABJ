<?php
/**
 * Tarnseite + 2-Stufen-Zugang für den Sicherheitsmodus.
 *
 * Stufe 1: Besucher gibt seinen Zugangscode ein. Unbekannt -> Fehler (getarnt).
 * Stufe 2: Registrieren (freier Code) oder Anmelden.
 *   - Ein freier Code wird beim Registrieren/Anmelden fest dem Konto zugewiesen.
 *   - Ein bereits zugewiesener Code lässt NUR das zugehörige Konto wieder rein
 *     (z. B. neues Gerät). Wer damit ein NEUES/fremdes Konto nutzt -> IP-Sperre.
 *
 * Die Oberfläche ist neutral gehalten (Einladungs-/Zugangsportal) und gibt
 * keinerlei Hinweis darauf, dass dahinter ein Shop liegt.
 */
session_start_once();

$gateError = '';

/** IP sperren und sofort die Sperr-Seite zeigen (Missbrauch eines Codes). */
if (!function_exists('gate_block_and_exit')) {
    function gate_block_and_exit(): void {
        ip_block(client_ip(), 'Zugangscode für ein fremdes Konto verwendet');
        unset($_SESSION['gate_code']); session_write_close();
        if (!headers_sent()) { http_response_code(403); header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); }
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Zugriff gesperrt</title></head>'
           . '<body style="font-family:system-ui,sans-serif;background:#0d0d12;color:#e8e8ee;display:grid;place-items:center;min-height:100vh;margin:0">'
           . '<div style="text-align:center;padding:2rem;max-width:420px"><h1 style="margin:0 0 .5rem">Zugriff gesperrt</h1>'
           . '<p style="color:#9a9aa5">Dieser Zugangscode gehört zu einem anderen Konto. Deine IP-Adresse wurde gesperrt.</p></div></body></html>';
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (isset($_POST['beleg'])) {
        // Stufe 1 – Code muss existieren (frei ODER bereits vergeben).
        $code = trim((string)$_POST['beleg']);
        $row  = code_find($code);
        if ($row === null) {
            $gateError = 'Dieser Code ist ungültig. Bitte prüfe ihn und versuche es erneut.';
        } else {
            $_SESSION['gate_code'] = $code;
            session_write_close();
            if (!headers_sent()) header('Location: ' . base_path() . '/');
            exit;
        }
    } elseif (isset($_POST['gate_action'])) {
        // Stufe 2
        $code = $_SESSION['gate_code'] ?? '';
        $row  = $code ? code_find($code) : null;
        if ($row === null) {
            unset($_SESSION['gate_code']);
            $gateError = 'Bitte gib deinen Zugangscode erneut ein.';
        } else {
            $assigned = (int)($row['used_by'] ?? 0); // 0 = frei, sonst Konto-ID

            if ($_POST['gate_action'] === 'register') {
                if ($assigned > 0) {
                    // Vergebener Code + neues Konto -> Missbrauch -> sperren
                    gate_block_and_exit();
                }
                $res = account_create(trim($_POST['email'] ?? ''), $_POST['password'] ?? '', trim($_POST['name'] ?? ''));
                if (!empty($res['ok'])) {
                    code_mark_used($code, (int)$res['id']);            // Code fest zuweisen
                    $owner = (int)($row['account_id'] ?? 0);
                    if ($owner > 0) account_set_referrer((int)$res['id'], $owner);
                    customer_login((int)$res['id'], trim($_POST['email'] ?? ''), trim($_POST['name'] ?? ''));
                    ip_allow_add(client_ip(), (int)$res['id']);
                    unset($_SESSION['gate_code']); session_write_close();
                    if (!headers_sent()) header('Location: ' . base_path() . '/');
                    exit;
                }
                $gateError = $res['error'] ?? 'Registrierung fehlgeschlagen.';

            } elseif ($_POST['gate_action'] === 'login') {
                $acc = account_verify_login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
                if (!$acc) {
                    $gateError = 'E-Mail oder Passwort ist falsch.';
                } else {
                    $accId = (int)$acc['id'];
                    if ($assigned > 0 && $assigned !== $accId) {
                        // Vergebener Code + fremdes Konto -> Missbrauch -> sperren
                        gate_block_and_exit();
                    }
                    if ($assigned === 0) code_mark_used($code, $accId); // freien Code zuweisen
                    customer_login($accId, $acc['email'], $acc['name'] ?? '');
                    ip_allow_add(client_ip(), $accId);
                    unset($_SESSION['gate_code']); session_write_close();
                    if (!headers_sent()) header('Location: ' . base_path() . '/');
                    exit;
                }
            }
        }
    }
}

$gateCode = $_SESSION['gate_code'] ?? '';
$gateRow  = $gateCode ? code_find($gateCode) : null;
if ($gateRow === null) { unset($_SESSION['gate_code']); }
$codeUsed = $gateRow && !empty($gateRow['used_by']); // vergebener Code -> nur Login
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Zugang · Anmeldung erforderlich</title>
<style>
  :root { --bd:#e6e9f0; --mut:#697086; --ink:#1c2333; --pri:#3551d1; --pri-d:#2940ad; --tint:#eef1fb; --bg:#f1f3f8; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:-apple-system,"Segoe UI",Roboto,Inter,Arial,sans-serif; color:var(--ink); font-size:15px;
         background:radial-gradient(900px 520px at 50% -10%, #ffffff 0%, var(--bg) 62%); min-height:100vh; min-height:100dvh;
         display:flex; flex-direction:column; align-items:center; justify-content:center; padding:1.5rem 1.1rem; }
  .shell { width:100%; max-width:420px; }
  .card { background:#fff; border:1px solid var(--bd); border-radius:20px;
          box-shadow:0 24px 60px -28px rgba(20,30,70,.4), 0 3px 10px -6px rgba(20,30,70,.18); padding:2.1rem 1.9rem 1.9rem; }
  .badge { width:54px; height:54px; border-radius:15px; background:var(--tint); color:var(--pri);
           display:grid; place-items:center; margin:0 0 1.15rem; }
  .badge svg { width:26px; height:26px; }
  .card h1 { font-size:1.5rem; line-height:1.15; margin:0 0 .55rem; letter-spacing:-.02em; }
  .lead { color:var(--mut); margin:0 0 1.5rem; line-height:1.5; font-size:.93rem; }
  label { display:block; font-size:.78rem; color:var(--ink); font-weight:600; margin:1rem 0 .4rem; }
  label:first-of-type { margin-top:0; }
  label .opt { color:var(--mut); font-weight:400; }
  input { width:100%; border:1px solid #d7dbe6; border-radius:11px; padding:.82rem .95rem; font:inherit; color:var(--ink); background:#fbfcfe; outline:none; transition:border-color .15s, box-shadow .15s; }
  input::placeholder { color:#9aa1ad; }
  input:focus { border-color:var(--pri); box-shadow:0 0 0 3px rgba(53,81,209,.15); background:#fff; }
  .code-input { text-align:center; font-size:1.55rem; font-weight:800; letter-spacing:.4em; text-indent:.4em; text-transform:uppercase; padding:1rem .9rem; }
  .btn { display:flex; width:100%; justify-content:center; align-items:center; gap:.45rem; background:var(--pri); color:#fff; border:none; border-radius:11px; padding:.9rem 1.1rem; font:inherit; font-size:1rem; font-weight:700; cursor:pointer; margin-top:1.4rem; transition:background .15s, transform .05s; }
  .btn:hover { background:var(--pri-d); }
  .btn:active { transform:translateY(1px); }
  .err { margin-top:1rem; background:#fdecec; color:#b42318; border:1px solid #f6cccc; padding:.7rem .9rem; border-radius:11px; font-size:.86rem; }
  .note { margin-top:1rem; background:var(--tint); color:#2b3a86; border:1px solid #d9e0fa; padding:.7rem .9rem; border-radius:11px; font-size:.84rem; line-height:1.45; }
  .switch { margin-top:1.4rem; padding-top:1.15rem; border-top:1px solid #eef1f6; text-align:center; font-size:.88rem; color:var(--mut); }
  .switch button { background:none; border:none; color:var(--pri); cursor:pointer; font:inherit; font-weight:700; padding:0; }
  .hint { margin-top:1.5rem; display:flex; gap:.6rem; align-items:flex-start; color:var(--mut); font-size:.82rem; line-height:1.45; }
  .hint svg { flex:none; width:16px; height:16px; margin-top:.12rem; color:var(--pri); }
  .foot { text-align:center; color:#9aa1ad; font-size:.76rem; margin:1.3rem 0 0; }
  @media (max-width:480px){ .card { padding:1.7rem 1.35rem 1.5rem; border-radius:18px; } .card h1 { font-size:1.32rem; } }
</style>
</head>
<body>
  <div class="shell">
<?php if ($gateRow && $codeUsed): ?>
    <!-- Stufe 2b: vergebener Code -> nur Anmeldung des zugehörigen Kontos -->
    <div class="card">
      <div class="badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6"/></svg>
      </div>
      <h1>Willkommen zurück</h1>
      <p class="lead">Melde dich mit deinem Konto an, um den Zugang auf diesem Gerät freizuschalten.</p>
      <form method="post" action="">
        <input type="hidden" name="gate_action" value="login">
        <label>E-Mail-Adresse</label>
        <input type="email" name="email" required autocomplete="email" placeholder="du@beispiel.ch">
        <label>Passwort</label>
        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        <?php if ($gateError): ?><div class="err"><?= h($gateError) ?></div><?php endif; ?>
        <div class="note">Dieser Code gehört zu einem Konto. Bitte melde dich mit genau diesem Konto an.</div>
        <button class="btn" type="submit">Anmelden</button>
      </form>
    </div>

<?php elseif ($gateRow): ?>
    <!-- Stufe 2a: freier Code -> Registrieren (oder Anmelden) -->
    <div class="card">
      <div class="badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="4"/><path d="M3 21c0-3.6 3-5.5 6-5.5"/><path d="M17 10.5v6M14 13.5h6"/></svg>
      </div>
      <h1>Konto erstellen</h1>
      <p class="lead">Nur noch ein Schritt: Richte dein Konto ein, um den Zugang abzuschliessen — oder melde dich an, falls du bereits eines hast.</p>
      <form method="post" action="" data-form="register">
        <input type="hidden" name="gate_action" value="register">
        <label>Name <span class="opt">(optional)</span></label>
        <input type="text" name="name" autocomplete="name" placeholder="Vor- und Nachname">
        <label>E-Mail-Adresse</label>
        <input type="email" name="email" required autocomplete="email" placeholder="du@beispiel.ch">
        <label>Passwort <span class="opt">(min. 8 Zeichen)</span></label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="••••••••">
        <?php if ($gateError): ?><div class="err"><?= h($gateError) ?></div><?php endif; ?>
        <button class="btn" type="submit">Konto erstellen</button>
      </form>
      <form method="post" action="" data-form="login" hidden>
        <input type="hidden" name="gate_action" value="login">
        <label>E-Mail-Adresse</label>
        <input type="email" name="email" required autocomplete="email" placeholder="du@beispiel.ch">
        <label>Passwort</label>
        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        <button class="btn" type="submit">Anmelden</button>
      </form>
      <div class="switch">
        <span data-switch-text>Bereits ein Konto?</span>
        <button type="button" data-toggle>Anmelden</button>
      </div>
    </div>
    <script>
      (function(){
        var t=document.querySelector('[data-toggle]'), txt=document.querySelector('[data-switch-text]');
        if(!t) return;
        var reg=document.querySelector('[data-form="register"]'), log=document.querySelector('[data-form="login"]');
        t.addEventListener('click',function(){
          var showLogin=reg.hidden===false;
          reg.hidden=showLogin; log.hidden=!showLogin;
          txt.textContent=showLogin?'Neu hier?':'Bereits ein Konto?';
          t.textContent=showLogin?'Konto erstellen':'Anmelden';
        });
      })();
    </script>

<?php else: ?>
    <!-- Stufe 1: Zugangscode eingeben -->
    <div class="card">
      <div class="badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2.2"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/></svg>
      </div>
      <h1>Zugangscode eingeben</h1>
      <p class="lead">Dieser Bereich ist nur mit Einladung zugänglich. Gib den Zugangscode ein, den du erhalten hast, um fortzufahren.</p>
      <form method="post" action="">
        <label for="beleg">Zugangscode</label>
        <input type="text" id="beleg" name="beleg" autocomplete="off" autocapitalize="characters" spellcheck="false" class="code-input" maxlength="16" placeholder="z. B. AB12CD" value="<?= h(trim((string)($_POST['beleg'] ?? ''))) ?>" autofocus>
        <?php if ($gateError): ?><div class="err"><?= h($gateError) ?></div><?php endif; ?>
        <button class="btn" type="submit">Weiter</button>
      </form>
      <div class="hint">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
        <span>Du erhältst deinen persönlichen Code per Einladung. Jeder Code lässt sich nur einmal verwenden.</span>
      </div>
    </div>
<?php endif; ?>
    <p class="foot">Geschützter Zugang · Anmeldung erforderlich</p>
  </div>
</body>
</html>
