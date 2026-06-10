<?php
require_once __DIR__ . '/../../lib/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'POST required'], 405);
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'Upload fehlgeschlagen'], 400);
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$mime    = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    json_response(['ok' => false, 'error' => 'Nur JPG, PNG, WEBP erlaubt'], 415);
}

if ($file['size'] > 5 * 1024 * 1024) {
    json_response(['ok' => false, 'error' => 'Datei zu groß (max. 5 MB)'], 413);
}

$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? 'jpg';
$dir = __DIR__ . '/../../public/uploads';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = bin2hex(random_bytes(12)) . '.' . $ext;
$dest     = $dir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    json_response(['ok' => false, 'error' => 'Speichern fehlgeschlagen'], 500);
}

json_response(['ok' => true, 'src' => '/uploads/' . $filename]);
