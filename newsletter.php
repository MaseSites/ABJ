<?php
require_once __DIR__ . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = db()->prepare("INSERT OR IGNORE INTO newsletter (email) VALUES (?)");
            $stmt->execute([mb_substr($email, 0, 200)]);
        } catch (Exception $e) {}
    }
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if ($isAjax) json_response(['ok' => true]);
    redirect('/?newsletter=ok');
}

redirect('/');
