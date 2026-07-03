<?php
function order_create(array $data): string {
    $reference = 'ABJ-' . nano_id(8);
    $isPaid = in_array($data['payment_method'] ?? '', ['kreditkarte', 'paypal']);
    // Bestellungen von noch nicht aktivierten (eingeschränkten) Konten werden
    // zurückgehalten und erscheinen erst nach der Aktivierung im Dashboard.
    $acc  = account_by_email($data['email'] ?? '');
    $held = ($acc && !account_is_activated($acc)) ? 1 : 0;
    $stmt = db()->prepare("INSERT INTO orders (reference,customer_name,email,phone,address,items,total_cents,shipping_cents,status,payment_status,payment_method,discount_code,discount_cents,held)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $reference, $data['customer_name'] ?? '', $data['email'] ?? '',
        $data['phone'] ?? '',
        is_string($data['address']) ? $data['address'] : json_encode($data['address'] ?? []),
        json_encode($data['items'] ?? []),
        $data['total_cents'] ?? 0, $data['shipping_cents'] ?? 0,
        'neu', $isPaid ? 'bezahlt' : 'offen',
        $data['payment_method'] ?? '',
        $data['discount_code'] ?? '', (int)($data['discount_cents'] ?? 0),
        $held,
    ]);
    if ($acc) {
        $body = $held
            ? 'Vielen Dank für deine Bestellung ' . $reference . "!\n\nWir haben sie erhalten. Damit wir sie bearbeiten können, aktiviere bitte noch dein Konto mit deinem Aktivierungscode - klicke dazu oben auf „Konto aktivieren“. Danach läuft alles automatisch weiter."
            : 'Vielen Dank für deine Bestellung ' . $reference . "!\n\nWir haben sie erhalten und kümmern uns sofort darum. Sobald sich etwas tut, melden wir uns hier in deinem Posteingang - versprochen.";
        account_message_create([
            'account_id' => (int)$acc['id'],
            'order_reference' => $reference,
            'sender_role' => 'system',
            'subject' => 'Danke für deine Bestellung! 🎉',
            'body' => $body,
            'is_read' => 0,
        ]);
    }
    return $reference;
}

function orders_list(): array {
    // Zurückgehaltene Bestellungen (Konto noch nicht aktiviert) werden erst nach
    // der Freischaltung sichtbar - hier ausgeblendet.
    $stmt = db()->query("SELECT * FROM orders WHERE COALESCE(merged_into, '') = '' AND COALESCE(held, 0) = 0 ORDER BY created_at DESC LIMIT 500");
    return array_map('order_parse', $stmt->fetchAll());
}

function orders_by_email(string $email): array {
    $stmt = db()->prepare("SELECT * FROM orders WHERE lower(email) = lower(?) AND COALESCE(merged_into, '') = '' ORDER BY created_at DESC LIMIT 200");
    $stmt->execute([trim($email)]);
    return array_map('order_parse', $stmt->fetchAll());
}

function order_by_ref(string $ref): ?array {
    $stmt = db()->prepare("SELECT * FROM orders WHERE reference = ? LIMIT 1");
    $stmt->execute([$ref]);
    $row = $stmt->fetch();
    if ($row && !empty($row['merged_into'])) {
        return order_by_ref((string)$row['merged_into']);
    }
    return $row ? order_parse($row) : null;
}

/**
 * Setzt Produktpreis + Versand einer Bestellung (z.B. nach einer Produktanfrage).
 * Gesamt = Produktpreis + Versand. Bei genau einer Position wird deren
 * Zeilenpreis auf den Produktpreis gesetzt.
 */
function order_set_price(string $ref, int $productCents, int $shippingCents = 0): bool {
    $order = order_by_ref($ref);
    if (!$order) return false;
    $productCents  = max(0, $productCents);
    $shippingCents = max(0, $shippingCents);
    $items = $order['items'];
    if (count($items) === 1) {
        $items[0]['unitCents'] = $productCents;
        $items[0]['lineCents'] = $productCents;
        $items[0]['qty']       = (int)($items[0]['qty'] ?? 1) ?: 1;
    }
    $stmt = db()->prepare("UPDATE orders SET total_cents=?, shipping_cents=?, items=?, updated_at=datetime('now') WHERE reference=?");
    $stmt->execute([$productCents + $shippingCents, $shippingCents, json_encode($items), $ref]);
    return $stmt->rowCount() > 0;
}

/** Ist die Bestellung eine Produktanfrage (ohne festen Preis vom Kunden)? */
function order_is_request(array $order): bool {
    foreach ($order['items'] ?? [] as $it) {
        if (!empty($it['request'])) return true;
    }
    return false;
}

function order_update_status(string $ref, string $status, string $paymentStatus, bool $notifyCustomer = true): bool {
    $before = order_by_ref($ref);
    $stmt = db()->prepare('UPDATE orders SET status=?, payment_status=? WHERE reference=?');
    $stmt->execute([$status, $paymentStatus, $ref]);
    $ok = $stmt->rowCount() > 0;
    // Erhaltenen Betrag mit dem Zahlungsstatus konsistent halten:
    // „bezahlt“ = voll bezahlt; „offen“ setzt eine frühere Vollzahlung zurück,
    // eine Teilzahlung bleibt aber erhalten.
    if ($before) {
        $total = (int)($before['total_cents'] ?? 0);
        $paid  = (int)($before['amount_paid_cents'] ?? 0);
        if ($paymentStatus === 'bezahlt') {
            try { db()->prepare('UPDATE orders SET amount_paid_cents=? WHERE reference=?')->execute([$total, $ref]); } catch (\Throwable $e) {}
        } elseif ($paymentStatus === 'offen' && $paid >= $total && $total > 0) {
            try { db()->prepare('UPDATE orders SET amount_paid_cents=0 WHERE reference=?')->execute([$ref]); } catch (\Throwable $e) {}
        }
    }
    // Promo-Punkte erst gutschreiben, wenn die Zahlung bestätigt wurde (einmalig).
    if ($before && $paymentStatus === 'bezahlt'
        && ($before['payment_status'] ?? '') !== 'bezahlt'
        && empty($before['promo_awarded'])) {
        $acc = account_by_email($before['email'] ?? '');
        if ($acc) {
            promo_award_for_buyer((int)$acc['id'], (int)($before['total_cents'] ?? 0));
            db()->prepare('UPDATE orders SET promo_awarded=1 WHERE reference=?')->execute([$ref]);
        }
    }
    if ($ok && $before && (($before['status'] ?? '') !== $status || ($before['payment_status'] ?? '') !== $paymentStatus)) {
        $notes = [];
        if (($before['status'] ?? '') !== $status) {
            $notes[] = 'Status geändert: ' . str_replace('_', ' ', $status);
        }
        if (($before['payment_status'] ?? '') !== $paymentStatus) {
            $notes[] = 'Zahlungsstatus geändert: ' . $paymentStatus;
        }
        if ($notes) {
            order_message_create([
                'order_reference' => $ref,
                'author_role' => 'system',
                'author_name' => 'System',
                'subject' => 'Automatische Info',
                'body' => implode("\n", $notes),
                'is_system' => 1,
                'is_read' => 0,
            ]);
            if ($notifyCustomer) {
                $acc = account_by_email($before['email'] ?? '');
                if ($acc) {
                    account_message_create([
                        'account_id' => (int)$acc['id'],
                        'order_reference' => $ref,
                        'sender_role' => 'system',
                        'subject' => 'Update zu deiner Bestellung',
                        'body' => 'Es gibt Neuigkeiten zu deiner Bestellung ' . $ref . ":\n\n" . implode("\n", $notes) . "\n\nDanke für deine Geduld!",
                        'is_read' => 0,
                    ]);
                }
            }
        }
    }
    return $ok;
}

/**
 * Erfasst eine (Teil-)Zahlung: erhöht den bereits bezahlten Betrag um $deltaCents
 * (oder setzt ihn absolut, wenn $absolute=true). Erreicht/übersteigt die Summe den
 * Gesamtbetrag, wird die Bestellung als „bezahlt“ markiert (löst Promo-Punkte aus).
 * Legt einen Eintrag in der Bestell-Zeitleiste an und informiert den Kunden.
 */
function order_add_payment(string $ref, int $deltaCents, bool $absolute = false, bool $notifyCustomer = true): bool {
    $order = order_by_ref($ref);
    if (!$order) return false;
    $total   = (int)($order['total_cents'] ?? 0);
    $wasPaid = (int)($order['amount_paid_cents'] ?? 0);
    $newPaid = $absolute ? max(0, $deltaCents) : max(0, $wasPaid + $deltaCents);
    if ($total > 0) $newPaid = min($newPaid, $total);
    if ($newPaid === $wasPaid) return false;

    db()->prepare("UPDATE orders SET amount_paid_cents=?, updated_at=datetime('now') WHERE reference=?")
        ->execute([$newPaid, $ref]);

    $fullyPaid = $total > 0 && $newPaid >= $total;
    // Bei Vollzahlung den Zahlungsstatus setzen (kümmert sich um Promo-Punkte etc.).
    if ($fullyPaid && ($order['payment_status'] ?? '') !== 'bezahlt') {
        $newStatus = ($order['status'] ?? '') === 'neu' ? 'in_bearbeitung' : ($order['status'] ?? 'neu');
        order_update_status($ref, $newStatus, 'bezahlt', false);
    }

    $curr = setting_get('currency') ?: 'CHF';
    $paidStr = number_format($newPaid / 100, 2, '.', '');
    $dueStr  = number_format(max(0, $total - $newPaid) / 100, 2, '.', '');
    $line = $fullyPaid
        ? 'Zahlung vollständig erhalten (' . $paidStr . ' ' . $curr . ').'
        : 'Teilzahlung erfasst: ' . $paidStr . ' ' . $curr . ' von ' . number_format($total / 100, 2, '.', '') . ' ' . $curr . ' - offen: ' . $dueStr . ' ' . $curr . '.';
    order_message_create([
        'order_reference' => $ref,
        'author_role' => 'system', 'author_name' => 'System',
        'subject' => $fullyPaid ? 'Zahlung erhalten' : 'Teilzahlung erfasst',
        'body' => $line, 'is_system' => 1, 'is_read' => 0,
    ]);
    if ($notifyCustomer) {
        $acc = account_by_email($order['email'] ?? '');
        if ($acc) {
            account_message_create([
                'account_id' => (int)$acc['id'],
                'order_reference' => $ref,
                'sender_role' => 'system',
                'subject' => $fullyPaid ? 'Zahlung erhalten' : 'Teilzahlung erhalten',
                'body' => $fullyPaid
                    ? 'Wir haben deine Zahlung für ' . $ref . ' vollständig erhalten. Vielen Dank!'
                    : 'Wir haben eine Teilzahlung für ' . $ref . ' erhalten.' . "\n\nBezahlt: " . $paidStr . ' ' . $curr . "\nNoch offen: " . $dueStr . ' ' . $curr,
                'is_read' => 0,
            ]);
        }
    }
    return true;
}

/**
 * Storniert eine Bestellung durch den Kunden. Nur erlaubt, solange die
 * Bestellung noch nicht versendet/abgeschlossen/storniert ist und dem Kunden
 * gehört. Bucht den Lagerbestand zurück und benachrichtigt Kunde + Admin.
 */
function order_cancel_by_customer(string $ref, string $email): bool {
    $order = order_by_ref($ref);
    if (!$order) return false;
    if (strtolower(trim((string)($order['email'] ?? ''))) !== strtolower(trim($email))) return false;
    if (function_exists('order_is_request') && order_is_request($order)) return false;
    if (!in_array($order['status'] ?? '', ['neu', 'in_bearbeitung'], true)) return false;

    db()->prepare("UPDATE orders SET status='storniert', updated_at=datetime('now') WHERE reference=?")
        ->execute([$order['reference']]);

    // Lagerbestand wieder freigeben
    try { inv_restock_stock($order['items'] ?? []); } catch (\Throwable $e) {}

    $wasPaid = ($order['payment_status'] ?? '') === 'bezahlt';

    // Eintrag in der Bestell-Zeitleiste
    order_message_create([
        'order_reference' => $order['reference'],
        'author_role' => 'system', 'author_name' => 'System',
        'subject' => 'Vom Kunden storniert',
        'body' => 'Der Kunde hat diese Bestellung storniert.' . ($wasPaid ? ' Bestellung war bereits bezahlt - Rückerstattung prüfen.' : ''),
        'is_system' => 1, 'is_read' => 0,
    ]);

    // Admin-Nachricht (erscheint unter „Nachrichten")
    message_create([
        'name'    => $order['customer_name'] ?: ($order['email'] ?? 'Kunde'),
        'email'   => $order['email'] ?? '',
        'subject' => 'Bestellung storniert: ' . $order['reference'],
        'message' => 'Der Kunde hat die Bestellung ' . $order['reference'] . ' storniert.'
            . ($wasPaid ? "\n\nACHTUNG: Die Bestellung war bereits BEZAHLT - bitte Rückerstattung prüfen." : ''),
    ]);

    // Bestätigung im Kunden-Posteingang
    $acc = account_by_email($order['email'] ?? '');
    if ($acc) {
        account_message_create([
            'account_id' => (int)$acc['id'],
            'order_reference' => $order['reference'],
            'sender_role' => 'system',
            'subject' => 'Bestellung storniert',
            'body' => 'Deine Bestellung ' . $order['reference'] . ' wurde storniert. '
                . ($wasPaid ? 'Eine allfällige Rückerstattung wird von uns bearbeitet.' : 'Es entstehen dir keine Kosten.'),
            'is_read' => 0,
        ]);
    }
    return true;
}

function order_mark_seen(string $ref): void {
    db()->prepare('UPDATE orders SET is_seen=1 WHERE reference=?')->execute([$ref]);
}

function order_delete(string $ref): bool {
    $stmt = db()->prepare('DELETE FROM orders WHERE reference=?');
    $stmt->execute([$ref]);
    return $stmt->rowCount() > 0;
}

function order_merge(string $targetRef, string $sourceRef): bool {
    $target = order_by_ref($targetRef);
    $source = db()->prepare("SELECT * FROM orders WHERE reference = ? AND COALESCE(merged_into, '') = ''");
    $source->execute([$sourceRef]);
    $source = $source->fetch();
    if (!$target || !$source) return false;
    if ($target['reference'] === $source['reference']) return false;
    if (strtolower(trim($target['email'] ?? '')) !== strtolower(trim($source['email'] ?? ''))) return false;

    $targetItems = $target['items'];
    $sourceItems = order_parse($source)['items'];
    foreach ($sourceItems as $item) {
        $merged = false;
        foreach ($targetItems as &$tItem) {
            // Gleiches Produkt + gleiche Grösse/Variante werden zusammengefasst -
            // auch wenn der Stückpreis abweicht (z.B. Sale-/Varianten-/Sonderpreis).
            $sameProduct = (int)($tItem['productId'] ?? 0) === (int)($item['productId'] ?? 0);
            $sameSize    = (string)($tItem['size'] ?? '') === (string)($item['size'] ?? '');
            if ($sameProduct && $sameSize) {
                $newQty  = (int)($tItem['qty'] ?? 1) + (int)($item['qty'] ?? 1);
                $newLine = (int)($tItem['lineCents'] ?? 0) + (int)($item['lineCents'] ?? 0);
                $tItem['qty']       = $newQty;
                $tItem['lineCents'] = $newLine;
                // Stückpreis als Mischpreis konsistent halten (Anzeige nutzt lineCents).
                if ($newQty > 0) $tItem['unitCents'] = (int) round($newLine / $newQty);
                $merged = true;
                break;
            }
        }
        unset($tItem);
        if (!$merged) $targetItems[] = $item;
    }

    $targetTotal = (int)$target['total_cents'] + (int)$source['total_cents'];
    $targetShipping = max((int)$target['shipping_cents'], (int)$source['shipping_cents']);
    $stmt = db()->prepare("UPDATE orders SET items=?, total_cents=?, shipping_cents=?, updated_at=datetime('now') WHERE reference=?");
    $stmt->execute([json_encode($targetItems), $targetTotal, $targetShipping, $target['reference']]);

    $srcUpdate = db()->prepare("UPDATE orders SET merged_into=?, status='storniert', is_seen=1, payment_status=CASE WHEN payment_status='bezahlt' THEN 'erstattet' ELSE payment_status END, updated_at=datetime('now') WHERE reference=?");
    $srcUpdate->execute([$target['reference'], $source['reference']]);

    db()->prepare("UPDATE order_messages SET order_reference=? WHERE order_reference=?")->execute([$target['reference'], $source['reference']]);
    order_message_create([
        'order_reference' => $target['reference'],
        'author_role' => 'system',
        'author_name' => 'System',
        'subject' => 'Bestellungen zusammengeführt',
        'body' => 'Die Bestellung ' . $source['reference'] . ' wurde mit dieser Bestellung zusammengeführt.',
        'is_system' => 1,
        'is_read' => 0,
    ]);
    $acc = account_by_email($target['email'] ?? '');
    if ($acc) {
        account_message_create([
            'account_id' => (int)$acc['id'],
            'order_reference' => $target['reference'],
            'sender_role' => 'system',
            'subject' => 'Deine Bestellungen wurden zusammengelegt',
            'body' => 'Gute Nachricht: Wir haben deine Bestellungen ' . $target['reference'] . ' und ' . $source['reference'] . ' zu einer zusammengefasst - so sparst du beim Versand. 😊',
            'is_read' => 0,
        ]);
    }
    return true;
}

function order_parse(array $r): array {
    $r['items'] = json_decode($r['items'] ?? '[]', true) ?: [];
    $addr = $r['address'] ?? '';
    $parsed = json_decode($addr, true);
    if (is_array($parsed)) $r['address'] = $parsed;
    return $r;
}

/**
 * Kunden aus Bestellungen aggregieren (E-Mail = Schlüssel).
 */
function customers_list(): array {
    $rows = db()->query("SELECT email, customer_name, phone,
            COUNT(*) AS order_count,
            SUM(CASE WHEN payment_status='bezahlt' THEN total_cents ELSE 0 END) AS revenue_cents,
            SUM(total_cents) AS total_cents,
            MAX(created_at) AS last_order_at,
            MIN(created_at) AS first_order_at
        FROM orders WHERE email <> ''
        GROUP BY lower(email)
        ORDER BY revenue_cents DESC, order_count DESC")->fetchAll();
    return $rows;
}

function orders_top_products(int $limit = 5, int $days = 90): array {
    $orders = db()->prepare("SELECT items FROM orders WHERE created_at >= datetime('now', ?) AND payment_status != 'storniert'");
    $orders->execute(["-$days days"]);
    $counts = [];
    foreach ($orders->fetchAll() as $o) {
        foreach (json_decode($o['items'] ?? '[]', true) ?: [] as $it) {
            $name = $it['name'] ?? '';
            if (!$name) continue;
            if (!isset($counts[$name])) $counts[$name] = ['name' => $name, 'qty' => 0, 'revenue' => 0];
            $counts[$name]['qty']     += (int)($it['qty'] ?? 1);
            $counts[$name]['revenue'] += (int)($it['lineCents'] ?? 0);
        }
    }
    usort($counts, fn($a, $b) => $b['qty'] <=> $a['qty']);
    return array_slice(array_values($counts), 0, $limit);
}

function orders_stats(int $days = 7): array {
    $pdo = db();
    $totalRevenue = (int)$pdo->query("SELECT COALESCE(SUM(total_cents),0) AS c FROM orders WHERE payment_status='bezahlt' AND COALESCE(held,0)=0")->fetch()['c'];
    $openCount = (int)$pdo->query("SELECT COUNT(*) AS n FROM orders WHERE payment_status != 'bezahlt' AND COALESCE(held,0)=0 AND COALESCE(merged_into,'')=''")->fetch()['n'];
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(total_cents),0) AS revenue FROM orders WHERE date(created_at) = date('now', ?)");
        $stmt->execute(["-$i days"]);
        $day = $stmt->fetch();
        $series[] = ['dayOffset' => $i, 'orders' => (int)$day['orders'], 'revenue' => (int)$day['revenue']];
    }
    $maxOrders  = max(1, ...array_column($series, 'orders'));
    $maxRevenue = max(1, ...array_column($series, 'revenue'));
    return ['totalRevenue' => $totalRevenue, 'openCount' => $openCount, 'series' => $series, 'maxOrders' => $maxOrders, 'maxRevenue' => $maxRevenue];
}
