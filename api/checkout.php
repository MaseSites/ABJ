<?php
require_once __DIR__ . '/../lib/bootstrap.php';

// Catch any fatal/unhandled error and return JSON so the frontend shows a message
set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['ok' => false, 'error' => 'Server-Fehler: ' . $e->getMessage()]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$currency = setting_get('currency') ?: 'CHF';

// --- Validate required fields ---
$firstname = trim($_POST['firstname'] ?? '');
$lastname  = trim($_POST['lastname']  ?? '');
$email     = trim($_POST['email']     ?? '');
$phone     = trim($_POST['phone']     ?? '');
$street    = trim($_POST['street']    ?? '');
$housenr   = trim($_POST['housenr']   ?? '');
$zip       = trim($_POST['zip']       ?? '');
$city      = trim($_POST['city']      ?? '');
$country   = trim($_POST['country']   ?? 'CH');
$payMethod = trim($_POST['payment_method'] ?? 'stripe');

$errors = [];
if (!$firstname)           $errors[] = 'Vorname fehlt';
if (!$lastname)            $errors[] = 'Nachname fehlt';
if (!str_has($email, '@')) $errors[] = 'Bitte gib eine gültige E-Mail-Adresse ein';
if (!$street)              $errors[] = 'Straße fehlt';
if (!$zip)                 $errors[] = 'PLZ fehlt';
if (!$city)                $errors[] = 'Stadt fehlt';

if ($errors) {
    json_response(['ok' => false, 'error' => implode(', ', $errors), 'errors' => $errors], 422);
}

// --- Build cart ---
$cart = cart_get();
if (empty($cart)) {
    json_response(['ok' => false, 'error' => 'Dein Warenkorb ist leer.'], 422);
}

$lineItems = [];
$subtotal  = 0;
foreach ($cart as $line) {
    $p = product_by_id($line['productId']);
    if (!$p || !$p['is_active']) continue;
    $variantRow = inv_by_variant($line['productId'], $line['size'] ?? '', '');
    $unit = ($variantRow && $variantRow['variant_price_cents'] !== null)
        ? (int)$variantRow['variant_price_cents']
        : (int)($p['sale_price_cents'] ?? $p['price_cents']);
    $avail   = inv_stock_for_variant($line['productId'], $line['size'] ?? '', '');
    $isBO    = ($avail <= 0) && inv_is_back_order($line['productId'], $line['size'] ?? '', '');
    $safeQty = $isBO ? $line['qty'] : min($line['qty'], max(0, $avail));
    if ($safeQty === 0) continue;
    $subtotal += $unit * $safeQty;
    $lineItems[] = [
        'productId' => $p['id'],
        'slug'      => $p['slug'],
        'name'      => $p['name'],
        'size'      => $line['size'] ?? '',
        'qty'       => $safeQty,
        'unitCents' => $unit,
        'lineCents' => $unit * $safeQty,
    ];
}

if (empty($lineItems)) {
    json_response(['ok' => false, 'error' => 'Keine verfügbaren Artikel im Warenkorb.'], 422);
}

// --- Rabattcode (serverseitig validiert) ---
$discountCode  = trim($_POST['discount_code'] ?? '');
$discountCents = 0;
$freeShipping  = false;
if ($discountCode !== '') {
    $check = discount_validate($discountCode, $subtotal);
    if (!$check['ok']) {
        json_response(['ok' => false, 'error' => 'Rabattcode: ' . $check['error']], 422);
    }
    $discountCents = (int)$check['discount_cents'];
    $freeShipping  = !empty($check['free_shipping']);
    $discountCode  = strtoupper($discountCode);
}

$shippingCents = $freeShipping ? 0 : shipping_cost_cents($country, $subtotal);
$totalCents    = max(0, $subtotal - $discountCents) + $shippingCents;

$address = [
    'firstname' => $firstname, 'lastname' => $lastname,
    'street'    => $street,    'housenr'  => $housenr,
    'zip'       => $zip,       'city'     => $city,
    'country'   => $country,
];

$orderData = [
    'customer_name'  => "$firstname $lastname",
    'email'          => $email,
    'phone'          => $phone,
    'address'        => $address,
    'items'          => $lineItems,
    'total_cents'    => $totalCents,
    'shipping_cents' => $shippingCents,
    'discount_code'  => $discountCode,
    'discount_cents' => $discountCents,
];

// --- Stripe online payment ---
if ($payMethod === 'stripe' && stripe_is_configured()) {
    $reference = order_create($orderData + ['payment_method' => 'stripe']);

    try {
        $pi = stripe_create_payment_intent($totalCents, $currency, [
            'order_ref'   => $reference,
            'email'       => $email,
            'description' => "ABJ Store – Bestellung $reference",
        ]);
        order_set_payment_intent($reference, $pi['id']);
        if ($discountCode) discount_redeem($discountCode);
        cart_set([]);
        last_order_set($reference);
        json_response([
            'ok'              => true,
            'client_secret'   => $pi['client_secret'],
            'order_ref'       => $reference,
            'publishable_key' => stripe_publishable_key(),
            'return_url'      => url('/bestellung.php?ref=' . urlencode($reference)),
        ]);
    } catch (Throwable $e) {
        order_update_status($reference, 'storniert', 'fehlgeschlagen');
        json_response(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// --- Bank transfer / fallback ---
$reference = order_create($orderData + ['payment_method' => 'vorkasse']);
inv_deduct_stock($lineItems);
if ($discountCode) discount_redeem($discountCode);
cart_set([]);
last_order_set($reference);
json_response(['ok' => true, 'redirect' => url('/bestellung.php?ref=' . urlencode($reference))]);
