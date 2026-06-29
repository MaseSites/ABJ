<?php
/**
 * Tarnseite + 2-Stufen-Zugang für den Sicherheitsmodus.
 *
 * Stufe 1: Besucher gibt seinen Zugangscode ein. Unbekannt -> Fehler (getarnt).
 * Stufe 2: Registrieren (freier Code) oder Anmelden.
 *   - Ein freier Code wird beim Registrieren/Anmelden fest dem Konto zugewiesen.
 *   - Ein bereits zugewiesener Code lässt NUR das zugehörige Konto wieder rein
 *     (z. B. neues Gerät). Wer damit ein NEUES/fremdes Konto nutzt -> IP-Sperre.
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
                    ip_allow_add(client_ip());
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
                    ip_allow_add(client_ip());
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
$today = date('d.m.Y');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Belegassistent — Zugang</title>
<style>
  :root { --bd:#e5e8ef; --mut:#6b7280; --ink:#1b2230; --pri:#2f5fe0; --pri-d:#214bc0; --bg:#eef1f6; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; color:var(--ink); font-size:15px;
         background:radial-gradient(1100px 540px at 50% -8%, #ffffff 0%, var(--bg) 60%); min-height:100vh; display:flex; flex-direction:column; }
  .top { background:rgba(255,255,255,.85); backdrop-filter:saturate(150%) blur(6px); border-bottom:1px solid var(--bd); }
  .top-in { max-width:1040px; margin:0 auto; padding:.85rem 1.4rem; display:flex; align-items:center; gap:.7rem; }
  .logo { width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,#2f5fe0,#5b8def); display:grid; place-items:center; color:#fff; font-weight:800; }
  .top h1 { font-size:1.03rem; margin:0; font-weight:700; letter-spacing:-.01em; }
  .top small { color:var(--mut); margin-left:auto; font-size:.82rem; }
  .stage { flex:1; display:flex; align-items:flex-start; justify-content:center; padding:3rem 1.2rem; }
  .card { width:100%; max-width:440px; background:#fff; border:1px solid var(--bd); border-radius:18px;
          box-shadow:0 18px 50px -22px rgba(20,33,71,.35), 0 2px 8px -4px rgba(20,33,71,.15); padding:2rem 1.9rem; }
  .eyebrow { font-size:.72rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--pri); margin:0 0 .5rem; }
  .card h2 { font-size:1.45rem; margin:0 0 .5rem; letter-spacing:-.02em; }
  .lead { color:var(--mut); margin:0 0 1.5rem; line-height:1.5; font-size:.92rem; }
  label { display:block; font-size:.78rem; color:var(--ink); font-weight:600; margin:1rem 0 .4rem; }
  label:first-of-type { margin-top:0; }
  label .opt { color:var(--mut); font-weight:400; }
  input { width:100%; border:1px solid #d6dae3; border-radius:10px; padding:.8rem .9rem; font:inherit; color:var(--ink); background:#fbfcfe; outline:none; transition:border-color .15s, box-shadow .15s; }
  input::placeholder { color:#9aa1ad; }
  input:focus { border-color:var(--pri); box-shadow:0 0 0 3px rgba(47,95,224,.14); background:#fff; }
  .code-input { text-align:center; font-size:1.5rem; font-weight:800; letter-spacing:.35em; text-transform:uppercase; padding:.95rem .9rem; }
  .btn { display:flex; width:100%; justify-content:center; align-items:center; gap:.45rem; background:var(--pri); color:#fff; border:none; border-radius:10px; padding:.85rem 1.1rem; font:inherit; font-weight:700; cursor:pointer; margin-top:1.3rem; transition:background .15s, transform .05s; }
  .btn:hover { background:var(--pri-d); }
  .btn:active { transform:translateY(1px); }
  .err { margin-top:1rem; background:#fdecec; color:#b42318; border:1px solid #f6cccc; padding:.65rem .85rem; border-radius:10px; font-size:.86rem; }
  .note { margin-top:1rem; background:#eef4ff; color:#264a9e; border:1px solid #d4e0fb; padding:.65rem .85rem; border-radius:10px; font-size:.84rem; line-height:1.45; }
  .switch { margin-top:1.3rem; padding-top:1.1rem; border-top:1px solid #eef1f5; text-align:center; font-size:.86rem; color:var(--mut); }
  .switch button { background:none; border:none; color:var(--pri); cursor:pointer; font:inherit; font-weight:600; padding:0; }
  .hint { margin-top:1.4rem; display:flex; gap:.6rem; align-items:flex-start; color:var(--mut); font-size:.82rem; line-height:1.45; }
  .hint svg { flex:none; width:17px; height:17px; margin-top:.1rem; color:var(--pri); }
  .foot { max-width:1040px; margin:0 auto; width:100%; padding:1.4rem; color:#9aa1ad; font-size:.78rem; text-align:center; }
  @media (max-width:520px){ .stage { padding:1.6rem 1rem; } .card { padding:1.6rem 1.3rem; } .card h2 { font-size:1.3rem; } }
</style>
</head>
<body>
  <div class="top"><div class="top-in">
    <span class="logo">B</span>
    <h1>Belegassistent</h1>
    <small>Sicherer Zugang · <?= h($today) ?></small>
  </div></div>

  <div class="stage">
<?php if ($gateRow && $codeUsed): ?>
    <!-- Stufe 2b: vergebener Code -> nur Anmeldung des zugehörigen Kontos -->
    <div class="card">
      <p class="eyebrow">Anmelden</p>
      <h2>Willkommen zurück</h2>
      <p class="lead">Dieser Zugangscode ist bereits einem Konto zugewiesen. Melde dich mit <strong>diesem Konto</strong> an, um den Zugang auf diesem Gerät freizuschalten.</p>
      <form method="post" action="">
        <input type="hidden" name="gate_action" value="login">
        <label>E-Mail-Adresse</label>
        <input type="email" name="email" required autocomplete="email" placeholder="du@beispiel.ch">
        <label>Passwort</label>
        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        <?php if ($gateError): ?><div class="err"><?= h($gateError) ?></div><?php endif; ?>
        <div class="note">Achtung: Dieser Code lässt nur das zugewiesene Konto rein. Ein anderes Konto führt zur Sperrung.</div>
        <button class="btn" type="submit">Anmelden &amp; fortfahren</button>
      </form>
    </div>

<?php elseif ($gateRow): ?>
    <!-- Stufe 2a: freier Code -> Registrieren (oder Anmelden) -->
    <div class="card">
      <p class="eyebrow">Fast geschafft</p>
      <h2>Konto erstellen</h2>
      <p class="lead">Dein Zugangscode ist gültig. Erstelle jetzt dein Konto, um fortzufahren — oder melde dich an, falls du bereits eines hast.</p>
      <form method="post" action="" data-form="register">
        <input type="hidden" name="gate_action" value="register">
        <label>Name <span class="opt">(optional)</span></label>
        <input type="text" name="name" autocomplete="name" placeholder="Vor- und Nachname">
        <label>E-Mail-Adresse</label>
        <input type="email" name="email" required autocomplete="email" placeholder="du@beispiel.ch">
        <label>Passwort <span class="opt">(min. 8 Zeichen)</span></label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="••••••••">
        <?php if ($gateError): ?><div class="err"><?= h($gateError) ?></div><?php endif; ?>
        <button class="btn" type="submit">Konto erstellen &amp; fortfahren</button>
      </form>
      <form method="post" action="" data-form="login" hidden>
        <input type="hidden" name="gate_action" value="login">
        <label>E-Mail-Adresse</label>
        <input type="email" name="email" required autocomplete="email" placeholder="du@beispiel.ch">
        <label>Passwort</label>
        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        <button class="btn" type="submit">Anmelden &amp; fortfahren</button>
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
      <p class="eyebrow">Zugang</p>
      <h2>Zugangscode eingeben</h2>
      <p class="lead">Dieser Bereich ist privat. Gib deinen persönlichen Zugangscode ein, um dich zu registrieren und fortzufahren.</p>
      <form method="post" action="">
        <label for="beleg">Dein Zugangscode</label>
        <input type="text" id="beleg" name="beleg" autocomplete="off" autocapitalize="characters" spellcheck="false" class="code-input" maxlength="16" placeholder="z.B. AB12CD" value="<?= h(trim((string)($_POST['beleg'] ?? ''))) ?>" autofocus>
        <?php if ($gateError): ?><div class="err"><?= h($gateError) ?></div><?php endif; ?>
        <button class="btn" type="submit">Weiter</button>
      </form>
      <div class="hint">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
        <span>Noch keinen Code? Du erhältst einen von einem bestehenden Mitglied. Jeder Code funktioniert nur einmal.</span>
      </div>
    </div>
<?php endif; ?>
  </div>

  <div class="foot">© <?= date('Y') ?> Belegassistent · Sicherer Bereich</div>
</body>
</html>
