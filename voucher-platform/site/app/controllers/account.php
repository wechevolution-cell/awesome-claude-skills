<?php
declare(strict_types=1);

function home_for(array $u): string
{
    return match ($u['role']) {
        'admin' => url('/admin'),
        'merchant', 'staff' => url($u['role'] === 'staff' ? '/business/redeem' : '/business'),
        'agent' => url('/agent'),
        default => url('/logariasmos'),
    };
}

function after_login_target(array $u): string
{
    $to = $_SESSION['after_login'] ?? '';
    unset($_SESSION['after_login']);
    if ($to && str_starts_with($to, '/') && !str_starts_with($to, '//')) {
        return $to;
    }
    return home_for($u);
}

function auth_login(): void
{
    if (current_user()) {
        redirect(home_for(current_user()));
    }
    if (is_post()) {
        $r = attempt_login((string)input('email'), (string)input('password'));
        if (!$r['ok']) {
            keep_old(['email' => input('email')]);
            flash('err', $r['error']);
            redirect(url('/eisodos'));
        }
        login_user($r['user']);
        redirect(after_login_target($r['user']));
    }
    render('account/login', ['title' => 'Σύνδεση · ' . site_name(), 'noindex' => true]);
}

function auth_register(): void
{
    if (current_user()) {
        redirect(home_for(current_user()));
    }
    if (setting('allow_registrations') !== '1') {
        abort(403, 'Οι εγγραφές είναι κλειστές', 'Δοκίμασε ξανά αργότερα.');
    }
    if (is_post()) {
        if (looks_like_bot(2)) {
            redirect(url('/'));
        }
        if (rate_limited('register:' . client_ip(), 8, 3600)) {
            flash('err', 'Πολλές εγγραφές από τη σύνδεσή σου. Δοκίμασε ξανά σε μία ώρα.');
            redirect(url('/eggrafi'));
        }
        $name = trim((string)input('name'));
        $email = mb_strtolower(trim((string)input('email')));
        $pass = (string)input('password');
        $errors = [];
        if ($name === '') {
            $errors[] = 'Γράψε το όνομά σου.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Γράψε ένα έγκυρο email.';
        } elseif (one('SELECT id FROM users WHERE email = ?', [$email])) {
            $errors[] = 'Υπάρχει ήδη λογαριασμός με αυτό το email. Συνδέσου ή ζήτα νέο κωδικό πρόσβασης.';
        }
        if ($e = validate_password($pass)) {
            $errors[] = $e;
        }
        if (!input('terms')) {
            $errors[] = 'Χρειάζεται να αποδεχτείς τους όρους χρήσης και την πολιτική απορρήτου.';
        }
        if ($errors) {
            keep_old($_POST);
            flash('err', implode(' ', $errors));
            redirect(url('/eggrafi'));
        }
        $id = create_user($email, $name, $pass, 'consumer', ['referred_by' => $_COOKIE['ref'] ?? null]);
        login_user(one('SELECT * FROM users WHERE id = ?', [$id]));
        flash('ok', 'Καλώς ήρθες! Ο λογαριασμός σου δημιουργήθηκε.');
        redirect(after_login_target(current_user()));
    }
    render('account/register', ['title' => 'Εγγραφή · ' . site_name(), 'noindex' => true]);
}

function auth_forgot(): void
{
    if (is_post()) {
        if (rate_limited('forgot:' . client_ip(), 5, 3600)) {
            flash('err', 'Πολλά αιτήματα. Δοκίμασε ξανά σε μία ώρα.');
            redirect(url('/xehasa-kodiko'));
        }
        $email = mb_strtolower(trim((string)input('email')));
        $u = one("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
        if ($u) {
            $token = create_reset_token((int)$u['id']);
            send_mail($u['email'], 'Νέος κωδικός πρόσβασης · ' . site_name(),
                "Γεια σου {$u['name']},\n\nΓια να ορίσεις νέο κωδικό πρόσβασης άνοιξε αυτόν τον σύνδεσμο μέσα σε μία ώρα:\n" . abs_url('/epanafora/' . $token)
                . "\n\nΑν δεν το ζήτησες εσύ, αγνόησε αυτό το μήνυμα.\n" . site_name());
        }
        flash('ok', 'Αν υπάρχει λογαριασμός με αυτό το email, θα λάβεις σύνδεσμο για νέο κωδικό πρόσβασης.');
        redirect(url('/eisodos'));
    }
    render('account/forgot', ['title' => 'Ξέχασα τον κωδικό · ' . site_name(), 'noindex' => true]);
}

function auth_reset(array $p): void
{
    $r = find_reset($p['token']);
    if (!$r) {
        flash('err', 'Ο σύνδεσμος έληξε ή χρησιμοποιήθηκε ήδη. Ζήτησε νέο.');
        redirect(url('/xehasa-kodiko'));
    }
    if (is_post()) {
        $pass = (string)input('password');
        if ($e = validate_password($pass)) {
            flash('err', $e);
            redirect(url('/epanafora/' . $p['token']));
        }
        update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], ['id' => $r['user_id']]);
        update('password_resets', ['used_at' => now()], ['id' => $r['id']]);
        flash('ok', 'Ο νέος κωδικός πρόσβασης αποθηκεύτηκε. Συνδέσου.');
        redirect(url('/eisodos'));
    }
    render('account/reset', ['title' => 'Νέος κωδικός πρόσβασης', 'noindex' => true, 'token' => $p['token']]);
}

function auth_logout(): void
{
    logout_user();
    session_start();
    flash('ok', 'Αποσυνδέθηκες.');
    redirect(url('/'));
}

function acc_home(): void
{
    $u = require_login();
    if ($u['role'] !== 'consumer') {
        redirect(home_for($u));
    }
    $active = all("SELECT c.*, s.title AS service_title, s.slug AS service_slug, b.name AS business_name, v.label AS variant_label FROM codes c
                   JOIN services s ON s.id = c.service_id JOIN businesses b ON b.id = c.business_id LEFT JOIN service_variants v ON v.id = c.variant_id
                   WHERE c.user_id = ? AND c.status = 'active' AND (c.expires_at IS NULL OR c.expires_at >= ?) ORDER BY c.expires_at ASC LIMIT 3", [$u['id'], now()]);
    $stats = [
        'active' => (int)val("SELECT COUNT(*) FROM codes WHERE user_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at >= ?)", [$u['id'], now()]),
        'redeemed' => (int)val("SELECT COUNT(*) FROM codes WHERE user_id = ? AND status = 'redeemed'", [$u['id']]),
        'saved' => (int)val("SELECT COALESCE(SUM(oi.regular_cents - oi.unit_cents), 0) FROM codes c JOIN order_items oi ON oi.id = c.order_item_id WHERE c.user_id = ? AND c.status = 'redeemed'", [$u['id']]),
        'favs' => (int)val('SELECT COUNT(*) FROM favorites WHERE user_id = ?', [$u['id']]),
    ];
    render('account/home', ['title' => 'Ο λογαριασμός μου', 'noindex' => true, 'u' => $u, 'active' => $active, 'stats' => $stats, 'tab' => 'home']);
}

function acc_codes(): void
{
    $u = require_login();
    $filter = (string)input('f', 'active');
    $cond = match ($filter) {
        'used' => "c.status = 'redeemed'",
        'expired' => "(c.status = 'expired' OR (c.status = 'active' AND c.expires_at < '" . now() . "'))",
        default => "c.status = 'active' AND (c.expires_at IS NULL OR c.expires_at >= '" . now() . "')",
    };
    $codes = all("SELECT c.*, s.title AS service_title, s.slug AS service_slug, s.image, b.name AS business_name, v.label AS variant_label FROM codes c
                  JOIN services s ON s.id = c.service_id JOIN businesses b ON b.id = c.business_id LEFT JOIN service_variants v ON v.id = c.variant_id
                  WHERE c.user_id = ? AND $cond ORDER BY c.id DESC", [$u['id']]);
    render('account/codes', ['title' => 'Οι κωδικοί μου', 'noindex' => true, 'codes' => $codes, 'filter' => $filter, 'tab' => 'codes']);
}

function acc_code(array $p): void
{
    $u = require_login();
    $c = one("SELECT c.*, s.title AS service_title, s.slug AS service_slug, s.terms, b.name AS business_name, v.label AS variant_label FROM codes c
              JOIN services s ON s.id = c.service_id JOIN businesses b ON b.id = c.business_id LEFT JOIN service_variants v ON v.id = c.variant_id
              WHERE c.code = ? AND c.user_id = ?", [strtoupper($p['code']), $u['id']]);
    if (!$c) {
        abort(404);
    }
    $stores = all("SELECT st.* FROM stores st JOIN service_stores ss ON ss.store_id = st.id WHERE ss.service_id = ? AND st.status = 'active'", [$c['service_id']]);
    render('account/code', ['title' => 'Κωδικός ' . $c['code'], 'noindex' => true, 'c' => $c, 'stores' => $stores, 'tab' => 'codes']);
}

function acc_favorites(): void
{
    $u = require_login();
    $items = service_cards('s.id IN (SELECT service_id FROM favorites WHERE user_id = ?)', [$u['id']], 's.id DESC', 100);
    render('account/favorites', ['title' => 'Αγαπημένα', 'noindex' => true, 'items' => $items, 'favs' => favorite_ids(), 'tab' => 'favs']);
}

function acc_orders(): void
{
    $u = require_login();
    $orders = all('SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS n FROM orders o WHERE o.user_id = ? ORDER BY o.id DESC', [$u['id']]);
    render('account/orders', ['title' => 'Οι παραγγελίες μου', 'noindex' => true, 'orders' => $orders, 'tab' => 'orders']);
}

function acc_profile(): void
{
    $u = require_login();
    if (is_post()) {
        $action = input('action');
        if ($action === 'profile') {
            $name = trim((string)input('name'));
            if ($name === '') {
                flash('err', 'Το όνομα δεν μπορεί να είναι κενό.');
            } else {
                update('users', ['name' => mb_substr($name, 0, 190), 'phone' => mb_substr((string)input('phone'), 0, 40)], ['id' => $u['id']]);
                flash('ok', 'Τα στοιχεία σου αποθηκεύτηκαν.');
            }
        } elseif ($action === 'password') {
            if (!password_verify((string)input('current'), $u['password_hash'])) {
                flash('err', 'Ο τρέχων κωδικός πρόσβασης δεν είναι σωστός.');
            } elseif ($e = validate_password((string)input('password'))) {
                flash('err', $e);
            } else {
                update('users', ['password_hash' => password_hash((string)input('password'), PASSWORD_DEFAULT)], ['id' => $u['id']]);
                flash('ok', 'Ο κωδικός πρόσβασης άλλαξε.');
            }
        }
        redirect(url('/logariasmos/stoixeia'));
    }
    render('account/profile', ['title' => 'Στοιχεία λογαριασμού', 'noindex' => true, 'u' => $u, 'tab' => 'profile']);
}
