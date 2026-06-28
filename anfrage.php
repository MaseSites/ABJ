<?php
require_once __DIR__ . '/lib/bootstrap.php';
// Anfrage nur mit Konto (damit sie im Profil & bei uns erscheint).
require_customer();

$cust = current_customer();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc = trim($_POST['desc'] ?? '');
    $link = trim($_POST['link'] ?? '');

    $screenshot = null;
    if (!empty($_FILES['screenshot']['name'])) {
        $up = save_uploaded_image($_FILES['screenshot'] ?? null, $error);
        if ($up) $screenshot = $up;
    }

    if ($error === '' && $desc === '' && !$screenshot) {
        $error = 'Bitte beschreibe das Produkt oder lade einen Screenshot hoch.';
    }

    if ($error === '') {
        $account = account_by_id((int)$cust['id']);
        $addr    = account_address($account);
        $title   = $desc !== '' ? $desc : 'Produktanfrage (Screenshot)';
        if ($link !== '') $title .= ' — ' . $link;

        $item = [
            'name'      => mb_substr($title, 0, 300),
            'size'      => '',
            'qty'       => 1,
            'unitCents' => 0,
            'lineCents' => 0,
            'image'     => $screenshot,
            'request'   => true,
        ];

        $reference = order_create([
            'customer_name' => $cust['name'] ?? '',
            'email'         => $cust['email'] ?? '',
            'phone'         => $account['phone'] ?? '',
            'address'       => $addr,
            'items'         => [$item],
            'total_cents'   => 0,
            'shipping_cents'=> 0,
            'payment_method'=> '',
        ]);
        last_order_set($reference);
        redirect('/bestellung.php?ref=' . urlencode($reference) . '&anfrage=1');
    }
}

$cartCount   = cart_count();
$currentPath = '/anfrage';
$pageTitle   = 'Produkt anfragen';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main id="main" class="container section narrow">
  <div class="anfrage-head">
    <span class="section-title-label">Nichts gefunden?</span>
    <h1 class="section-title" style="margin-bottom:.4rem">Produkt anfragen</h1>
    <p class="muted" style="margin:0;max-width:520px">
      Sag uns, welches Produkt du suchst — beschreibe es oder lade einen Screenshot hoch.
      Wir prüfen die Verfügbarkeit, setzen den Preis und du siehst die Anfrage in deinem
      <a href="<?= url('/konto.php?tab=orders') ?>" style="color:var(--accent-3)">Profil</a>.
    </p>
  </div>

  <?php if ($error): ?><div class="alert alert-error" style="max-width:560px;margin-bottom:1.2rem"><?= h($error) ?></div><?php endif; ?>

  <form method="post" action="<?= url('/anfrage.php') ?>" enctype="multipart/form-data" class="anfrage-form">
    <label class="field">
      <span>Welches Produkt suchst du?</span>
      <input type="text" name="desc" maxlength="300" placeholder="z.B. Nike Air Max 1, Grösse 42, Farbe Grau" value="<?= h($_POST['desc'] ?? '') ?>">
    </label>

    <label class="field">
      <span>Link <small class="muted">(optional)</small></span>
      <input type="url" name="link" maxlength="400" placeholder="https://… (Online-Shop, Insta, …)" value="<?= h($_POST['link'] ?? '') ?>">
    </label>

    <div class="field">
      <span>Screenshot / Bild <small class="muted">(optional)</small></span>
      <label class="anfrage-drop" data-drop>
        <input type="file" name="screenshot" accept="image/*" data-file hidden>
        <div class="anfrage-drop-inner" data-drop-inner>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="30" height="30"><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/><path d="M12 3v13M7 8l5-5 5 5"/></svg>
          <span><strong>Bild auswählen</strong> oder hierher ziehen</span>
          <small class="muted">JPG, PNG, WEBP · max. 6 MB</small>
        </div>
        <img class="anfrage-preview" data-preview hidden alt="Vorschau">
      </label>
    </div>

    <button class="btn btn-primary btn-block" type="submit">Anfrage absenden</button>
    <p class="muted" style="font-size:.8rem;text-align:center;margin:.2rem 0 0">Unverbindlich · kostenlos · du entscheidest später, ob du bestellst.</p>
  </form>
</main>

<script>
(function () {
  var drop = document.querySelector('[data-drop]');
  if (!drop) return;
  var input = drop.querySelector('[data-file]');
  var preview = drop.querySelector('[data-preview]');
  var inner = drop.querySelector('[data-drop-inner]');
  function show(file) {
    if (!file || !file.type.startsWith('image/')) return;
    var r = new FileReader();
    r.onload = function (e) { preview.src = e.target.result; preview.hidden = false; inner.hidden = true; };
    r.readAsDataURL(file);
  }
  input.addEventListener('change', function () { if (input.files[0]) show(input.files[0]); });
  ['dragover', 'dragenter'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
  drop.addEventListener('drop', function (e) {
    if (e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; show(e.dataTransfer.files[0]); }
  });
})();
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
