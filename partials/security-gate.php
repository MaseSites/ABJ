<?php
/**
 * Tarnseite + 2-Stufen-Zugang für den Sicherheitsmodus.
 *
 * Stufe 1: Besucher gibt im Feld "Belegnummer" einen Code ein (ein Promo-/
 *          Zugangscode). Unbekannt -> Fehler (bleibt getarnt).
 * Stufe 2: Registrieren oder Anmelden. Danach wird die IP freigeschaltet und
 *          man gelangt auf den echten Shop. Wer sich mit dem Code eines Kunden
 *          NEU registriert, wird dessen Empfehlung (der Kunde bekommt Punkte).
 */
session_start_once();

$gateError = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (isset($_POST['beleg'])) {
        // Stufe 1 – Code muss existieren UND noch frei sein (einmal verwendbar)
        $code = trim((string)$_POST['beleg']);
        if (code_is_usable(code_find($code))) {
            $_SESSION['gate_code'] = $code;
            session_write_close();
            if (!headers_sent()) header('Location: ' . base_path() . '/');
            exit;
        }
        $gateError = 'Kein Beleg mit dieser Nummer gefunden.';
    } elseif (isset($_POST['gate_action'])) {
        // Stufe 2
        $code = $_SESSION['gate_code'] ?? '';
        $row  = $code ? code_find($code) : null;
        if (!code_is_usable($row)) {
            unset($_SESSION['gate_code']);
            $gateError = 'Dieser Beleg ist nicht mehr gültig. Bitte gib eine neue Nummer ein.';
        } elseif ($_POST['gate_action'] === 'register') {
            $res = account_create(trim($_POST['email'] ?? ''), $_POST['password'] ?? '', trim($_POST['name'] ?? ''));
            if (!empty($res['ok'])) {
                // Code einlösen (einmalig) + Werber zuordnen
                code_mark_used($code, (int)$res['id']);
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
            if ($acc) {
                customer_login((int)$acc['id'], $acc['email'], $acc['name'] ?? '');
                ip_allow_add(client_ip());
                unset($_SESSION['gate_code']); session_write_close();
                if (!headers_sent()) header('Location: ' . base_path() . '/');
                exit;
            }
            $gateError = 'E-Mail oder Passwort ist falsch.';
        }
    }
}

$gateCode = $_SESSION['gate_code'] ?? '';
$gateRow  = $gateCode ? code_find($gateCode) : null;
if (!code_is_usable($gateRow)) { $gateRow = null; unset($_SESSION['gate_code']); }
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
<title>Belegassistent</title>
<style>
  :root { --bd:#e3e6ec; --mut:#6b7280; --pri:#2f5fe0; --bg:#f4f6f9; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif; background:var(--bg); color:#1f2430; font-size:15px; }
  .top { background:#fff; border-bottom:1px solid var(--bd); }
  .top-in { max-width:980px; margin:0 auto; padding:.8rem 1.4rem; display:flex; align-items:center; gap:.7rem; }
  .logo { width:30px; height:30px; border-radius:7px; background:linear-gradient(135deg,#2f5fe0,#5b8def); display:grid; place-items:center; color:#fff; font-weight:800; font-size:.95rem; }
  .top h1 { font-size:1.02rem; margin:0; font-weight:700; }
  .top small { color:var(--mut); margin-left:auto; font-size:.82rem; }
  .wrap { max-width:980px; margin:1.6rem auto; padding:0 1.4rem; display:grid; grid-template-columns:1.6fr 1fr; gap:1.4rem; }
  .wrap.single { grid-template-columns:1fr; max-width:460px; }
  .card { background:#fff; border:1px solid var(--bd); border-radius:12px; padding:1.3rem 1.4rem; }
  .card h2 { font-size:.95rem; margin:0 0 .9rem; }
  label { display:block; font-size:.8rem; color:var(--mut); font-weight:600; margin-bottom:.35rem; margin-top:.7rem; }
  label:first-of-type { margin-top:0; }
  textarea, input { width:100%; border:1px solid var(--bd); border-radius:8px; padding:.7rem .85rem; font:inherit; color:#1f2430; background:#fff; outline:none; }
  textarea { min-height:150px; resize:vertical; }
  input:focus, textarea:focus { border-color:var(--pri); box-shadow:0 0 0 3px rgba(47,95,224,.12); }
  .btn { display:inline-flex; align-items:center; gap:.4rem; background:var(--pri); color:#fff; border:none; border-radius:8px; padding:.62rem 1.1rem; font:inherit; font-weight:600; cursor:pointer; }
  .btn:hover { background:#234fc4; }
  .btn-block { width:100%; justify-content:center; margin-top:1rem; }
  .btn-soft { background:#eef1f6; color:#3a4252; }
  .btn-soft:hover { background:#e2e7ef; }
  .row { display:flex; gap:.6rem; align-items:flex-end; }
  .row .grow { flex:1; }
  .muted { color:var(--mut); font-size:.84rem; }
  .err { margin-top:.7rem; background:#fdecec; color:#b42318; border:1px solid #f6cccc; padding:.55rem .8rem; border-radius:8px; font-size:.85rem; }
  .list { list-style:none; margin:0; padding:0; }
  .list li { display:flex; justify-content:space-between; padding:.55rem 0; border-bottom:1px solid #eef1f5; font-size:.86rem; }
  .list li:last-child { border-bottom:none; }
  .tag { font-size:.72rem; padding:.1rem .5rem; border-radius:999px; background:#eef4ff; color:#2f5fe0; }
  .tag.open { background:#fff5e6; color:#b06f00; }
  .toggle-link { background:none; border:none; color:var(--pri); cursor:pointer; font:inherit; font-size:.84rem; padding:0; margin-top:.9rem; }
  .foot { max-width:980px; margin:0 auto 2rem; padding:0 1.4rem; color:#9aa1ad; font-size:.78rem; }
  @media (max-width:760px){ .wrap{ grid-template-columns:1fr; } }
</style>
</head>
<body>
  <div class="top"><div class="top-in">
    <span class="logo">B</span>
    <h1>Belegassistent</h1>
    <small>Interne Belegerfassung · <?= h($today) ?></small>
  </div></div>

<?php if ($gateRow): ?>
  <!-- Stufe 2: Registrieren / Anmelden -->
  <div class="wrap single">
    <div class="card">
      <h2>Beleg freigeben</h2>
      <p class="muted" style="margin-top:0">Erstelle ein Konto, um den Beleg zu öffnen — oder melde dich an, falls du schon eines hast.</p>
      <form method="post" action="" data-form="register">
        <input type="hidden" name="gate_action" value="register">
        <label>Name</label>
        <input type="text" name="name" autocomplete="name">
        <label>E-Mail</label>
        <input type="email" name="email" required autocomplete="email">
        <label>Passwort <span class="muted" style="font-weight:400">(min. 8 Zeichen)</span></label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
        <?php if ($gateError): ?><div class="err"><?= h($gateError) ?></div><?php endif; ?>
        <button class="btn btn-block" type="submit">Konto erstellen &amp; öffnen</button>
      </form>
      <form method="post" action="" data-form="login" hidden>
        <input type="hidden" name="gate_action" value="login">
        <label>E-Mail</label>
        <input type="email" name="email" required autocomplete="email">
        <label>Passwort</label>
        <input type="password" name="password" required autocomplete="current-password">
        <button class="btn btn-block" type="submit">Anmelden</button>
      </form>
      <button type="button" class="toggle-link" data-toggle>Bereits ein Konto? Anmelden</button>
    </div>
  </div>
  <script>
    (function(){
      var t=document.querySelector('[data-toggle]'); if(!t) return;
      var reg=document.querySelector('[data-form="register"]'), log=document.querySelector('[data-form="login"]');
      t.addEventListener('click',function(){
        var showLogin=reg.hidden===false;
        reg.hidden=showLogin; log.hidden=!showLogin;
        t.textContent=showLogin?'Neu hier? Konto erstellen':'Bereits ein Konto? Anmelden';
      });
    })();
  </script>

<?php else: ?>
  <!-- Stufe 1: Code / Belegnummer -->
  <div class="wrap">
    <div class="card">
      <h2>Notiz / Belegtext</h2>
      <form method="post" action="">
        <label for="notiz">Text erfassen</label>
        <textarea id="notiz" name="notiz" placeholder="Belegtext, Notiz oder Buchungsvermerk eingeben…"><?= h($_POST['notiz'] ?? '') ?></textarea>
        <div class="row" style="margin-top:1rem">
          <div class="grow">
            <label for="beleg" style="margin-top:0">Belegnummer prüfen</label>
            <input type="text" id="beleg" name="beleg" autocomplete="off" placeholder="z.B. RE-2026-0001">
          </div>
          <button class="btn" type="submit">Prüfen</button>
        </div>
        <?php if ($gateError): ?><div class="err"><?= h($gateError) ?></div><?php endif; ?>
        <p class="muted" style="margin:.9rem 0 0">Hinweis: Notizen werden lokal zwischengespeichert und sind nicht öffentlich.</p>
      </form>
    </div>
    <div class="card">
      <h2>Zuletzt bearbeitet</h2>
      <ul class="list">
        <li><span>RE-2026-0184</span><span class="tag">erfasst</span></li>
        <li><span>RE-2026-0183</span><span class="tag open">offen</span></li>
        <li><span>RE-2026-0182</span><span class="tag">erfasst</span></li>
        <li><span>GS-2026-0044</span><span class="tag">erfasst</span></li>
      </ul>
      <button class="btn btn-soft" type="button" style="margin-top:1rem;width:100%" onclick="this.textContent='Synchronisiert ✓'">Synchronisieren</button>
    </div>
  </div>
<?php endif; ?>

  <div class="foot">© <?= date('Y') ?> Belegassistent · v2.4 — internes Werkzeug</div>
</body>
</html>
