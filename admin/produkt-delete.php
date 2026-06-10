<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) { product_delete($id); inv_delete_by_product($id); }
}
redirect('/admin/produkte.php');
