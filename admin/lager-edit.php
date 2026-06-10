<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$id  = (int)($_GET['id'] ?? 0);
$row = $id ? (function($id){ $s = db()->prepare('SELECT i.*,p.name AS product_name FROM inventory i JOIN products p ON p.id=i.product_id WHERE i.id=?'); $s->execute([$id]); return $s->fetch() ?: null; })($id) : null;
if (!$row) redirect('/admin/lager.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = db()->prepare("UPDATE inventory SET stock=?,min_stock=?,sku=?,notes=?,title=?,next_delivery=?,variant_price_cents=?,updated_at=datetime('now') WHERE id=?");
    $stmt->execute([
        max(0,(int)$_POST['stock']), max(0,(int)$_POST['min_stock']),
        trim($_POST['sku']??''), trim($_POST['notes']??''), trim($_POST['title']??''),
        trim($_POST['next_delivery']??''),
        $_POST['variant_price'] !== '' ? (int)round((float)str_replace(',','.',$_POST['variant_price']??'0')*100) : null,
        $id,
    ]);
    redirect('/admin/lager-edit.php?id='.$id.'&saved=1');
}

$adminTitle = 'Lager bearbeiten';
include __DIR__ . '/partials/admin-layout-top.php';
?>
<div class="admin-header">
  <h1>Lager: <?= h($row['product_name']) ?> <?= $row['size'] ? '(' . h($row['size']) . ')' : '' ?></h1>
  <a href="/admin/lager.php" class="btn btn-ghost">← Zurück</a>
</div>
<?php if (!empty($_GET['saved'])): ?><div class="alert alert-ok" style="margin-bottom:1rem">Gespeichert.</div><?php endif; ?>

<form method="post" class="admin-form" style="max-width:500px">
  <div class="form-row-2">
    <label class="field"><span>Bestand</span><input type="number" name="stock" value="<?= (int)$row['stock'] ?>" min="0" required></label>
    <label class="field"><span>Min.-Bestand (Warnung)</span><input type="number" name="min_stock" value="<?= (int)$row['min_stock'] ?>" min="0"></label>
  </div>
  <label class="field"><span>Varianten-Titel</span><input type="text" name="title" value="<?= h($row['title']??'') ?>" maxlength="100"></label>
  <label class="field"><span>SKU</span><input type="text" name="sku" value="<?= h($row['sku']??'') ?>" maxlength="80"></label>
  <label class="field"><span>Varianten-Preis (leer = Produktpreis)</span><input type="number" step="0.01" name="variant_price" value="<?= $row['variant_price_cents'] !== null ? number_format($row['variant_price_cents']/100, 2, '.', '') : '' ?>" min="0"></label>
  <label class="field"><span>Nächste Lieferung</span><input type="text" name="next_delivery" value="<?= h($row['next_delivery']??'') ?>"></label>
  <label class="field"><span>Notizen</span><textarea name="notes" rows="3"><?= h($row['notes']??'') ?></textarea></label>
  <button class="btn btn-primary" type="submit">Speichern</button>
</form>

<?php include __DIR__ . '/partials/admin-layout-bottom.php'; ?>
