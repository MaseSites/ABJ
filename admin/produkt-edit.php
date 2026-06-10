<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$p  = $id ? product_by_id($id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'             => trim($_POST['name'] ?? ''),
        'description'      => trim($_POST['description'] ?? ''),
        'category'         => trim($_POST['category'] ?? 'Allgemein'),
        'price_cents'      => (int)round((float)str_replace(',', '.', $_POST['price'] ?? '0') * 100),
        'sale_price_cents' => $_POST['sale_price'] !== '' ? (int)round((float)str_replace(',', '.', $_POST['sale_price'] ?? '0') * 100) : null,
        'stock'            => (int)($_POST['stock'] ?? 0),
        'is_bestseller'    => !empty($_POST['is_bestseller']),
        'is_active'        => !empty($_POST['is_active']),
        'images'           => json_decode($_POST['images'] ?? '[]', true) ?: [],
        'sizes'            => array_filter(array_map('trim', explode(',', $_POST['sizes'] ?? ''))),
        'option_groups'    => json_decode($_POST['option_groups'] ?? '[]', true) ?: [],
    ];
    if ($id) {
        product_update($id, $data);
    } else {
        $new = product_create($data);
        $id  = $new['id'];
    }
    redirect('/admin/produkt-edit.php?id=' . $id . '&saved=1');
}

$adminTitle = $p ? 'Produkt bearbeiten' : 'Neues Produkt';
include __DIR__ . '/partials/admin-layout-top.php';

$currency = setting_get('currency') ?: 'EUR';
$saved    = !empty($_GET['saved']);
?>
<div class="admin-header">
  <h1><?= $p ? 'Produkt bearbeiten' : 'Neues Produkt' ?></h1>
  <a href="/admin/produkte.php" class="btn btn-ghost">← Zurück</a>
</div>

<?php if ($saved): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>

<form method="post" action="" class="admin-form" id="product-form">
  <div class="form-row-2">
    <label class="field"><span>Name *</span><input type="text" name="name" value="<?= h($p['name'] ?? '') ?>" required maxlength="200"></label>
    <label class="field"><span>Kategorie</span><input type="text" name="category" value="<?= h($p['category'] ?? 'Allgemein') ?>" maxlength="80"></label>
  </div>
  <label class="field"><span>Beschreibung (HTML erlaubt)</span><textarea name="description" rows="6"><?= h($p['description'] ?? '') ?></textarea></label>
  <div class="form-row-2">
    <label class="field"><span>Preis *</span><input type="number" step="0.01" name="price" value="<?= number_format(($p['price_cents'] ?? 0) / 100, 2, '.', '') ?>" required min="0"></label>
    <label class="field"><span>Sale-Preis (leer = kein Sale)</span><input type="number" step="0.01" name="sale_price" value="<?= $p['sale_price_cents'] ? number_format($p['sale_price_cents'] / 100, 2, '.', '') : '' ?>" min="0"></label>
  </div>
  <div class="form-row-2">
    <label class="field"><span>Lagerbestand (Fallback)</span><input type="number" name="stock" value="<?= (int)($p['stock'] ?? 0) ?>" min="0"></label>
    <label class="field"><span>Grössen (kommagetrennt)</span><input type="text" name="sizes" value="<?= h(implode(', ', $p['sizes'] ?? [])) ?>" placeholder="S, M, L, XL"></label>
  </div>
  <div style="display:flex;gap:2rem;margin:1rem 0">
    <label style="display:flex;gap:.5rem;align-items:center;cursor:pointer">
      <input type="checkbox" name="is_active" <?= ($p['is_active'] ?? true) ? 'checked' : '' ?>> Aktiv
    </label>
    <label style="display:flex;gap:.5rem;align-items:center;cursor:pointer">
      <input type="checkbox" name="is_bestseller" <?= ($p['is_bestseller'] ?? false) ? 'checked' : '' ?>> Bestseller
    </label>
  </div>

  <div class="field">
    <span>Bilder</span>
    <div class="image-list" id="image-list"></div>
    <button type="button" class="btn btn-ghost btn-sm" id="add-image-btn">+ Bild hochladen</button>
    <input type="file" id="image-file-input" accept="image/*" style="display:none">
    <input type="hidden" name="images" id="images-hidden" value="<?= h(json_encode($p['images'] ?? [])) ?>">
  </div>
  <input type="hidden" name="option_groups" value="<?= h(json_encode($p['option_groups'] ?? [])) ?>">

  <button class="btn btn-primary" type="submit">Speichern</button>
</form>

<script>
(function(){
  var images = <?= json_encode($p['images'] ?? []) ?>;
  function render(){
    var list=document.getElementById('image-list');list.innerHTML='';
    images.forEach(function(img,i){
      var d=document.createElement('div');d.className='image-item';
      d.innerHTML='<img src="'+img.src+'" style="width:60px;height:75px;object-fit:cover;border-radius:4px"><button type="button" data-rm="'+i+'">×</button>';
      list.appendChild(d);
    });
    document.getElementById('images-hidden').value=JSON.stringify(images);
  }
  document.getElementById('image-list').addEventListener('click',function(e){
    if(e.target.dataset.rm!==undefined){images.splice(+e.target.dataset.rm,1);render();}
  });
  document.getElementById('add-image-btn').addEventListener('click',function(){document.getElementById('image-file-input').click();});
  document.getElementById('image-file-input').addEventListener('change',function(){
    var f=this.files[0];if(!f)return;
    var fd=new FormData();fd.append('file',f);
    fetch('/api/upload.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
      if(d.ok){images.push({src:d.src});render();}else{alert('Upload fehlgeschlagen');}
    });
    this.value='';
  });
  render();
})();
</script>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
