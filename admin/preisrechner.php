<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

// Konstanten / Faktoren merken
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_defaults') {
    require_cap('settings.manage');
    foreach ([
        'usd_chf'     => 'calc_usd_chf',
        'flat'        => 'calc_flat',
        'ship_per_kg' => 'calc_ship_per_kg',
        'vk_factor'   => 'calc_vk_factor',
        'min_factor'  => 'calc_min_factor',
    ] as $in => $key) {
        $v = str_replace(',', '.', trim($_POST[$in] ?? ''));
        if (is_numeric($v) && (float)$v >= 0) setting_set($key, (string)(float)$v);
    }
    redirect('/admin/preisrechner.php?saved=1');
}

$rate     = (float)(setting_get('calc_usd_chf')     ?: '0.81');
$flat     = (float)(setting_get('calc_flat')        ?: '1.5');
$shipKg   = (float)(setting_get('calc_ship_per_kg') ?: '25');
$vkFactor = (float)(setting_get('calc_vk_factor')   ?: '1.8');
$minFactor= (float)(setting_get('calc_min_factor')  ?: '1.55');
$currency = setting_get('currency') ?: 'CHF';

function pr_num($v): string { return rtrim(rtrim(number_format((float)$v, 4, '.', ''), '0'), '.'); }

$adminTitle = 'Preisrechner';
include __DIR__ . '/partials/admin-layout-top.php';
?>
<p class="admin-kicker">Katalog</p>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Preisrechner</h1></div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>

<p class="muted" style="max-width:680px;margin-bottom:1.4rem">
  Gib <strong>Preis (USD)</strong> und <strong>Gewicht (Gramm)</strong> ein. Daraus werden
  <strong>Verkaufspreis</strong> und <strong>Minimumpreis</strong> berechnet:
  <br><code style="color:#9aa6cf">Kosten = (Preis × <?= h(pr_num($rate)) ?>) + (Gewicht/1000 × <?= h(pr_num($shipKg)) ?> × <?= h(pr_num($rate)) ?>) + <?= h(pr_num($flat)) ?></code>
  <br><code style="color:#9aa6cf">VK = Kosten × <?= h(pr_num($vkFactor)) ?>&nbsp;&nbsp;-&nbsp;&nbsp;Minimum = Kosten × <?= h(pr_num($minFactor)) ?></code>
</p>

<div class="calc-grid" data-calc>
  <!-- Eingaben -->
  <div class="admin-section" style="margin:0">
    <h2 style="margin-top:0">Eingaben</h2>
    <div class="form-grid">
      <label class="field">
        <span>Preis (USD)</span>
        <input type="text" inputmode="decimal" data-calc-price value="" placeholder="z.B. 30.00" autofocus>
      </label>
      <label class="field">
        <span>Gewicht (Gramm)</span>
        <input type="text" inputmode="decimal" data-calc-grams value="" placeholder="z.B. 500">
      </label>
    </div>

    <details class="calc-settings">
      <summary>Konstanten &amp; Faktoren</summary>
      <form method="post" class="form-grid" style="margin-top:1rem">
        <input type="hidden" name="action" value="save_defaults">
        <label class="field"><span>Kurs USD → <?= h($currency) ?></span><input type="text" inputmode="decimal" name="usd_chf" data-calc-rate value="<?= h(pr_num($rate)) ?>"></label>
        <label class="field"><span>Pauschale (<?= h($currency) ?>)</span><input type="text" inputmode="decimal" name="flat" data-calc-flat value="<?= h(pr_num($flat)) ?>"></label>
        <label class="field"><span>Versand pro kg (USD)</span><input type="text" inputmode="decimal" name="ship_per_kg" data-calc-shipkg value="<?= h(pr_num($shipKg)) ?>"></label>
        <label class="field"><span>VK-Faktor</span><input type="text" inputmode="decimal" name="vk_factor" data-calc-vk value="<?= h(pr_num($vkFactor)) ?>"></label>
        <label class="field"><span>Minimum-Faktor</span><input type="text" inputmode="decimal" name="min_factor" data-calc-min value="<?= h(pr_num($minFactor)) ?>"></label>
        <div class="span-2"><button class="btn btn-ghost btn-sm" type="submit">Als Standard speichern</button></div>
      </form>
    </details>
  </div>

  <!-- Ergebnis -->
  <div class="admin-section calc-result" style="margin:0">
    <h2 style="margin-top:0">Ergebnis</h2>

    <div class="calc-two">
      <div class="calc-out calc-out-vk">
        <span class="calc-out-label">Verkaufspreis</span>
        <span class="calc-out-value" data-out-vk>-</span>
        <span class="calc-out-sub">gerundet: <strong data-out-vk-round>-</strong></span>
      </div>
      <div class="calc-out calc-out-min">
        <span class="calc-out-label">Minimumpreis</span>
        <span class="calc-out-value" data-out-min>-</span>
        <span class="calc-out-sub">gerundet: <strong data-out-min-round>-</strong></span>
      </div>
    </div>

    <div class="calc-lines" style="margin-top:1.3rem">
      <div class="calc-line"><span>Warenwert (Preis × Kurs)</span><strong data-out-goods>-</strong></div>
      <div class="calc-line"><span>Versand</span><strong data-out-ship>-</strong></div>
      <div class="calc-line"><span>Pauschale</span><strong data-out-flat>-</strong></div>
      <div class="calc-line calc-line-total"><span>= Kosten</span><strong data-out-cost>-</strong></div>
    </div>
  </div>
</div>

<script>
(function () {
  var box = document.querySelector('[data-calc]');
  if (!box) return;
  var cur = <?= json_encode($currency) ?>;
  var g = function (s) { return box.querySelector(s); };
  var price = g('[data-calc-price]'), grams = g('[data-calc-grams]'),
      rate = g('[data-calc-rate]'), flat = g('[data-calc-flat]'),
      shipkg = g('[data-calc-shipkg]'), vk = g('[data-calc-vk]'), mn = g('[data-calc-min]');

  function num(el) {
    if (!el) return 0;
    var v = parseFloat(String(el.value || '').replace(',', '.').replace(/[^0-9.]/g, ''));
    return isFinite(v) ? v : 0;
  }
  function money(v) { return cur + ' ' + v.toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function roundPretty(v) {
    if (v <= 0) return 0;
    var base = Math.floor(v);
    var cand = [base + 0.90, base + 0.95, base + 1.90, base + 1.95];
    for (var i = 0; i < cand.length; i++) { if (cand[i] >= v) return cand[i]; }
    return Math.ceil(v);
  }
  function set(sel, val) { var e = box.querySelector(sel); if (e) e.textContent = val; }

  function calc() {
    var A = num(price), B = num(grams), r = num(rate), f = num(flat), s = num(shipkg),
        vf = num(vk), mf = num(mn);
    var goods = A * r;
    var ship  = (B / 1000) * s * r;
    var cost  = goods + ship + f;     // (A*r) + ((B/1000)*s*r) + f
    var vkPrice  = cost * vf;          // VK      = Kosten × 1.8
    var minPrice = cost * mf;          // Minimum = Kosten × 1.55

    set('[data-out-goods]', money(goods));
    set('[data-out-ship]', money(ship));
    set('[data-out-flat]', money(f));
    set('[data-out-cost]', money(cost));
    set('[data-out-vk]', money(vkPrice));
    set('[data-out-vk-round]', money(roundPretty(vkPrice)));
    set('[data-out-min]', money(minPrice));
    set('[data-out-min-round]', money(roundPretty(minPrice)));
  }

  [price, grams, rate, flat, shipkg, vk, mn].forEach(function (el) { if (el) el.addEventListener('input', calc); });
  calc();
})();
</script>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
