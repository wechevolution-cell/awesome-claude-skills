<?php
declare(strict_types=1);

const CART_MAX_QTY = 10;

/** SQL fragment: services the public can see and buy. */
function public_service_sql(string $alias = 's'): string
{
    return "$alias.status = 'active' AND EXISTS (SELECT 1 FROM businesses bb WHERE bb.id = $alias.business_id AND bb.status = 'active')";
}

function find_public_service(int $id): ?array
{
    return one('SELECT s.*, b.name AS business_name, b.slug AS business_slug FROM services s JOIN businesses b ON b.id = s.business_id WHERE s.id = ? AND ' . public_service_sql('s'), [$id]);
}

function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_key(int $serviceId, ?int $variantId): string
{
    return $serviceId . ':' . ($variantId ?: 0);
}

function cart_add(int $serviceId, ?int $variantId, int $qty = 1): array
{
    $s = find_public_service($serviceId);
    if (!$s) {
        return ['ok' => false, 'error' => 'Η προσφορά δεν είναι διαθέσιμη.'];
    }
    $variants = all('SELECT * FROM service_variants WHERE service_id = ? ORDER BY sort, id', [$serviceId]);
    if ($variants) {
        $ids = array_map('intval', array_column($variants, 'id'));
        if (!$variantId || !in_array($variantId, $ids, true)) {
            $variantId = $ids[0];
        }
    } else {
        $variantId = null;
    }
    $key = cart_key($serviceId, $variantId);
    $cart = cart_raw();
    $current = $cart[$key]['qty'] ?? 0;
    $limit = min(CART_MAX_QTY, max(1, (int)$s['per_user_limit']));
    $newQty = min($limit, $current + max(1, $qty));
    $cart[$key] = ['service_id' => $serviceId, 'variant_id' => $variantId, 'qty' => $newQty];
    $_SESSION['cart'] = $cart;
    $msg = $newQty === $current ? 'Έχεις ήδη το μέγιστο επιτρεπτό για αυτή την προσφορά.' : 'Προστέθηκε στο καλάθι.';
    return ['ok' => true, 'message' => $msg, 'count' => cart_count()];
}

function cart_set_qty(string $key, int $qty): void
{
    $cart = cart_raw();
    if (!isset($cart[$key])) {
        return;
    }
    if ($qty <= 0) {
        unset($cart[$key]);
    } else {
        $s = find_public_service((int)$cart[$key]['service_id']);
        $limit = $s ? min(CART_MAX_QTY, max(1, (int)$s['per_user_limit'])) : 1;
        $cart[$key]['qty'] = min($qty, $limit);
    }
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

function cart_count(): int
{
    return array_sum(array_column(cart_raw(), 'qty'));
}

/** Resolved cart lines with current prices. Removes lines whose offer is no longer available. */
function cart_lines(): array
{
    $lines = [];
    $cart = cart_raw();
    $changed = false;
    foreach ($cart as $key => $it) {
        $s = find_public_service((int)$it['service_id']);
        if (!$s) {
            unset($cart[$key]);
            $changed = true;
            continue;
        }
        $v = $it['variant_id'] ? one('SELECT * FROM service_variants WHERE id = ? AND service_id = ?', [$it['variant_id'], $s['id']]) : null;
        $unit = (int)($v['price_cents'] ?? $s['price_cents']);
        $regular = (int)($v['regular_cents'] ?? $s['regular_cents']);
        $lines[] = [
            'key' => $key, 'service' => $s, 'variant' => $v, 'qty' => (int)$it['qty'],
            'title' => $s['title'] . ($v ? ' · ' . $v['label'] : ''),
            'unit_cents' => $unit, 'regular_cents' => max($regular, $unit),
            'line_cents' => $unit * (int)$it['qty'], 'line_regular_cents' => max($regular, $unit) * (int)$it['qty'],
        ];
    }
    if ($changed) {
        $_SESSION['cart'] = $cart;
    }
    return $lines;
}

function cart_totals(array $lines): array
{
    $total = array_sum(array_column($lines, 'line_cents'));
    $regular = array_sum(array_column($lines, 'line_regular_cents'));
    return ['total' => $total, 'regular' => $regular, 'savings' => max(0, $regular - $total), 'count' => array_sum(array_column($lines, 'qty'))];
}

/** Checks limits per user and totals before an order is created. Returns a list of problems. */
function cart_problems(array $lines, int $userId): array
{
    $problems = [];
    foreach ($lines as $l) {
        $s = $l['service'];
        $mine = (int)val("SELECT COUNT(*) FROM codes WHERE user_id = ? AND service_id = ? AND status <> 'void'", [$userId, $s['id']]);
        if ($mine + $l['qty'] > max(1, (int)$s['per_user_limit'])) {
            $problems[] = '«' . $s['title'] . '»: έχεις φτάσει το όριο κωδικών ανά πελάτη.';
        }
        if ($s['max_codes'] !== null && $s['max_codes'] !== '') {
            $issued = (int)val("SELECT COUNT(*) FROM codes WHERE service_id = ? AND status <> 'void'", [$s['id']]);
            if ($issued + $l['qty'] > (int)$s['max_codes']) {
                $problems[] = '«' . $s['title'] . '»: δεν απομένουν αρκετοί κωδικοί.';
            }
        }
    }
    return $problems;
}

function codes_left(array $s): ?int
{
    if ($s['max_codes'] === null || $s['max_codes'] === '') {
        return null;
    }
    $issued = (int)val("SELECT COUNT(*) FROM codes WHERE service_id = ? AND status <> 'void'", [$s['id']]);
    return max(0, (int)$s['max_codes'] - $issued);
}

function new_order_number(): string
{
    do {
        $n = date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    } while (val('SELECT COUNT(*) FROM orders WHERE number = ?', [$n]));
    return $n;
}

function create_order(array $user, array $lines, string $name, string $email): array
{
    $t = cart_totals($lines);
    $feePct = (int)setting('fee_percent', 0);
    return tx(function () use ($user, $lines, $name, $email, $t, $feePct) {
        $orderId = insert('orders', [
            'number' => new_order_number(), 'user_id' => (int)$user['id'], 'status' => 'pending',
            'subtotal_cents' => $t['total'], 'regular_cents' => $t['regular'], 'total_cents' => $t['total'],
            'platform_fee_cents' => (int)round($t['total'] * $feePct / 100),
            'customer_name' => $name, 'customer_email' => $email, 'accepted_terms_at' => now(), 'created_at' => now(),
        ]);
        foreach ($lines as $l) {
            insert('order_items', [
                'order_id' => $orderId, 'service_id' => (int)$l['service']['id'], 'variant_id' => $l['variant']['id'] ?? null,
                'business_id' => (int)$l['service']['business_id'], 'title' => $l['title'], 'qty' => $l['qty'],
                'unit_cents' => $l['unit_cents'], 'regular_cents' => $l['regular_cents'],
            ]);
        }
        return one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    });
}

/** Marks the order paid (or free) and issues one code per unit. Safe to call twice. */
function finalize_order(int $orderId, string $status, ?string $provider = null, ?string $ref = null): array
{
    return tx(function () use ($orderId, $status, $provider, $ref) {
        $n = q("UPDATE orders SET status = ?, paid_at = ?, payment_provider = COALESCE(?, payment_provider), payment_ref = COALESCE(?, payment_ref)
                WHERE id = ? AND status = 'pending'", [$status, now(), $provider, $ref, $orderId])->rowCount();
        $order = one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($n === 1) {
            foreach (all('SELECT * FROM order_items WHERE order_id = ?', [$orderId]) as $it) {
                $s = one('SELECT * FROM services WHERE id = ?', [$it['service_id']]);
                for ($i = 0; $i < (int)$it['qty']; $i++) {
                    issue_code($it, $s, (int)$order['user_id']);
                }
            }
            audit('order.' . $status, 'order', $orderId);
        }
        return $order;
    });
}

function order_codes(int $orderId): array
{
    return all('SELECT c.*, s.title AS service_title, s.slug AS service_slug, v.label AS variant_label, b.name AS business_name
                FROM codes c JOIN order_items oi ON oi.id = c.order_item_id JOIN services s ON s.id = c.service_id
                JOIN businesses b ON b.id = c.business_id LEFT JOIN service_variants v ON v.id = c.variant_id
                WHERE oi.order_id = ? ORDER BY c.id', [$orderId]);
}

function send_codes_email(array $order): void
{
    $codes = order_codes((int)$order['id']);
    if (!$codes) {
        return;
    }
    $lines = [];
    foreach ($codes as $c) {
        $lines[] = '• ' . $c['service_title'] . ($c['variant_label'] ? ' (' . $c['variant_label'] . ')' : '') . ' — ' . $c['business_name'] . "\n  Κωδικός: " . $c['code'] . ' · Ισχύει έως ' . date_gr($c['expires_at']);
    }
    $text = "Γεια σου " . $order['customer_name'] . ",\n\nΟι κωδικοί σου είναι έτοιμοι:\n\n" . implode("\n", $lines)
        . "\n\nΔείξε τον κωδικό ή το QR στο ταμείο. Θα τους βρεις πάντα εδώ: " . abs_url('/logariasmos/kodikoi')
        . "\n\nΠαραγγελία " . $order['number'] . "\n" . site_name();
    send_mail($order['customer_email'], 'Οι κωδικοί σου · ' . site_name(), $text);
}
