<?php
// Versandkosten zentral (vorher in kasse.php / api/checkout.php hartkodiert)

function shipping_cost_cents(string $country, int $subtotalCents): int {
    $freeFrom = (int)(setting_get('free_shipping_from_cents') ?: 0);
    if ($freeFrom > 0 && $subtotalCents >= $freeFrom) return 0;
    $ch   = (int)(setting_get('shipping_ch_cents') ?: 590);
    $intl = (int)(setting_get('shipping_intl_cents') ?: 1990);
    return strtoupper($country) === 'CH' ? $ch : $intl;
}

function shipping_free_from_cents(): int {
    return (int)(setting_get('free_shipping_from_cents') ?: 0);
}
