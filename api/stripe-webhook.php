<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$payload   = (string)file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$event = stripe_verify_webhook($payload, $sigHeader);
if ($event === null) {
    http_response_code(400);
    exit('Webhook-Signatur ungültig oder Webhook-Secret nicht konfiguriert');
}

$type = $event['type']          ?? '';
$obj  = $event['data']['object'] ?? [];

if ($type === 'payment_intent.succeeded') {
    $ref = $obj['metadata']['order_ref'] ?? '';
    if ($ref) {
        $order = order_by_ref($ref);
        if ($order && $order['payment_status'] !== 'bezahlt') {
            inv_deduct_stock($order['items']);
            order_update_status($ref, 'in_bearbeitung', 'bezahlt');
        }
    }
} elseif ($type === 'payment_intent.payment_failed') {
    $ref = $obj['metadata']['order_ref'] ?? '';
    if ($ref) {
        order_update_status($ref, 'storniert', 'fehlgeschlagen');
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
