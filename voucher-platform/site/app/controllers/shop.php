<?php
declare(strict_types=1);

function cart_json(): array
{
    $lines = cart_lines();
    $t = cart_totals($lines);
    return [
        'ok' => true, 'count' => $t['count'], 'total' => money($t['total']), 'savings' => $t['savings'] > 0 ? money($t['savings']) : null,
        'items' => array_map(fn($l) => [
            'key' => $l['key'], 'title' => $l['title'], 'business' => $l['service']['business_name'], 'qty' => $l['qty'],
            'line' => $l['line_cents'] ? money($l['line_cents']) : 'Δωρεάν', 'image' => $l['service']['image'], 'url' => url('/prosfora/' . $l['service']['slug']),
            'max' => min(CART_MAX_QTY, max(1, (int)$l['service']['per_user_limit'])),
        ], $lines),
    ];
}

function cart_page(): void
{
    if (wants_json()) {
        json_out(cart_json());
    }
    $lines = cart_lines();
    render('public/cart', ['title' => 'Καλάθι · ' . site_name(), 'noindex' => true, 'lines' => $lines, 't' => cart_totals($lines)]);
}

function cart_add_action(): void
{
    $r = cart_add((int)input('service_id'), (int)input('variant_id') ?: null, (int)input('qty', 1));
    if (wants_json()) {
        json_out($r['ok'] ? array_merge(cart_json(), ['message' => $r['message']]) : $r, $r['ok'] ? 200 : 422);
    }
    flash($r['ok'] ? 'ok' : 'err', $r['message'] ?? $r['error']);
    redirect(url('/kalathi'));
}

function cart_update_action(): void
{
    cart_set_qty((string)input('key'), (int)input('qty'));
    if (wants_json()) {
        json_out(cart_json());
    }
    redirect(url('/kalathi'));
}

function checkout_page(): void
{
    $u = require_login();
    $lines = cart_lines();
    if (!$lines) {
        flash('info', 'Το καλάθι σου είναι άδειο.');
        redirect(url('/prosfores'));
    }
    $t = cart_totals($lines);
    $errors = [];
    if (is_post()) {
        $name = trim((string)input('name'));
        $email = mb_strtolower(trim((string)input('email')));
        if ($name === '') {
            $errors[] = 'Γράψε το ονοματεπώνυμό σου.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Γράψε ένα έγκυρο email για να σου στείλουμε τους κωδικούς.';
        }
        if (!input('terms')) {
            $errors[] = 'Χρειάζεται να αποδεχτείς τους όρους χρήσης.';
        }
        $errors = array_merge($errors, cart_problems($lines, (int)$u['id']));
        if (!$errors) {
            $order = create_order($u, $lines, $name, $email);
            if ((int)$order['total_cents'] === 0) {
                $order = finalize_order((int)$order['id'], 'free');
                cart_clear();
                send_codes_email($order);
                redirect(url('/paraggelia/' . $order['number']));
            }
            cart_clear();
            redirect(payment_start($order));
        }
    }
    render('public/checkout', ['title' => 'Ολοκλήρωση · ' . site_name(), 'noindex' => true, 'lines' => $lines, 't' => $t, 'u' => $u, 'errors' => $errors]);
}

function own_order(string $number): array
{
    $u = require_login();
    $o = one('SELECT * FROM orders WHERE number = ?', [$number]);
    if (!$o || ((int)$o['user_id'] !== (int)$u['id'] && $u['role'] !== 'admin')) {
        abort(404);
    }
    return $o;
}

function order_done(array $p): void
{
    $o = own_order($p['num']);
    render('public/order_done', ['title' => 'Παραγγελία ' . $o['number'] . ' · ' . site_name(), 'noindex' => true, 'o' => $o,
        'codes' => order_codes((int)$o['id']), 'items' => all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']])]);
}

/** Simulated hosted payment page. Disabled when Admin → Ρυθμίσεις → Πληρωμές is set to live. */
function pay_test(array $p): void
{
    if (!test_payments_allowed()) {
        abort(404);
    }
    $o = own_order($p['num']);
    if ($o['status'] !== 'pending') {
        redirect(url('/paraggelia/' . $o['number']));
    }
    if (is_post()) {
        if (input('result') === 'approve') {
            $o = finalize_order((int)$o['id'], 'paid', 'test', 'TEST-' . strtoupper(bin2hex(random_bytes(4))));
            send_codes_email($o);
            flash('ok', 'Η πληρωμή ολοκληρώθηκε.');
        } else {
            update('orders', ['status' => 'failed'], ['id' => $o['id']]);
            flash('err', 'Η πληρωμή απορρίφθηκε. Δεν έγινε καμία χρέωση.');
        }
        redirect(url('/paraggelia/' . $o['number']));
    }
    render('public/pay_test', ['title' => 'Δοκιμαστική πληρωμή', 'noindex' => true, 'o' => $o]);
}

function favorite_toggle(array $p): void
{
    $u = current_user();
    if (!$u) {
        if (wants_json()) {
            json_out(['ok' => false, 'login' => url('/eisodos'), 'error' => 'Συνδέσου για να κρατάς αγαπημένα.'], 401);
        }
        require_login();
    }
    $sid = (int)$p['id'];
    if (!find_public_service($sid)) {
        abort(404);
    }
    $exists = one('SELECT id FROM favorites WHERE user_id = ? AND service_id = ?', [$u['id'], $sid]);
    if ($exists) {
        q('DELETE FROM favorites WHERE id = ?', [$exists['id']]);
    } else {
        insert('favorites', ['user_id' => $u['id'], 'service_id' => $sid, 'created_at' => now()]);
    }
    if (wants_json()) {
        json_out(['ok' => true, 'on' => !$exists]);
    }
    back(url('/logariasmos/agapimena'));
}
