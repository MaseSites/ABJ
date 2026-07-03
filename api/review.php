<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$productId = (int)($_POST['product_id'] ?? 0);
$author    = trim($_POST['author'] ?? '');
$rating    = (int)($_POST['rating'] ?? 0);
$text      = trim($_POST['text'] ?? '');

$p = $productId ? product_by_id($productId) : null;
if (!$p || !$p['is_active'])        json_response(['ok' => false, 'error' => 'Produkt nicht gefunden.'], 404);
if (mb_strlen($author) < 2)         json_response(['ok' => false, 'error' => 'Bitte gib deinen Namen an.'], 422);
if ($rating < 1 || $rating > 5)     json_response(['ok' => false, 'error' => 'Bitte wähle 1-5 Sterne.'], 422);
if (mb_strlen($text) < 5)           json_response(['ok' => false, 'error' => 'Bitte schreibe eine kurze Bewertung.'], 422);

// Einfacher Spam-Schutz: max. 3 Bewertungen pro Stunde pro Cart-Token
$token = cart_token();
$recent = db()->prepare("SELECT COUNT(*) AS n FROM reviews WHERE created_at >= datetime('now','-1 hour') AND author = ?");
$recent->execute([mb_substr($author, 0, 80)]);
if ((int)$recent->fetch()['n'] >= 3) {
    json_response(['ok' => false, 'error' => 'Zu viele Bewertungen. Bitte versuche es später erneut.'], 429);
}

review_create($productId, $author, $rating, $text);
json_response(['ok' => true, 'message' => 'Danke! Deine Bewertung wird nach Prüfung veröffentlicht.']);
