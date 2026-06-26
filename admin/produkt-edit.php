<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$p  = $id ? product_by_id($id) : null;
if ($id && !$p) { http_response_code(404); echo '404 – Produkt nicht gefunden'; exit; }

$isNew = !$p;
$adminTitle = $isNew ? 'Neues Produkt' : ($p['name'] ?? 'Produkt bearbeiten');
include __DIR__ . '/partials/admin-layout-top.php';

/* ── Hilfsfunktionen ── */
function cents_to_input(?int $c): string {
    return $c === null ? '' : number_format($c / 100, 2, '.', '');
}

function pe_slugify(string $text): string {
    $text = strtolower(strtr($text, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue','ß'=>'ss']));
    return trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
}

function pe_build_option_groups(array $p, array $invRows): array {
    $groups = is_array($p['option_groups'] ?? null) ? $p['option_groups'] : [];
    if (!empty($groups)) return $groups;
    $sizes  = is_array($p['sizes'] ?? null) ? $p['sizes'] : [];
    $colors = array_values(array_unique(array_filter(array_column($invRows, 'color'))));
    if ($sizes)  $groups[] = ['key' => 'size',  'label' => 'Größe', 'values' => $sizes];
    if ($colors) $groups[] = ['key' => 'color', 'label' => 'Farbe', 'values' => $colors];
    return $groups;
}

function pe_build_variants(array $invRows): array {
    $out = [];
    foreach ($invRows as $row) {
        // Strukturierte Optionswerte bevorzugen, sonst aus size/color-Spalten ableiten.
        $ov = safe_parse($row['option_values'] ?? '[]', []);
        if (empty($ov)) {
            if (!empty($row['color'])) $ov[] = ['key' => 'color', 'label' => 'Farbe', 'value' => $row['color']];
            if (!empty($row['size']))  $ov[] = ['key' => 'size',  'label' => 'Größe', 'value' => $row['size']];
        }
        $color = '';
        $size  = '';
        foreach ($ov as $o) {
            if (($o['key'] ?? '') === 'color') $color = $o['value'] ?? '';
            if (($o['key'] ?? '') === 'size')  $size  = $o['value'] ?? '';
        }
        $imgs = safe_parse($row['images'] ?? '[]', []);
        $out[] = [
            'color'      => $color,
            'size'       => $size,
            'stock'      => (int)($row['stock'] ?? 0),
            'image'      => $imgs[0]['src'] ?? '',
            'is_default' => !empty($row['is_default']),
        ];
    }
    return $out;
}

$existingImages   = is_array($p['images'] ?? null) ? $p['images'] : [];
$invRows          = $id ? inv_by_product($id) : [];
$optionGroups     = pe_build_option_groups($p ?? [], $invRows);
$existingVariants = pe_build_variants($invRows);
// Neue Produkte starten einfach (ein Gesamtbestand). Varianten nur, wenn das
// Produkt bereits welche hat – so entstehen keine versehentlichen 0-Bestände.
$hasVariants      = $isNew ? false
    : (count($invRows) > 1
       || (count($invRows) === 1 && ($invRows[0]['size'] || $invRows[0]['color'])));

$formAction = url($isNew ? '/admin/api/products.php' : '/admin/api/products.php?id=' . $id);
$title      = $isNew ? 'Neues Produkt' : h($p['name'] ?? '');
$currency   = setting_get('currency') ?: 'CHF';
?>

<p class="admin-kicker">Produkte</p>
<div class="admin-head-row">
  <h1><?= $title ?></h1>
  <a class="btn btn-ghost" href="<?= url('/admin/produkte.php') ?>">← Zurück</a>
</div>

<div id="form-alert" class="alert alert-error" hidden></div>

<form
  id="product-form"
  data-action="<?= h($formAction) ?>"
  data-method="POST"
  class="product-editor"
>

  <!-- ── Schritt 1: Grunddaten ── -->
  <div class="admin-section">
    <div class="step-head">
      <span class="step-num">1</span>
      <div><h2>Grunddaten</h2><p>Name, Preis und Beschreibung</p></div>
    </div>
    <div class="form-step">
      <div class="form-grid">
        <label class="field span-2">
          <span>Name *</span>
          <input type="text" name="name" required maxlength="160"
                 value="<?= h($p['name'] ?? '') ?>" placeholder="z.B. ABJ Essentials Hoodie">
        </label>

        <label class="field">
          <span>Kategorie</span>
          <input type="text" name="category" maxlength="80"
                 value="<?= h($p['category'] ?? '') ?>" placeholder="z.B. Hoodies">
        </label>

        <label class="field">
          <span>Preis (CHF)</span>
          <input type="text" name="price" inputmode="decimal"
                 value="<?= cents_to_input($p['price_cents'] ?? null) ?>" placeholder="49.90">
        </label>

        <label class="field">
          <span>Sale-Preis (CHF) <small class="muted">optional</small></span>
          <input type="text" name="sale_price" inputmode="decimal"
                 value="<?= cents_to_input($p['sale_price_cents'] ?? null) ?>" placeholder="29.90">
        </label>

        <label class="field span-2">
          <span>Beschreibung</span>
          <textarea name="description" rows="4" maxlength="8000"
                    placeholder="Produktbeschreibung…"><?= h($p['description'] ?? '') ?></textarea>
        </label>

        <label class="field span-2">
          <span>Such-Tags <small class="muted">(Komma-getrennt, erscheinen nicht im Shop)</small></span>
          <input type="text" name="tags" maxlength="300"
                 value="<?= h($p['tags'] ?? '') ?>" placeholder="z.B. nike, sneaker, retro, geschenk">
          <small style="color:#8a8a95;font-size:.75rem">Hilft Kunden, das Produkt über die Suche zu finden, ohne dass die Begriffe im Titel stehen.</small>
        </label>
      </div>
    </div>
  </div>

  <!-- ── Schritt 2: Varianten & Bestand ── -->
  <div class="admin-section">
    <div class="step-head">
      <span class="step-num">2</span>
      <div>
        <h2>Varianten &amp; Bestand</h2>
        <p>Farben (mit eigenem Bild), Größen und Bestand pro Kombination</p>
      </div>
    </div>
    <div class="form-step">
      <label class="switch-row" style="margin-bottom:1.2rem">
        <input type="checkbox" name="has_variants" value="1"
               <?= $hasVariants ? 'checked' : '' ?>>
        <div>
          <strong>Dieses Produkt hat Varianten</strong>
          <small>Farben und/oder Größen mit eigenem Bestand. Aus: ein Gesamtbestand.</small>
        </div>
      </label>

      <!-- Kein-Varianten-Bestand -->
      <div id="no-variant-stock" <?= $hasVariants ? 'hidden' : '' ?>>
        <label class="field" style="max-width:200px">
          <span>Lagerbestand</span>
          <input type="number" name="stock" min="0" max="1000000"
                 value="<?= (int)($p['stock'] ?? 0) ?>">
        </label>
      </div>

      <!-- Varianten-Builder -->
      <div id="variant-builder" <?= !$hasVariants ? 'hidden' : '' ?>>

        <div class="vb-block">
          <div class="vb-block-head">
            <h3>Farben</h3>
            <span class="muted small">Jede Farbe kann ein eigenes Bild haben (optional)</span>
          </div>
          <div id="vb-colors"></div>
          <button type="button" class="btn btn-ghost btn-sm" data-vb-add-color>+ Farbe hinzufügen</button>
        </div>

        <div class="vb-block">
          <div class="vb-block-head">
            <h3>Größen</h3>
            <span class="muted small">Leer lassen, wenn das Produkt keine Größen hat</span>
          </div>
          <div class="vb-chips" id="vb-sizes"></div>
          <div class="vb-add-row">
            <input type="text" id="vb-size-input" placeholder="Größe eingeben…" maxlength="40">
            <button type="button" class="btn btn-ghost btn-sm" data-vb-add-size>Hinzufügen</button>
          </div>
          <div class="vb-quick">
            <span class="muted small">Schnell:</span>
            <?php foreach (['XS','S','M','L','XL','XXL','One Size'] as $qs): ?>
            <button type="button" class="btn btn-ghost btn-sm" data-vb-size-quick="<?= h($qs) ?>">+ <?= h($qs) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="vb-block">
          <div class="vb-block-head">
            <h3>Bestand pro Variante</h3>
            <span class="muted small">Wird automatisch aus Farben &amp; Größen gebildet</span>
          </div>
          <div id="vb-matrix"></div>
        </div>

      </div>
    </div>
  </div>

  <!-- ── Schritt 3: Bilder ── -->
  <div class="admin-section">
    <div class="step-head">
      <span class="step-num">3</span>
      <div>
        <h2>Bilder</h2>
        <p>Bis zu 8 Bilder — das erste wird als Hauptbild angezeigt</p>
      </div>
    </div>
    <div class="form-step">
      <div class="existing-images" id="existing-images"></div>
      <p class="img-hint muted">Das erste Bild ist das Hauptbild. Reihenfolge mit den Pfeilen ändern, × entfernt ein Bild.</p>
      <div class="image-input-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <label class="field">
          <span>Bilder hochladen <small class="muted">(JPG, PNG, WEBP – max. 5 MB)</small></span>
          <input type="file" name="images" accept="image/*" multiple data-file-input>
        </label>
        <label class="field">
          <span>Oder Bild-URLs <small class="muted">(eine URL pro Zeile)</small></span>
          <textarea name="image_urls" rows="3" placeholder="https://..."></textarea>
        </label>
      </div>
      <div class="upload-preview" id="upload-preview"></div>
    </div>
  </div>

  <!-- ── Schritt 4: Einstellungen ── -->
  <div class="admin-section">
    <div class="step-head">
      <span class="step-num">4</span>
      <div><h2>Einstellungen</h2></div>
    </div>
    <div class="form-step">
      <div class="field checkbox-row">
        <label class="checkbox">
          <input type="checkbox" name="is_active" value="1"
                 <?= (!$p || !empty($p['is_active'])) ? 'checked' : '' ?>>
          Aktiv (im Shop sichtbar)
        </label>
        <label class="checkbox">
          <input type="checkbox" name="is_bestseller" value="1"
                 <?= !empty($p['is_bestseller']) ? 'checked' : '' ?>>
          Als Bestseller markieren
        </label>
      </div>
    </div>
  </div>

  <!-- ── Sticky Speichern-Leiste ── -->
  <div class="sticky-save">
    <p class="muted" style="margin:0;font-size:.82rem">
      <?= $isNew ? 'Produkt wird gespeichert und automatisch ins Lager eingetragen.'
                 : 'Änderungen werden gespeichert und im Lager synchronisiert.' ?>
    </p>
    <div class="form-footer" style="margin:0">
      <button class="btn btn-primary" type="submit">
        <?= $isNew ? 'Produkt erstellen' : 'Änderungen speichern' ?>
      </button>
      <a class="btn btn-ghost" href="<?= url('/admin/produkte.php') ?>">Abbrechen</a>
    </div>
  </div>

</form>

<!-- Daten für admin.js -->
<script type="application/json" id="existing-images-data"><?= json_encode($existingImages) ?></script>
<script type="application/json" id="existing-variants-data"><?= json_encode($existingVariants) ?></script>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
