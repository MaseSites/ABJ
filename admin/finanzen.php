<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();

$currency = setting_get('currency') ?: 'CHF';

// Manuelle Werte speichern (Kontostand & Investiert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    require_cap('settings.manage');
    foreach (['account' => 'finance_account_cents', 'invested' => 'finance_invested_cents'] as $in => $key) {
        $raw = str_replace(["'", ' '], '', str_replace(',', '.', trim($_POST[$in] ?? '')));
        setting_set($key, (string)max(0, (int)round((float)$raw * 100)));
    }
    redirect('/admin/finanzen.php?saved=1');
}

$accountCents  = (int)(setting_get('finance_account_cents')  ?? 0);
$investedCents = (int)(setting_get('finance_invested_cents') ?? 0);

// Umsatz = Summe bezahlter Bestellungen (Zahlung bestätigt)
$revenueCents = (int)db()->query("SELECT COALESCE(SUM(total_cents),0) AS c FROM orders WHERE payment_status='bezahlt'")->fetch()['c'];
$openCents    = (int)db()->query("SELECT COALESCE(SUM(total_cents),0) AS c FROM orders WHERE payment_status!='bezahlt' AND status!='storniert'")->fetch()['c'];
$paidOrders   = (int)db()->query("SELECT COUNT(*) AS n FROM orders WHERE payment_status='bezahlt'")->fetch()['n'];

$stockValue   = inv_total_value();           // Wert des aktuellen Lagers
$profitCents  = $accountCents - $investedCents; // "im Plus": Konto minus Investiert

function fin_input(int $cents): string {
    return number_format($cents / 100, 2, '.', '');
}

$adminTitle = 'Finanzen';
include __DIR__ . '/partials/admin-layout-top.php';
?>
<p class="admin-kicker">Übersicht</p>
<div class="admin-head-row" style="margin-bottom:1.4rem"><h1>Finanzen</h1></div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>

<!-- KPI-Karten -->
<div class="stat-grid">
  <div class="stat-card stat-highlight">
    <span class="stat-num"><?= format_price($accountCents, $currency) ?></span>
    <span class="stat-label">Kontostand</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= format_price($investedCents, $currency) ?></span>
    <span class="stat-label">Investiert</span>
  </div>
  <div class="stat-card">
    <span class="stat-num"><?= format_price($revenueCents, $currency) ?></span>
    <span class="stat-label">Umsatz (bezahlt)</span>
  </div>
  <div class="stat-card">
    <span class="stat-num" style="color:<?= $profitCents >= 0 ? '#6ee7b7' : '#f08a7a' ?>">
      <?= ($profitCents >= 0 ? '+' : '−') ?><?= format_price(abs($profitCents), $currency) ?>
    </span>
    <span class="stat-label">Im Plus (Konto − Investiert)</span>
  </div>
</div>

<div class="fin-grid">
  <!-- Manuelle Eingabe -->
  <div class="admin-section" style="margin:0">
    <h2 style="margin-top:0">Werte pflegen</h2>
    <p class="muted small" style="margin-bottom:1rem">Kontostand und investierten Betrag trägst du selbst ein.</p>
    <form method="post" class="admin-form">
      <input type="hidden" name="action" value="save">
      <label class="field">
        <span>Kontostand (<?= h($currency) ?>)</span>
        <input type="text" name="account" inputmode="decimal" value="<?= h(fin_input($accountCents)) ?>" placeholder="0.00">
      </label>
      <label class="field">
        <span>Investiert (<?= h($currency) ?>)</span>
        <input type="text" name="invested" inputmode="decimal" value="<?= h(fin_input($investedCents)) ?>" placeholder="0.00">
        <small style="color:#8a8a95;font-size:.75rem">Gesamtbetrag, den du in den Shop gesteckt hast (Ware, Kosten …).</small>
      </label>
      <button class="btn btn-primary" type="submit" style="align-self:flex-start">Speichern</button>
    </form>
  </div>

  <!-- Übersicht / Aufschlüsselung -->
  <div class="admin-section" style="margin:0">
    <h2 style="margin-top:0">Übersicht</h2>
    <div class="fin-lines">
      <div class="fin-line"><span>Kontostand</span><strong><?= format_price($accountCents, $currency) ?></strong></div>
      <div class="fin-line"><span>− Investiert</span><strong><?= format_price($investedCents, $currency) ?></strong></div>
      <div class="fin-line fin-line-total"><span>= Im Plus</span><strong style="color:<?= $profitCents >= 0 ? '#6ee7b7' : '#f08a7a' ?>"><?= format_price($profitCents, $currency) ?></strong></div>
    </div>
    <div class="fin-lines" style="margin-top:1.2rem">
      <div class="fin-line"><span>Umsatz (bezahlte Bestellungen)</span><strong><?= format_price($revenueCents, $currency) ?></strong></div>
      <div class="fin-line"><span>Offen (noch nicht bezahlt)</span><strong><?= format_price($openCents, $currency) ?></strong></div>
      <div class="fin-line"><span>Bezahlte Bestellungen</span><strong><?= $paidOrders ?></strong></div>
      <div class="fin-line"><span>Aktueller Lagerwert</span><strong><?= format_price($stockValue, $currency) ?></strong></div>
    </div>
    <p class="muted small" style="margin-top:1rem">Der Umsatz steigt automatisch, sobald du eine Bestellung unter
      <a href="<?= url('/admin/bestellungen.php') ?>" style="color:#b89c67">Bestellungen</a> auf „Bezahlt" setzt.</p>
  </div>
</div>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
