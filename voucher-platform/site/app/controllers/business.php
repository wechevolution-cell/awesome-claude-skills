<?php
declare(strict_types=1);

function biz_context(bool $ownerOnly = false): array
{
    $u = require_role(...($ownerOnly ? ['merchant'] : ['merchant', 'staff']));
    $b = my_business();
    if (!$b) {
        abort(403, 'Δεν υπάρχει συνδεδεμένη επιχείρηση', 'Ο λογαριασμός σου δεν έχει συνδεθεί ακόμα με επιχείρηση. Επικοινώνησε με την ομάδα της πλατφόρμας.');
    }
    return [$u, $b];
}

function biz_home(): void
{
    [$u, $b] = biz_context();
    if ($u['role'] === 'staff') {
        redirect(url('/business/redeem'));
    }
    $bid = (int)$b['id'];
    $monthStart = date('Y-m-01 00:00:00');
    $kpi = [
        'issued' => (int)val('SELECT COUNT(*) FROM codes WHERE business_id = ? AND created_at >= ?', [$bid, $monthStart]),
        'redeemed' => (int)val("SELECT COUNT(*) FROM codes WHERE business_id = ? AND status = 'redeemed' AND redeemed_at >= ?", [$bid, $monthStart]),
        'active' => (int)val("SELECT COUNT(*) FROM codes WHERE business_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at >= ?)", [$bid, now()]),
        'revenue' => (int)val("SELECT COALESCE(SUM(paid_cents),0) FROM codes WHERE business_id = ? AND status = 'redeemed' AND redeemed_at >= ?", [$bid, $monthStart]),
        'services' => (int)val("SELECT COUNT(*) FROM services WHERE business_id = ? AND status = 'active'", [$bid]),
        'pending' => (int)val("SELECT COUNT(*) FROM services WHERE business_id = ? AND status = 'pending'", [$bid]),
    ];
    $recent = all("SELECT c.*, s.title AS service_title, u.name AS customer FROM codes c JOIN services s ON s.id = c.service_id JOIN users u ON u.id = c.user_id
                   WHERE c.business_id = ? AND c.status = 'redeemed' ORDER BY c.redeemed_at DESC LIMIT 8", [$bid]);
    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $days[$d] = 0;
    }
    foreach (all("SELECT redeemed_at FROM codes WHERE business_id = ? AND status = 'redeemed' AND redeemed_at >= ?", [$bid, date('Y-m-d', strtotime('-13 day'))]) as $r) {
        $d = substr($r['redeemed_at'], 0, 10);
        if (isset($days[$d])) {
            $days[$d]++;
        }
    }
    render('business/home', ['title' => 'Πίνακας επιχείρησης', 'noindex' => true, 'b' => $b, 'kpi' => $kpi, 'recent' => $recent, 'days' => $days, 'area' => 'business'], 'panel/layout');
}

function biz_redeem(): void
{
    [$u, $b] = biz_context();
    $result = null;
    $stores = all("SELECT * FROM stores WHERE business_id = ? AND status = 'active' ORDER BY name", [$b['id']]);
    if (is_post()) {
        if (rate_limited('redeem:' . $u['id'], 60, 600)) {
            flash('err', 'Πολλές προσπάθειες. Περίμενε λίγα λεπτά.');
            redirect(url('/business/redeem'));
        }
        $storeId = (int)input('store_id') ?: ($b['member_store'] ? (int)$b['member_store'] : ($stores[0]['id'] ?? null));
        if ($storeId && !in_array($storeId, array_map('intval', array_column($stores, 'id')), true)) {
            $storeId = null;
        }
        $result = redeem_code((string)input('code'), (int)$b['id'], $storeId, (int)$u['id']);
        $_SESSION['last_store'] = $storeId;
    } elseif (input('code') !== '') {
        $result = lookup_code((string)input('code'), (int)$b['id']);
    }
    render('business/redeem', ['title' => 'Εξαργύρωση', 'noindex' => true, 'b' => $b, 'u' => $u, 'stores' => $stores, 'result' => $result, 'area' => 'business', 'scripts' => ['js/vendor/jsQR.js']], 'panel/layout');
}

/** JSON lookup used by the scanner before the cashier confirms. */
function biz_redeem_check(): void
{
    [, $b] = biz_context();
    $r = lookup_code((string)input('code'), (int)$b['id']);
    [$type, $msg] = redeem_message($r);
    $row = $r['row'] ?? null;
    json_out(['ok' => true, 'status' => $r['status'], 'type' => $type, 'message' => $msg, 'code' => $r['code'],
        'service' => $row ? $row['service_title'] . ($row['variant_label'] ? ' · ' . $row['variant_label'] : '') : null,
        'customer' => $row ? $row['customer_name'] : null, 'expires' => $row ? date_gr($row['expires_at']) : null,
        'paid' => $row ? money((int)$row['paid_cents']) : null]);
}

function biz_redemption_rows(int $bid, string $from, string $to): array
{
    return all("SELECT c.code, c.redeemed_at, c.paid_cents, s.title AS service_title, v.label AS variant_label, u.name AS customer, st.name AS store, sb.name AS staff
                FROM codes c JOIN services s ON s.id = c.service_id JOIN users u ON u.id = c.user_id
                LEFT JOIN service_variants v ON v.id = c.variant_id LEFT JOIN stores st ON st.id = c.redeemed_store_id LEFT JOIN users sb ON sb.id = c.redeemed_by
                WHERE c.business_id = ? AND c.status = 'redeemed' AND c.redeemed_at >= ? AND c.redeemed_at <= ? ORDER BY c.redeemed_at DESC", [$bid, $from . ' 00:00:00', $to . ' 23:59:59']);
}

function biz_range(): array
{
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)input('from')) ? (string)input('from') : date('Y-m-01');
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)input('to')) ? (string)input('to') : date('Y-m-d');
    return [$from, $to];
}

function biz_redemptions(): void
{
    [, $b] = biz_context();
    [$from, $to] = biz_range();
    $rows = biz_redemption_rows((int)$b['id'], $from, $to);
    render('business/redemptions', ['title' => 'Εξαργυρώσεις', 'noindex' => true, 'b' => $b, 'rows' => $rows, 'from' => $from, 'to' => $to, 'area' => 'business'], 'panel/layout');
}

function csv_out(string $filename, array $header, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
    fputcsv($out, $header, ';');
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r), ';');
    }
    fclose($out);
    exit;
}

function biz_redemptions_csv(): void
{
    [, $b] = biz_context();
    [$from, $to] = biz_range();
    $rows = array_map(fn($r) => [$r['redeemed_at'], $r['code'], $r['service_title'] . ($r['variant_label'] ? ' · ' . $r['variant_label'] : ''), $r['customer'], $r['store'], $r['staff'], number_format($r['paid_cents'] / 100, 2, ',', '')],
        biz_redemption_rows((int)$b['id'], $from, $to));
    csv_out('exargyroseis-' . $from . '-' . $to . '.csv', ['Ημερομηνία', 'Κωδικός', 'Υπηρεσία', 'Πελάτης', 'Κατάστημα', 'Υπάλληλος', 'Πληρωμή (€)'], $rows);
}

function biz_switch(): void
{
    $u = require_role('merchant', 'staff');
    $id = (int)input('business_id');
    if (one('SELECT id FROM business_users WHERE user_id = ? AND business_id = ?', [$u['id'], $id])) {
        $_SESSION['business_id'] = $id;
    }
    redirect(url('/business'));
}

function biz_staff(): void
{
    [$u, $b] = biz_context(true);
    $stores = all('SELECT * FROM stores WHERE business_id = ? ORDER BY name', [$b['id']]);
    if (is_post()) {
        if (input('action') === 'remove') {
            $uid = (int)input('user_id');
            if ($uid !== (int)$u['id']) {
                q("DELETE FROM business_users WHERE business_id = ? AND user_id = ? AND role = 'staff'", [$b['id'], $uid]);
                flash('ok', 'Ο υπάλληλος αφαιρέθηκε.');
            }
            redirect(url('/business/staff'));
        }
        $name = trim((string)input('name'));
        $email = mb_strtolower(trim((string)input('email')));
        $pass = (string)input('password');
        $err = [];
        if ($name === '') {
            $err[] = 'Γράψε όνομα.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err[] = 'Γράψε έγκυρο email.';
        }
        $existing = one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($existing && $existing['role'] !== 'staff') {
            $err[] = 'Αυτό το email ανήκει σε λογαριασμό άλλου τύπου.';
        }
        if (!$existing && ($e = validate_password($pass))) {
            $err[] = $e;
        }
        $storeId = (int)input('store_id') ?: null;
        if ($storeId && !in_array($storeId, array_map('intval', array_column($stores, 'id')), true)) {
            $storeId = null;
        }
        if ($err) {
            keep_old($_POST);
            flash('err', implode(' ', $err));
            redirect(url('/business/staff'));
        }
        $uid = $existing ? (int)$existing['id'] : create_user($email, $name, $pass, 'staff');
        if (!one('SELECT id FROM business_users WHERE business_id = ? AND user_id = ?', [$b['id'], $uid])) {
            insert('business_users', ['business_id' => $b['id'], 'user_id' => $uid, 'role' => 'staff', 'store_id' => $storeId, 'created_at' => now()]);
        }
        flash('ok', 'Ο υπάλληλος προστέθηκε. Μπορεί να συνδεθεί και να εξαργυρώνει κωδικούς.');
        redirect(url('/business/staff'));
    }
    $staff = all('SELECT u.id, u.name, u.email, bu.role, st.name AS store FROM business_users bu JOIN users u ON u.id = bu.user_id LEFT JOIN stores st ON st.id = bu.store_id WHERE bu.business_id = ? ORDER BY bu.role, u.name', [$b['id']]);
    render('business/staff', ['title' => 'Προσωπικό', 'noindex' => true, 'b' => $b, 'staff' => $staff, 'stores' => $stores, 'me' => $u, 'area' => 'business'], 'panel/layout');
}
