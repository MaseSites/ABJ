<?php
/**
 * Tarnseite für den Sicherheitsmodus. Sieht aus wie ein internes Beleg-/
 * Notiztool und hat nichts mit dem Shop zu tun. Wer im Feld "Belegnummer"
 * den im Admin gesetzten Zugangscode eingibt, dessen IP wird freigeschaltet
 * und er gelangt auf die echte Seite.
 */
$gateError = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['beleg'])) {
    $code = trim((string)$_POST['beleg']);
    $real = (string)(setting_get('access_code') ?? '');
    if ($real !== '' && hash_equals($real, $code)) {
        ip_allow_add(client_ip());
        if (!headers_sent()) header('Location: ' . base_path() . '/');
        echo 'OK';
        exit;
    }
    $gateError = true;
}
if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
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
  .card { background:#fff; border:1px solid var(--bd); border-radius:12px; padding:1.3rem 1.4rem; }
  .card h2 { font-size:.95rem; margin:0 0 .9rem; }
  label { display:block; font-size:.8rem; color:var(--mut); font-weight:600; margin-bottom:.35rem; }
  textarea, input[type=text] { width:100%; border:1px solid var(--bd); border-radius:8px; padding:.7rem .85rem; font:inherit; color:#1f2430; background:#fff; outline:none; }
  textarea { min-height:150px; resize:vertical; }
  input:focus, textarea:focus { border-color:var(--pri); box-shadow:0 0 0 3px rgba(47,95,224,.12); }
  .btn { display:inline-flex; align-items:center; gap:.4rem; background:var(--pri); color:#fff; border:none; border-radius:8px; padding:.62rem 1.1rem; font:inherit; font-weight:600; cursor:pointer; }
  .btn:hover { background:#234fc4; }
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

  <div class="wrap">
    <div class="card">
      <h2>Notiz / Belegtext</h2>
      <form method="post" action="">
        <label for="notiz">Text erfassen</label>
        <textarea id="notiz" name="notiz" placeholder="Belegtext, Notiz oder Buchungsvermerk eingeben…"><?= h($_POST['notiz'] ?? '') ?></textarea>
        <div class="row" style="margin-top:1rem">
          <div class="grow">
            <label for="beleg">Belegnummer prüfen</label>
            <input type="text" id="beleg" name="beleg" autocomplete="off" placeholder="z.B. RE-2026-0001">
          </div>
          <button class="btn" type="submit">Prüfen</button>
        </div>
        <?php if ($gateError): ?><div class="err">Kein Beleg mit dieser Nummer gefunden.</div><?php endif; ?>
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

  <div class="foot">© <?= date('Y') ?> Belegassistent · v2.4 — internes Werkzeug</div>
</body>
</html>
