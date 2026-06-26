<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

// Standardwerte (Kurs & Aufschlag) merken
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_defaults') {
    $rate   = str_replace(',', '.', trim($_POST['usd_chf'] ?? ''));
    $markup = str_replace(',', '.', trim($_POST['markup'] ?? ''));
    if (is_numeric($rate)   && (float)$rate   > 0) setting_set('calc_usd_chf', (string)(float)$rate);
    if (is_numeric($markup) && (float)$markup >= 0) setting_set('calc_markup', (string)(float)$markup);
    redirect('/admin/preisrechner.php?saved=1');
}

$usdChf = (float)(setting_get('calc_usd_chf') ?: '0.90');
$markup = (float)(setting_get('calc_markup') ?: '180');
$currency = setting_get('currency') ?: 'CHF';

$adminTitle = 'Preisrechner';
include __DIR__ . '/partials/admin-layout-top.php';
?>
<p class="admin-kicker">Katalog</p>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Preisrechner</h1></div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Standardwerte gespeichert.</div><?php endif; ?>

<p class="muted" style="max-width:640px;margin-bottom:1.4rem">
  Einkaufspreis und Versand in <strong>USD</strong> eingeben — alles wird in <strong><?= h($currency) ?></strong>
  umgerechnet und mit deinem Aufschlag zum Verkaufspreis kalkuliert.
  Beispiel: Kosten 100 → Aufschlag 180&nbsp;% → Verkaufspreis 280 (180 Gewinn).
</p>

<div class="calc-grid" data-calc>
  <!-- Eingaben -->
  <div class="admin-section" style="margin:0">
    <h2 style="margin-top:0">Eingaben</h2>
    <div class="form-grid">
      <label class="field">
        <span>Einkaufspreis (USD)</span>
        <input type="text" inputmode="decimal" data-calc-price value="" placeholder="z.B. 30.00" autofocus>
      </label>
      <label class="field">
        <span>Versand (USD)</span>
        <input type="text" inputmode="decimal" data-calc-ship value="" placeholder="z.B. 5.00">
      </label>
      <label class="field">
        <span>Kurs USD → <?= h($currency) ?></span>
        <input type="text" inputmode="decimal" data-calc-rate value="<?= h(rtrim(rtrim(number_format($usdChf, 4, '.', ''), '0'), '.')) ?>">
      </label>
      <label class="field">
        <span>Aufschlag (%)</span>
        <input type="text" inputmode="decimal" data-calc-markup value="<?= h(rtrim(rtrim(number_format($markup, 2, '.', ''), '0'), '.')) ?>">
      </label>
    </div>

    <form method="post" style="margin-top:1rem;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="action" value="save_defaults">
      <input type="hidden" name="usd_chf" data-save-rate>
      <input type="hidden" name="markup" data-save-markup>
      <button class="btn btn-ghost btn-sm" type="submit">Kurs &amp; Aufschlag als Standard speichern</button>
    </form>
  </div>

  <!-- Ergebnis -->
  <div class="admin-section calc-result" style="margin:0">
    <h2 style="margin-top:0">Ergebnis</h2>

    <div class="calc-sell">
      <span class="calc-sell-label">Verkaufspreis (<?= h($currency) ?>)</span>
      <span class="calc-sell-value" data-out-sell>—</span>
      <span class="calc-sell-round">gerundet: <strong data-out-round>—</strong></span>
    </div>

    <div class="calc-lines">
      <div class="calc-line"><span>Kosten Einkauf + Versand</span><strong data-out-cost>—</strong></div>
      <div class="calc-line"><span>Aufschlag <span data-out-markup-pct>—</span></span><strong data-out-markup>—</strong></div>
      <div class="calc-line calc-line-profit"><span>Dein Gewinn</span><strong data-out-profit>—</strong></div>
      <div class="calc-line"><span>Marge (Gewinn / Verkaufspreis)</span><strong data-out-margin>—</strong></div>
    </div>
  </div>
</div>

<script>
(function () {
  var box = document.querySelector('[data-calc]');
  if (!box) return;
  var cur = <?= json_encode($currency) ?>;
  var els = {
    price:  box.querySelector('[data-calc-price]'),
    ship:   box.querySelector('[data-calc-ship]'),
    rate:   box.querySelector('[data-calc-rate]'),
    markup: box.querySelector('[data-calc-markup]'),
    outSell:   box.querySelector('[data-out-sell]'),
    outRound:  box.querySelector('[data-out-round]'),
    outCost:   box.querySelector('[data-out-cost]'),
    outMarkup: box.querySelector('[data-out-markup]'),
    outMarkupPct: box.querySelector('[data-out-markup-pct]'),
    outProfit: box.querySelector('[data-out-profit]'),
    outMargin: box.querySelector('[data-out-margin]'),
    saveRate:   box.querySelector('[data-save-rate]'),
    saveMarkup: box.querySelector('[data-save-markup]'),
  };

  function num(el) {
    var v = parseFloat(String(el.value || '').replace(',', '.').replace(/[^0-9.]/g, ''));
    return isFinite(v) ? v : 0;
  }
  function money(v) {
    return cur + ' ' + v.toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  // Auf die nächsten 0.90 / 0.95 aufrunden (psychologischer Preis)
  function roundPretty(v) {
    if (v <= 0) return 0;
    var base = Math.floor(v);
    var candidates = [base + 0.90, base + 0.95, base + 1.90, base + 1.95];
    for (var i = 0; i < candidates.length; i++) { if (candidates[i] >= v) return candidates[i]; }
    return Math.ceil(v);
  }

  function calc() {
    var costUsd = num(els.price) + num(els.ship);
    var rate    = num(els.rate);
    var markup  = num(els.markup);
    var costChf = costUsd * rate;
    var markupAmount = costChf * (markup / 100);
    var sell    = costChf + markupAmount;
    var profit  = sell - costChf;
    var margin  = sell > 0 ? (profit / sell) * 100 : 0;

    els.outCost.textContent   = money(costChf);
    els.outMarkup.textContent = money(markupAmount);
    els.outMarkupPct.textContent = '(' + (markup || 0) + ' %)';
    els.outSell.textContent   = money(sell);
    els.outRound.textContent  = money(roundPretty(sell));
    els.outProfit.textContent = money(profit);
    els.outMargin.textContent = margin.toFixed(1).replace('.', ',') + ' %';

    if (els.saveRate)   els.saveRate.value = rate;
    if (els.saveMarkup) els.saveMarkup.value = markup;
  }

  [els.price, els.ship, els.rate, els.markup].forEach(function (el) {
    el.addEventListener('input', calc);
  });
  calc();
})();
</script>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
