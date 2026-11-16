<?php
declare(strict_types=1);

function agent_ctx(): array
{
    $u = require_role('agent');
    $a = my_agent();
    if (!$a) {
        abort(403, 'Δεν υπάρχει προφίλ αντιπροσώπου', 'Ο διαχειριστής πρέπει να ολοκληρώσει το προφίλ σου.');
    }
    return [$u, $a];
}

/** Agent plus every sub-agent below them. */
function agent_tree_ids(int $agentId): array
{
    $ids = [$agentId];
    $queue = [$agentId];
    while ($queue) {
        $next = array_map('intval', array_column(all('SELECT id FROM agents WHERE parent_agent_id = ?', [array_shift($queue)]), 'id'));
        foreach ($next as $n) {
            if (!in_array($n, $ids, true)) {
                $ids[] = $n;
                $queue[] = $n;
            }
        }
    }
    return $ids;
}

function agent_home(): void
{
    [$u, $a] = agent_ctx();
    $ids = agent_tree_ids((int)$a['id']);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $businesses = all("SELECT b.*, (SELECT COUNT(*) FROM codes c WHERE c.business_id = b.id AND c.status = 'redeemed') AS redeemed,
                       (SELECT COUNT(*) FROM services s WHERE s.business_id = b.id AND s.status = 'active') AS services
                       FROM businesses b WHERE b.agent_id IN ($in) ORDER BY b.created_at DESC", $ids);
    $kpi = [
        'businesses' => count($businesses),
        'active' => count(array_filter($businesses, fn($b) => $b['status'] === 'active')),
        'pending_cents' => (int)val("SELECT COALESCE(SUM(amount_cents),0) FROM commissions WHERE agent_id = ? AND status IN ('pending','approved')", [$a['id']]),
        'paid_cents' => (int)val("SELECT COALESCE(SUM(amount_cents),0) FROM commissions WHERE agent_id = ? AND status = 'paid'", [$a['id']]),
    ];
    $subs = all('SELECT a.*, u.name, u.email FROM agents a JOIN users u ON u.id = a.user_id WHERE a.parent_agent_id = ?', [$a['id']]);
    render('agent/home', ['title' => 'Πίνακας αντιπροσώπου', 'noindex' => true, 'a' => $a, 'u' => $u, 'businesses' => $businesses, 'kpi' => $kpi, 'subs' => $subs, 'area' => 'agent'], 'panel/layout');
}

function agent_new_business(): void
{
    [$u, $a] = agent_ctx();
    if (is_post()) {
        $f = [
            'business' => trim((string)input('business')), 'vat' => preg_replace('/\D/', '', (string)input('vat')),
            'name' => trim((string)input('name')), 'email' => mb_strtolower(trim((string)input('email'))),
            'phone' => trim((string)input('phone')), 'city' => trim((string)input('city')), 'address' => trim((string)input('address')),
        ];
        $err = [];
        if ($f['business'] === '') {
            $err[] = 'Γράψε την επωνυμία.';
        }
        if ($f['name'] === '') {
            $err[] = 'Γράψε τον υπεύθυνο.';
        }
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
            $err[] = 'Γράψε έγκυρο email υπευθύνου.';
        } elseif (one('SELECT id FROM users WHERE email = ?', [$f['email']])) {
            $err[] = 'Υπάρχει ήδη λογαριασμός με αυτό το email.';
        }
        if ($err) {
            keep_old($_POST);
            flash('err', implode(' ', $err));
            redirect(url('/agent/new-business'));
        }
        $tmp = bin2hex(random_bytes(16));
        $uid = tx(function () use ($f, $a, $tmp) {
            $uid = create_user($f['email'], $f['name'], $tmp, 'merchant', ['phone' => $f['phone'], 'referred_by' => $a['affiliate_code']]);
            $bid = insert('businesses', ['name' => $f['business'], 'slug' => unique_slug('businesses', $f['business']), 'vat_number' => $f['vat'],
                'phone' => $f['phone'], 'email' => $f['email'], 'agent_id' => $a['id'], 'status' => 'pending', 'created_at' => now()]);
            insert('business_users', ['business_id' => $bid, 'user_id' => $uid, 'role' => 'owner', 'created_at' => now()]);
            insert('stores', ['business_id' => $bid, 'name' => $f['business'], 'address' => $f['address'], 'city' => $f['city'], 'status' => 'active', 'created_at' => now()]);
            return $uid;
        });
        $token = create_reset_token($uid);
        send_mail($f['email'], 'Πρόσκληση στο ' . site_name(),
            "Γεια σου {$f['name']},\n\nΟ/Η {$u['name']} δημιούργησε λογαριασμό για την επιχείρηση «{$f['business']}» στο " . site_name()
            . ".\nΓια να ορίσεις κωδικό πρόσβασης άνοιξε μέσα σε μία ώρα τον σύνδεσμο:\n" . abs_url('/epanafora/' . $token)
            . "\n\nΑν λήξει, ζήτησε νέο από τη σελίδα «Ξέχασα τον κωδικό».\n" . site_name());
        flash('ok', 'Η επιχείρηση δημιουργήθηκε και περιμένει έγκριση. Στάλθηκε πρόσκληση στον υπεύθυνο.');
        redirect(url('/agent'));
    }
    render('agent/new_business', ['title' => 'Νέα επιχείρηση', 'noindex' => true, 'a' => $a, 'area' => 'agent'], 'panel/layout');
}

function agent_commissions(): void
{
    [, $a] = agent_ctx();
    $rows = all('SELECT cm.*, b.name AS business, c.code FROM commissions cm JOIN businesses b ON b.id = cm.business_id LEFT JOIN codes c ON c.id = cm.code_id WHERE cm.agent_id = ? ORDER BY cm.id DESC LIMIT 500', [$a['id']]);
    render('agent/commissions', ['title' => 'Προμήθειες', 'noindex' => true, 'a' => $a, 'rows' => $rows, 'area' => 'agent'], 'panel/layout');
}
