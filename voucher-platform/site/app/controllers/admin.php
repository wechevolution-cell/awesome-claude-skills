<?php
declare(strict_types=1);

function admin_setup(): void
{
    if (admin_exists()) {
        redirect(url('/admin/login'));
    }
    $errors = [];
    if (is_post()) {
        $name = trim((string)input('name'));
        $email = mb_strtolower(trim((string)input('email')));
        $pass = (string)input('password');
        if ($name === '') {
            $errors[] = 'Γράψε το όνομά σου.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Γράψε έγκυρο email.';
        }
        if ($e = validate_password($pass)) {
            $errors[] = $e;
        }
        if ($pass !== (string)input('password2')) {
            $errors[] = 'Οι δύο κωδικοί δεν ταιριάζουν.';
        }
        if (!$errors) {
            $id = create_user($email, $name, $pass, 'admin');
            if (trim((string)input('site_name')) !== '') {
                set_setting('site_name', mb_substr(trim((string)input('site_name')), 0, 60));
            }
            seed_base();
            login_user(one('SELECT * FROM users WHERE id = ?', [$id]));
            flash('ok', 'Ο λογαριασμός διαχειριστή δημιουργήθηκε.');
            redirect(url('/admin'));
        }
    }
    render('admin/setup', ['title' => 'Πρώτη ρύθμιση', 'noindex' => true, 'errors' => $errors], 'panel/bare');
}

function admin_login(): void
{
    if (is_role('admin')) {
        redirect(url('/admin'));
    }
    if (is_post()) {
        $r = attempt_login((string)input('email'), (string)input('password'), ['admin']);
        if ($r['ok']) {
            login_user($r['user']);
            redirect(url('/admin'));
        }
        flash('err', $r['error']);
        redirect(url('/admin/login'));
    }
    render('admin/login', ['title' => 'Σύνδεση διαχειριστή', 'noindex' => true], 'panel/bare');
}

function admin_home(): void
{
    require_role('admin');
    $month = date('Y-m-01 00:00:00');
    $kpi = [
        ['Ενεργές προσφορές', (int)val("SELECT COUNT(*) FROM services WHERE status = 'active'"), '/admin/c/services?status=active'],
        ['Σε έγκριση', (int)val("SELECT (SELECT COUNT(*) FROM services WHERE status='pending') + (SELECT COUNT(*) FROM businesses WHERE status='pending') + (SELECT COUNT(*) FROM campaigns WHERE status='pending')"), '/admin/approvals'],
        ['Ενεργές καμπάνιες', (int)val('SELECT COUNT(*) FROM campaigns c WHERE ' . active_campaign_sql()), '/admin/c/campaigns'],
        ['Επιχειρήσεις', (int)val("SELECT COUNT(*) FROM businesses WHERE status = 'active'"), '/admin/c/businesses'],
        ['Κωδικοί μήνα', (int)val('SELECT COUNT(*) FROM codes WHERE created_at >= ?', [$month]), '/admin/codes'],
        ['Εξαργυρώσεις μήνα', (int)val("SELECT COUNT(*) FROM codes WHERE status = 'redeemed' AND redeemed_at >= ?", [$month]), '/admin/codes?status=redeemed'],
        ['Τζίρος μήνα', money((int)val("SELECT COALESCE(SUM(total_cents),0) FROM orders WHERE status = 'paid' AND paid_at >= ?", [$month])), '/admin/orders'],
        ['Νέα μηνύματα', (int)val('SELECT COUNT(*) FROM messages WHERE is_read = 0'), '/admin/messages'],
    ];
    $checks = [
        ['Όνομα brand', site_name() !== 'Brand', '/admin/settings#brand'],
        ['Email επικοινωνίας', setting('contact_email') !== '', '/admin/settings#contact'],
        ['Social profiles (sameAs)', setting('social_facebook') . setting('social_instagram') . setting('social_linkedin') !== '', '/admin/settings#contact'],
        ['Νομικά στοιχεία εταιρείας', setting('company_legal_name') !== '' && setting('company_vat') !== '', '/admin/settings#contact'],
        ['Google Analytics 4', setting('ga4_id') !== '', '/admin/settings#seo'],
        ['Search Console', setting('gsc_verification') !== '', '/admin/settings#seo'],
        ['Πάροχος πληρωμών (live)', setting('payments_mode') === 'live', '/admin/settings#payments'],
        ['Site ανοιχτό σε μηχανές αναζήτησης', setting('noindex_site') !== '1', '/admin/settings#seo'],
        ['SMTP για αποστολή email', !empty(config('smtp')['host']), null],
        ['HTTPS', is_https(), null],
    ];
    $files = [['sitemap.xml', '/sitemap.xml'], ['robots.txt', '/robots.txt'], ['llms.txt', '/llms.txt'], ['manifest', '/manifest.webmanifest']];
    $recent = all('SELECT a.*, u.name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 12');
    render('admin/home', ['title' => 'Πίνακας ελέγχου', 'noindex' => true, 'kpi' => $kpi, 'checks' => $checks, 'files' => $files, 'recent' => $recent, 'area' => 'admin'], 'panel/layout');
}

function admin_approvals(): void
{
    require_role('admin');
    render('admin/approvals', [
        'title' => 'Εγκρίσεις', 'noindex' => true, 'area' => 'admin',
        'businesses' => all("SELECT b.*, (SELECT u.name FROM agents a JOIN users u ON u.id = a.user_id WHERE a.id = b.agent_id) AS agent FROM businesses b WHERE status = 'pending' ORDER BY id"),
        'services' => all("SELECT s.*, b.name AS business FROM services s JOIN businesses b ON b.id = s.business_id WHERE s.status = 'pending' ORDER BY s.id"),
        'campaigns' => all("SELECT * FROM campaigns WHERE status = 'pending' ORDER BY id"),
    ], 'panel/layout');
}

function admin_approve(): void
{
    require_role('admin');
    $type = (string)input('type');
    $id = (int)input('id');
    $ok = input('decision') === 'approve';
    $note = mb_substr((string)input('note'), 0, 255);
    $tables = ['business' => 'businesses', 'service' => 'services', 'campaign' => 'campaigns'];
    if (!isset($tables[$type])) {
        abort(400);
    }
    $data = ['status' => $ok ? 'active' : 'rejected'];
    if ($type !== 'business') {
        $data['review_note'] = $note ?: null;
    }
    update($tables[$type], $data, ['id' => $id]);
    audit($ok ? 'approve' : 'reject', $tables[$type], $id, ['note' => $note]);
    if ($type === 'business') {
        $owner = one("SELECT u.* FROM business_users bu JOIN users u ON u.id = bu.user_id WHERE bu.business_id = ? AND bu.role = 'owner'", [$id]);
        if ($owner) {
            send_mail($owner['email'], ($ok ? 'Η επιχείρησή σου εγκρίθηκε' : 'Σχετικά με την αίτησή σου') . ' · ' . site_name(),
                $ok ? "Η επιχείρησή σου εγκρίθηκε. Μπορείς να δημοσιεύεις προσφορές και να εξαργυρώνεις κωδικούς:\n" . abs_url('/business')
                    : "Η αίτηση δεν εγκρίθηκε." . ($note ? "\nΣημείωση: $note" : '') . "\nΓια απορίες απάντησε σε αυτό το email.");
        }
    }
    flash('ok', $ok ? 'Εγκρίθηκε.' : 'Απορρίφθηκε.');
    redirect(url('/admin/approvals'));
}

function settings_groups(): array
{
    return [
        'brand' => ['Brand και αρχική', [
            'site_name' => ['Όνομα brand', 'text'], 'site_tagline' => ['Σύνθημα (tagline)', 'text'],
            'hero_line_1' => ['Hero, γραμμή 1 (κεφαλαία)', 'text'], 'hero_line_2' => ['Hero, γραμμή 2 (πλάγια serif)', 'text'], 'hero_line_3' => ['Hero, γραμμή 3 (χρυσή)', 'text'],
            'hero_text' => ['Κείμενο hero', 'textarea'], 'ticker' => ['Κυλιόμενη ταινία (μία φράση ανά γραμμή)', 'textarea'],
            'default_mood' => ['Προεπιλεγμένη εμφάνιση', 'select', ['auto' => 'Αυτόματα (όπως η συσκευή)', 'night' => 'Νύχτα', 'day' => 'Ημέρα']],
        ]],
        'contact' => ['Επικοινωνία και εταιρεία', [
            'contact_email' => ['Email επικοινωνίας', 'email'], 'contact_phone' => ['Τηλέφωνο', 'text'], 'contact_address' => ['Διεύθυνση', 'text'],
            'company_legal_name' => ['Νομική επωνυμία', 'text'], 'company_vat' => ['ΑΦΜ', 'text'],
            'social_facebook' => ['Facebook URL', 'url'], 'social_instagram' => ['Instagram URL', 'url'], 'social_tiktok' => ['TikTok URL', 'url'],
            'social_linkedin' => ['LinkedIn URL', 'url'], 'social_youtube' => ['YouTube URL', 'url'],
        ]],
        'codes' => ['Κωδικοί και έσοδα', [
            'code_prefix' => ['Πρόθεμα κωδικών (2-4 λατινικά)', 'text'], 'default_validity' => ['Προεπιλεγμένη ισχύς κωδικού (ημέρες)', 'number'],
            'fee_percent' => ['Προμήθεια πλατφόρμας επί πληρωμής (%)', 'number'], 'fee_fixed_cents' => ['Σταθερή χρέωση ανά εξαργύρωση (λεπτά €)', 'number'],
        ]],
        'payments' => ['Πληρωμές', [
            'payments_mode' => ['Λειτουργία', 'select', ['test' => 'Δοκιμαστική (καμία χρέωση)', 'live' => 'Πραγματική']],
            'payments_provider' => ['Πάροχος', 'select', payment_providers()],
        ]],
        'access' => ['Εγγραφές', [
            'allow_registrations' => ['Επιτρέπονται εγγραφές καταναλωτών', 'bool'], 'business_signup' => ['Επιτρέπονται αιτήσεις επιχειρήσεων', 'bool'],
        ]],
        'seo' => ['SEO και analytics', [
            'noindex_site' => ['Κρυφό από μηχανές αναζήτησης (μέχρι το live)', 'bool'], 'ai_bots_allowed' => ['Επιτρέπονται AI crawlers (GPTBot, ClaudeBot κ.ά.)', 'bool'],
            'ga4_id' => ['GA4 Measurement ID (G-XXXX)', 'text'], 'gsc_verification' => ['Search Console verification (content)', 'text'],
        ]],
    ];
}

function admin_settings(): void
{
    require_role('admin');
    if (is_post()) {
        foreach (settings_groups() as $g) {
            foreach ($g[1] as $k => $f) {
                if ($f[1] === 'bool') {
                    set_setting($k, input($k) ? '1' : '0');
                    continue;
                }
                if (!array_key_exists($k, $_POST)) {
                    continue;
                }
                $v = trim((string)$_POST[$k]);
                if ($f[1] === 'url' && $v !== '' && !preg_match('~^https?://~i', $v)) {
                    $v = 'https://' . $v;
                }
                if ($f[1] === 'select' && !array_key_exists($v, $f[2])) {
                    continue;
                }
                if ($k === 'code_prefix') {
                    $v = substr(preg_replace('/[^A-Z]/', '', strtoupper($v)), 0, 4) ?: 'CD';
                }
                if ($k === 'ga4_id' && $v !== '' && !preg_match('/^G-[A-Z0-9]{4,15}$/', $v)) {
                    flash('err', 'Το GA4 ID πρέπει να έχει τη μορφή G-XXXXXXX.');
                    continue;
                }
                set_setting($k, mb_substr($v, 0, 2000));
            }
        }
        @unlink(ROOT . '/data/icon-192.png');
        audit('settings.update');
        flash('ok', 'Οι ρυθμίσεις αποθηκεύτηκαν.');
        redirect(url('/admin/settings'));
    }
    render('admin/settings', ['title' => 'Ρυθμίσεις', 'noindex' => true, 'groups' => settings_groups(), 'area' => 'admin'], 'panel/layout');
}

function admin_orders(): void
{
    require_role('admin');
    $status = (string)input('status');
    $w = $status ? 'WHERE o.status = ?' : '';
    $rows = all("SELECT o.*, u.name, u.email FROM orders o JOIN users u ON u.id = o.user_id $w ORDER BY o.id DESC LIMIT 300", $status ? [$status] : []);
    render('admin/orders', ['title' => 'Παραγγελίες', 'noindex' => true, 'rows' => $rows, 'area' => 'admin'], 'panel/layout');
}

function admin_codes(): void
{
    require_role('admin');
    $status = (string)input('status');
    $qq = strtoupper(trim((string)input('q')));
    $w = ['1=1'];
    $p = [];
    if ($status) {
        $w[] = 'c.status = ?';
        $p[] = $status;
    }
    if ($qq !== '') {
        $w[] = 'c.code LIKE ?';
        $p[] = "%$qq%";
    }
    $rows = all('SELECT c.*, s.title, b.name AS business, u.email FROM codes c JOIN services s ON s.id = c.service_id JOIN businesses b ON b.id = c.business_id JOIN users u ON u.id = c.user_id WHERE '
        . implode(' AND ', $w) . ' ORDER BY c.id DESC LIMIT 300', $p);
    render('admin/codes', ['title' => 'Κωδικοί', 'noindex' => true, 'rows' => $rows, 'area' => 'admin'], 'panel/layout');
}

function admin_commissions(): void
{
    require_role('admin');
    $rows = all("SELECT a.id, u.name, a.region, a.commission_percent, a.commission_fixed_cents,
                   COALESCE(SUM(CASE WHEN cm.status IN ('pending','approved') THEN cm.amount_cents END),0) AS due,
                   COALESCE(SUM(CASE WHEN cm.status = 'paid' THEN cm.amount_cents END),0) AS paid, COUNT(cm.id) AS n
                 FROM agents a JOIN users u ON u.id = a.user_id LEFT JOIN commissions cm ON cm.agent_id = a.id GROUP BY a.id, u.name, a.region, a.commission_percent, a.commission_fixed_cents ORDER BY due DESC");
    render('admin/commissions', ['title' => 'Προμήθειες αντιπροσώπων', 'noindex' => true, 'rows' => $rows, 'area' => 'admin'], 'panel/layout');
}

function admin_commissions_pay(): void
{
    require_role('admin');
    $id = (int)input('agent_id');
    $n = q("UPDATE commissions SET status = 'paid', paid_at = ? WHERE agent_id = ? AND status IN ('pending','approved')", [now(), $id])->rowCount();
    audit('commissions.paid', 'agent', $id, ['rows' => $n]);
    flash('ok', 'Σημειώθηκαν ως πληρωμένες ' . $n . ' προμήθειες.');
    redirect(url('/admin/commissions'));
}

function admin_messages(): void
{
    require_role('admin');
    $rows = all('SELECT * FROM messages ORDER BY id DESC LIMIT 200');
    q('UPDATE messages SET is_read = 1 WHERE is_read = 0');
    render('admin/messages', ['title' => 'Μηνύματα', 'noindex' => true, 'rows' => $rows, 'area' => 'admin'], 'panel/layout');
}

function admin_export(array $p): void
{
    require_role('admin');
    switch ($p['what']) {
        case 'orders':
            $rows = all('SELECT o.number, o.created_at, o.status, u.email, o.total_cents, o.platform_fee_cents, o.payment_provider, o.payment_ref FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC');
            csv_out('orders.csv', ['Αριθμός', 'Ημερομηνία', 'Κατάσταση', 'Email', 'Σύνολο (λεπτά)', 'Προμήθεια (λεπτά)', 'Πάροχος', 'Αναφορά'], array_map('array_values', $rows));
        case 'codes':
            $rows = all('SELECT c.code, c.status, c.created_at, c.expires_at, c.redeemed_at, s.title, b.name, c.paid_cents FROM codes c JOIN services s ON s.id = c.service_id JOIN businesses b ON b.id = c.business_id ORDER BY c.id DESC');
            csv_out('codes.csv', ['Κωδικός', 'Κατάσταση', 'Έκδοση', 'Λήξη', 'Εξαργύρωση', 'Υπηρεσία', 'Επιχείρηση', 'Πληρωμή (λεπτά)'], array_map('array_values', $rows));
        case 'payouts':
            $rows = all("SELECT b.name, b.vat_number, COUNT(c.id) AS n, COALESCE(SUM(c.paid_cents),0) AS gross FROM businesses b JOIN codes c ON c.business_id = b.id WHERE c.status = 'redeemed' GROUP BY b.id, b.name, b.vat_number ORDER BY gross DESC");
            $pct = (int)setting('fee_percent', 0);
            $fixed = (int)setting('fee_fixed_cents', 0);
            csv_out('payouts.csv', ['Επιχείρηση', 'ΑΦΜ', 'Εξαργυρώσεις', 'Μικτά (€)', 'Προμήθεια πλατφόρμας (€)', 'Προς απόδοση (€)'], array_map(function ($r) use ($pct, $fixed) {
                $fee = (int)round($r['gross'] * $pct / 100) + $fixed * (int)$r['n'];
                return [$r['name'], $r['vat_number'], $r['n'], number_format($r['gross'] / 100, 2, ',', ''), number_format($fee / 100, 2, ',', ''), number_format(max(0, $r['gross'] - $fee) / 100, 2, ',', '')];
            }, $rows));
    }
    abort(404);
}

function admin_demo(): void
{
    require_role('admin');
    if (is_post()) {
        if (input('action') === 'load') {
            seed_demo();
            flash('ok', 'Φορτώθηκαν δεδομένα επίδειξης. Όλα έχουν την ένδειξη «(δείγμα)».');
        } elseif (input('action') === 'clear' && input('confirm') === 'yes') {
            clear_demo();
            flash('ok', 'Τα δεδομένα επίδειξης διαγράφηκαν.');
        } else {
            flash('err', 'Επιβεβαίωσε τη διαγραφή τσεκάροντας το κουτί.');
        }
        redirect(url('/admin/demo'));
    }
    render('admin/demo', ['title' => 'Δεδομένα επίδειξης', 'noindex' => true, 'loaded' => setting('demo_loaded') === '1', 'area' => 'admin'], 'panel/layout');
}
