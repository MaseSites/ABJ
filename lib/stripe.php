<?php
// ============================================================
// STRIPE-KONFIGURATION
//
// Trage deine Schlüssel in Admin → Einstellungen → Zahlungen ein.
// Stripe Dashboard: https://dashboard.stripe.com/apikeys
//
//   Publishable key : pk_live_…  (oder pk_test_… zum Testen)
//   Secret key      : sk_live_…  (oder sk_test_… zum Testen)
//
// Webhook-Secret einrichten:
//   https://dashboard.stripe.com/webhooks
//   Endpoint-URL  : https://deineshop.de/api/stripe-webhook
//   Events wählen : payment_intent.succeeded
//                   payment_intent.payment_failed
// ============================================================

function stripe_is_configured(): bool {
    $pk = setting_get('stripe_publishable_key') ?: '';
    $sk = setting_get('stripe_secret_key')      ?: '';
    return $pk !== '' && $sk !== '';
}

function stripe_publishable_key(): string {
    return setting_get('stripe_publishable_key') ?: '';
}

function stripe_create_payment_intent(int $amountCents, string $currency, array $meta = []): array {
    $secretKey = setting_get('stripe_secret_key') ?: '';
    if (!$secretKey) {
        throw new RuntimeException('Stripe Secret Key nicht konfiguriert.');
    }

    $params = [
        'amount'                    => $amountCents,
        'currency'                  => strtolower($currency),
        'payment_method_types[0]'   => 'card',
    ];
    if (!empty($meta['order_ref']))   $params['metadata[order_ref]']     = $meta['order_ref'];
    if (!empty($meta['email']))       $params['metadata[customer_email]'] = $meta['email'];
    if (!empty($meta['description'])) $params['description']              = $meta['description'];

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Authorization: Bearer $secretKey\r\nContent-Type: application/x-www-form-urlencoded\r\n",
        'content'       => http_build_query($params),
        'ignore_errors' => true,
        'timeout'       => 15,
    ]]);

    $body = @file_get_contents('https://api.stripe.com/v1/payment_intents', false, $ctx);
    if ($body === false) {
        throw new RuntimeException('Stripe API nicht erreichbar. Bitte versuche es erneut.');
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Ungültige Antwort von Stripe.');
    }
    if (isset($data['error'])) {
        throw new RuntimeException($data['error']['message'] ?? 'Stripe-Fehler');
    }

    return $data;
}

function stripe_retrieve_payment_intent(string $intentId): ?array {
    $secretKey = setting_get('stripe_secret_key') ?: '';
    if (!$secretKey || !$intentId) return null;

    $ctx = stream_context_create(['http' => [
        'method'        => 'GET',
        'header'        => "Authorization: Bearer $secretKey\r\n",
        'ignore_errors' => true,
        'timeout'       => 10,
    ]]);
    $body = @file_get_contents('https://api.stripe.com/v1/payment_intents/' . urlencode($intentId), false, $ctx);
    if (!$body) return null;
    $data = json_decode($body, true);
    if (!is_array($data) || isset($data['error'])) return null;
    return $data;
}

function stripe_verify_webhook(string $payload, string $sigHeader): ?array {
    $secret = setting_get('stripe_webhook_secret') ?: '';
    if (!$secret || !$sigHeader) return null;

    $timestamp = '';
    $signature = '';
    foreach (explode(',', $sigHeader) as $part) {
        if (strpos($part, 't=')  === 0) $timestamp = substr($part, 2);
        if (strpos($part, 'v1=') === 0) $signature = substr($part, 3);
    }

    if (!$timestamp || !$signature) return null;

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    if (!hash_equals($expected, $signature)) return null;
    if (abs(time() - (int)$timestamp) > 300) return null;

    return json_decode($payload, true);
}
