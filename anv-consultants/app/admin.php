<?php
declare(strict_types=1);

namespace App;

const LOCK_MAX = 5;
const LOCK_WINDOW = 900; // 15 minutes
const SESSION_IDLE = 7200;
const PAGE_TYPES = ['home' => 'Αρχική', 'service' => 'Υπηρεσία (κύρια)', 'subservice' => 'Υποϋπηρεσία', 'page' => 'Σελίδα', 'faq' => 'FAQ', 'contact' => 'Επικοινωνία', 'legal' => 'Νομική σελίδα'];
const SCHEMA_TYPES = ['WebPage', 'Service', 'AboutPage', 'ContactPage', 'FAQPage', 'CollectionPage'];
const REDIRECT_CODES = [301, 302, 307, 308, 410, 451];

function admin_route(string $path, string $method): void
{
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    session_start_secure();

    $sub = trim(substr($path, strlen('/admin')), '/');
    $hasAdmin = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;

    if (!$hasAdmin) {
        if ($sub !== 'setup') {
            redirect('/admin/setup');
        }
        admin_setup($method);
        return;
    }
    if ($sub === 'setup') {
        redirect('/admin/');
    }
    if ($sub === 'login') {
        admin_login($method);
        return;
    }

    // auth gate
    if (empty($_SESSION['admin_id']) || (time() - (int) ($_SESSION['last_seen'] ?? 0)) > SESSION_IDLE) {
        $_SESSION = [];
        session_regenerate_id(true);
        if (str_starts_with($sub, 'api/')) {
            json_out(['error' => 'Η σύνδεση έληξε. Συνδεθείτε ξανά.'], 401);
        }
        redirect('/admin/login');
    }
    $_SESSION['last_seen'] = time();

    if ($method === 'POST') {
        csrf_check();
    }

    match (true) {
        $sub === '' => admin_dashboard(),
        $sub === 'logout' && $method === 'POST' => admin_logout(),
        $sub === 'pages' => admin_pages(),
        $sub === 'pages/edit' => admin_page_edit($method),
        $sub === 'pages/delete' => admin_page_delete($method),
        $sub === 'api/analyze' && $method === 'POST' => admin_api_analyze(),
        $sub === 'api/slug' && $method === 'POST' => json_out(['slug' => slugify((string) ($_POST['text'] ?? ''))]),
        $sub === 'import' => admin_import($method),
        $sub === 'redirects' => admin_redirects($method),
        $sub === '404' => admin_404($method),
        $sub === 'media' => admin_media($method),
        $sub === 'settings' => admin_settings($method),
        $sub === 'backup' && $method === 'POST' => admin_backup(),
        $sub === 'account' => admin_account($method),
        default => admin_render('notfound_admin', ['title' => 'Δεν βρέθηκε'], 404),
    };
}

function admin_render(string $view, array $vars = [], int $code = 200): void
{
    $vars['flash'] = flash();
    $vars['content'] = view('admin/' . $view, $vars);
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo view('admin/layout', $vars);
}

/* ---------------- auth ---------------- */

function admin_setup(string $method): void
{
    $err = '';
    if ($method === 'POST') {
        csrf_check();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Συμπληρώστε ένα έγκυρο email.';
        } elseif (mb_strlen($pass) < 12) {
            $err = 'Ο κωδικός πρέπει να έχει τουλάχιστον 12 χαρακτήρες.';
        } elseif ($pass !== $pass2) {
            $err = 'Οι δύο κωδικοί δεν ταιριάζουν.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) {
                $pdo->prepare('INSERT INTO admins(email, name, pass_hash, created_at) VALUES(?,?,?,?)')
                    ->execute([$email, $name, password_hash($pass, PASSWORD_DEFAULT), now()]);
            }
            $pdo->commit();
            flash('Ο λογαριασμός δημιουργήθηκε. Συνδεθείτε.');
            redirect('/admin/login');
        }
    }
    admin_render('setup', ['title' => 'Πρώτη ρύθμιση', 'err' => $err, 'bare' => true]);
}

function login_locked(string $ip, string $email): int
{
    $since = time() - LOCK_WINDOW;
    $st = db()->prepare('SELECT COUNT(*), MAX(at) FROM login_attempts WHERE success = 0 AND at > ? AND (ip = ? OR (email != \'\' AND email = ?))');
    $st->execute([$since, $ip, $email]);
    [$n, $last] = $st->fetch(\PDO::FETCH_NUM);
    return (int) $n >= LOCK_MAX ? max(1, (int) ceil(((int) $last + LOCK_WINDOW - time()) / 60)) : 0;
}

function admin_login(string $method): void
{
    if (!empty($_SESSION['admin_id'])) {
        redirect('/admin/');
    }
    $err = '';
    if ($method === 'POST') {
        csrf_check();
        $ip = client_ip();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $pass = (string) ($_POST['password'] ?? '');
        db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 86400]);
        if ($mins = login_locked($ip, $email)) {
            $err = "Πολλές αποτυχημένες προσπάθειες. Δοκιμάστε ξανά σε $mins λεπτά.";
        } else {
            $st = db()->prepare('SELECT * FROM admins WHERE email = ?');
            $st->execute([$email]);
            $a = $st->fetch();
            $ok = $a && password_verify($pass, $a['pass_hash']);
            if (!$a) {
                password_verify($pass, '$2y$10$abcdefghijklmnopqrstuuJ0b6oIiWl3V8q2o3xS1v5k8Qe9z8Oa'); // equalise timing
            }
            db()->prepare('INSERT INTO login_attempts(ip, email, success, at) VALUES(?,?,?,?)')->execute([$ip, $email, $ok ? 1 : 0, time()]);
            if ($ok) {
                if (password_needs_rehash($a['pass_hash'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE admins SET pass_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $a['id']]);
                }
                db()->prepare('DELETE FROM login_attempts WHERE success = 0 AND (ip = ? OR email = ?)')->execute([$ip, $email]);
                db()->prepare('UPDATE admins SET last_login = ? WHERE id = ?')->execute([now(), $a['id']]);
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $a['id'];
                $_SESSION['admin_email'] = $a['email'];
                $_SESSION['last_seen'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                redirect('/admin/');
            }
            $left = LOCK_MAX - (login_locked($ip, $email) ? LOCK_MAX : count_fails($ip, $email));
            $err = $left > 0 ? "Λάθος email ή κωδικός. Απομένουν $left προσπάθειες." : 'Πολλές αποτυχημένες προσπάθειες. Δοκιμάστε ξανά σε 15 λεπτά.';
        }
    }
    admin_render('login', ['title' => 'Σύνδεση', 'err' => $err, 'bare' => true]);
}

function count_fails(string $ip, string $email): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND at > ? AND (ip = ? OR (email != \'\' AND email = ?))');
    $st->execute([time() - LOCK_WINDOW, $ip, $email]);
    return (int) $st->fetchColumn();
}

function admin_logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();
    setcookie('anv_sess', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    redirect('/admin/login');
}

function admin_account(string $method): void
{
    if ($method === 'POST') {
        $st = db()->prepare('SELECT * FROM admins WHERE id = ?');
        $st->execute([$_SESSION['admin_id']]);
        $a = $st->fetch();
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        if (!$a || !password_verify($cur, $a['pass_hash'])) {
            flash('Ο τρέχων κωδικός είναι λάθος.', 'err');
        } elseif (mb_strlen($new) < 12 || $new !== (string) ($_POST['password2'] ?? '')) {
            flash('Ο νέος κωδικός πρέπει να έχει 12+ χαρακτήρες και να ταιριάζει με την επιβεβαίωση.', 'err');
        } else {
            db()->prepare('UPDATE admins SET pass_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $a['id']]);
            session_regenerate_id(true);
            flash('Ο κωδικός άλλαξε.');
        }
        redirect('/admin/account');
    }
    admin_render('account', ['title' => 'Λογαριασμός']);
}

/* ---------------- dashboard ---------------- */

function admin_dashboard(): void
{
    $pdo = db();
    foreach ($pdo->query('SELECT id FROM pages WHERE seo_score = 0 AND aeo_score = 0')->fetchAll(\PDO::FETCH_COLUMN) as $id) {
        refresh_scores((int) $id);
    }
    $count = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
    $kpi = [
        'published' => $count("SELECT COUNT(*) FROM pages WHERE status='published'"),
        'drafts' => $count("SELECT COUNT(*) FROM pages WHERE status='draft'"),
        'scheduled' => $count("SELECT COUNT(*) FROM pages WHERE status='scheduled'"),
        'seo' => $count("SELECT COALESCE(ROUND(AVG(seo_score)),0) FROM pages WHERE status='published' AND type!='legal'"),
        'aeo' => $count("SELECT COALESCE(ROUND(AVG(aeo_score)),0) FROM pages WHERE status='published' AND type!='legal'"),
        'placeholders' => $count('SELECT COUNT(*) FROM pages WHERE is_placeholder=1'),
        '404' => $count('SELECT COUNT(*) FROM notfound WHERE ignored=0'),
        'redirects' => $count('SELECT COUNT(*) FROM redirects WHERE active=1'),
    ];
    $weak = $pdo->query("SELECT id, title, path, seo_score, aeo_score FROM pages WHERE type!='legal' AND (seo_score < 60 OR aeo_score < 60) ORDER BY (seo_score + aeo_score) LIMIT 12")->fetchAll();
    $missingMeta = $pdo->query("SELECT id, title, path FROM pages WHERE (meta_desc = '' OR focus_kw = '') AND type NOT IN ('legal') ORDER BY sort")->fetchAll();
    $placeholders = $pdo->query('SELECT id, title, path FROM pages WHERE is_placeholder=1 ORDER BY sort, id')->fetchAll();

    $checks = [];
    $chk = function (string $label, bool $ok, string $fix, string $link = '/admin/settings') use (&$checks) {
        $checks[] = compact('label', 'ok', 'fix', 'link');
    };
    $chk('Τηλέφωνο γραφείου', (bool) setting('phone'), 'Συμπληρώστε το τηλέφωνο στις Ρυθμίσεις.');
    $chk('Email γραφείου', (bool) setting('email'), 'Συμπληρώστε το email στις Ρυθμίσεις.');
    $chk('Local SEO: διεύθυνση', setting('address_street') && setting('address_city') && setting('address_postcode'), 'Συμπληρώστε οδό, πόλη και ΤΚ.');
    $chk('Local SEO: συντεταγμένες χάρτη', is_numeric(setting('geo_lat')) && is_numeric(setting('geo_lng')), 'Συμπληρώστε γεωγραφικό πλάτος/μήκος για ακριβή πινέζα.');
    $chk('Ωράριο επιβεβαιωμένο', setting('hours_confirmed') === '1', 'Ελέγξτε το ωράριο και τσεκάρετε «Το ωράριο είναι σωστό».');
    $chk('Social profiles (sameAs)', (bool) same_as(), 'Προσθέστε τουλάχιστον ένα προφίλ ή το Google Business Profile.');
    $chk('Google Search Console', (bool) setting('gsc_verification'), 'Προσθέστε τον κωδικό επαλήθευσης και υποβάλετε το sitemap.');
    $chk('Google Analytics 4 (προαιρετικό)', (bool) setting('ga4_id'), 'Προσθέστε το Measurement ID (G-XXXX).');
    $chk('Κανονικό URL (https, χωρίς www)', (bool) setting('canonical_url'), 'Ορίστε π.χ. https://anv.gr μόλις ενεργοποιηθεί το SSL.');
    $chk('Λογότυπο σε υψηλή ανάλυση', setting('logo') !== '/assets/img/logo.png', 'Ανεβάστε το λογότυπο σε μεγάλη ανάλυση από τα Πολυμέσα.');
    $chk('Φωτογραφία hero αρχικής', (bool) setting('home_hero_image'), 'Προαιρετικό: ανεβάστε φωτογραφία τοπίου. Αλλιώς εμφανίζεται το ζωγραφισμένο τοπίο.');
    $lb = (string) setting('last_backup', '');
    $chk('Backup τις τελευταίες 30 ημέρες', $lb !== '' && strtotime($lb) > time() - 30 * 86400, 'Κατεβάστε backup από το κουμπί παρακάτω.', '/admin/#backup');
    $chk('Συντήρηση απενεργοποιημένη', setting('maintenance') !== '1', 'Ο ιστότοπος δείχνει 503 στους επισκέπτες.');

    admin_render('dashboard', compact('kpi', 'weak', 'missingMeta', 'placeholders', 'checks', 'lb') + ['title' => 'Πίνακας ελέγχου']);
}

/* ---------------- pages ---------------- */

function admin_pages(): void
{
    $rows = db()->query('SELECT p.*, (SELECT title FROM pages q WHERE q.id = p.parent_id) AS parent_title FROM pages p ORDER BY CASE p.type WHEN \'home\' THEN 0 ELSE 1 END, COALESCE(p.parent_id, p.id), p.parent_id IS NOT NULL, p.sort, p.id')->fetchAll();
    // order as tree: top-level by sort, children under parent
    $top = array_filter($rows, fn($r) => !$r['parent_id']);
    usort($top, fn($a, $b) => [$a['type'] !== 'home', (int) $a['sort'], (int) $a['id']] <=> [$b['type'] !== 'home', (int) $b['sort'], (int) $b['id']]);
    $tree = [];
    foreach ($top as $t) {
        $tree[] = $t + ['depth' => 0];
        $kids = array_filter($rows, fn($r) => (int) $r['parent_id'] === (int) $t['id']);
        usort($kids, fn($a, $b) => [(int) $a['sort'], $a['title']] <=> [(int) $b['sort'], $b['title']]);
        foreach ($kids as $k) {
            $tree[] = $k + ['depth' => 1];
        }
    }
    $known = array_column($tree, 'id');
    foreach ($rows as $r) {
        if (!in_array($r['id'], $known, true)) {
            $tree[] = $r + ['depth' => 1];
        }
    }
    admin_render('pages', ['title' => 'Σελίδες', 'rows' => $tree]);
}

function page_form_defaults(): array
{
    return [
        'id' => 0, 'type' => 'subservice', 'parent_id' => null, 'slug' => '', 'path' => '', 'title' => '', 'nav_label' => '', 'h1' => '', 'intro' => '', 'tldr' => '',
        'key_points' => '', 'body_md' => '', 'body_html' => '', 'faq_json' => '[]', 'howto_json' => '[]', 'howto_title' => '', 'sources' => '', 'tags' => '', 'icon' => '',
        'hero_image' => '', 'hero_alt' => '', 'meta_title' => '', 'meta_desc' => '', 'focus_kw' => '', 'secondary_kws' => '', 'schema_type' => 'Service',
        'custom_jsonld' => '', 'noindex' => 0, 'in_nav' => 0, 'sort' => 0, 'status' => 'draft', 'publish_at' => '', 'is_placeholder' => 0, 'author' => '',
        'seo_score' => 0, 'aeo_score' => 0,
    ];
}

/** Collects page fields from POST (shared by save and live analyzer). */
function page_from_post(): array
{
    $g = fn(string $k) => trim((string) ($_POST[$k] ?? ''));
    $faq = [];
    foreach ((array) ($_POST['faq_q'] ?? []) as $i => $q) {
        $a = (string) ($_POST['faq_a'][$i] ?? '');
        if (trim((string) $q) !== '' || trim($a) !== '') {
            $faq[] = ['q' => trim((string) $q), 'a' => trim($a)];
        }
    }
    $how = [];
    foreach ((array) ($_POST['howto_name'] ?? []) as $i => $n) {
        $t = (string) ($_POST['howto_text'][$i] ?? '');
        if (trim((string) $n) !== '') {
            $how[] = ['name' => trim((string) $n), 'text' => trim($t)];
        }
    }
    $type = array_key_exists($g('type'), PAGE_TYPES) ? $g('type') : 'page';
    return [
        'id' => (int) ($_POST['id'] ?? 0),
        'type' => $type,
        'parent_id' => (int) ($_POST['parent_id'] ?? 0) ?: null,
        'slug' => $type === 'home' ? '' : slugify($g('slug') !== '' ? $g('slug') : $g('title')),
        'title' => $g('title'),
        'nav_label' => $g('nav_label'),
        'h1' => $g('h1'),
        'intro' => $g('intro'),
        'tldr' => $g('tldr'),
        'key_points' => $g('key_points'),
        'body_md' => (string) ($_POST['body_md'] ?? ''),
        'faq_json' => json_encode($faq, JSON_UNESCAPED_UNICODE),
        'howto_json' => json_encode($how, JSON_UNESCAPED_UNICODE),
        'howto_title' => $g('howto_title'),
        'sources' => $g('sources'),
        'tags' => $g('tags'),
        'icon' => in_array($g('icon'), ['', 'ledger', 'shield', 'bolt'], true) ? $g('icon') : '',
        'hero_image' => preg_match('#^/uploads/[A-Za-z0-9/_.\-]+$#', $g('hero_image')) ? $g('hero_image') : '',
        'hero_alt' => $g('hero_alt'),
        'meta_title' => $g('meta_title'),
        'meta_desc' => $g('meta_desc'),
        'focus_kw' => $g('focus_kw'),
        'secondary_kws' => $g('secondary_kws'),
        'schema_type' => in_array($g('schema_type'), SCHEMA_TYPES, true) ? $g('schema_type') : 'WebPage',
        'custom_jsonld' => (string) ($_POST['custom_jsonld'] ?? ''),
        'noindex' => isset($_POST['noindex']) ? 1 : 0,
        'in_nav' => isset($_POST['in_nav']) ? 1 : 0,
        'sort' => (int) ($_POST['sort'] ?? 0),
        'status' => in_array($g('status'), ['published', 'draft', 'scheduled'], true) ? $g('status') : 'draft',
        'publish_at' => $g('publish_at'),
        'is_placeholder' => isset($_POST['is_placeholder']) ? 1 : 0,
        'author' => $g('author'),
    ];
}

function admin_page_edit(string $method): void
{
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    $existing = $id ? page_by_id($id) : null;
    if ($id && !$existing) {
        flash('Η σελίδα δεν βρέθηκε.', 'err');
        redirect('/admin/pages');
    }
    $errors = [];
    $p = $existing ?? page_form_defaults();
    if (!$existing && isset($_SESSION['import_draft'])) {
        $p = array_merge($p, $_SESSION['import_draft']);
        unset($_SESSION['import_draft']);
    }
    if (!$existing && isset($_GET['parent'])) {
        $p['parent_id'] = (int) $_GET['parent'];
    }

    if ($method === 'POST') {
        $p = array_merge($p, page_from_post());
        $p['id'] = $id;
        if ($existing && $existing['type'] === 'home') {
            $p['type'] = 'home';
            $p['parent_id'] = null;
        }
        if ($p['title'] === '') {
            $errors[] = 'Ο τίτλος είναι υποχρεωτικός.';
        }
        if ($p['type'] === 'home' && (!$existing || $existing['type'] !== 'home')) {
            $errors[] = 'Υπάρχει ήδη αρχική σελίδα.';
        }
        if ($p['type'] === 'subservice') {
            $par = $p['parent_id'] ? page_by_id((int) $p['parent_id']) : null;
            if (!$par || $par['type'] !== 'service') {
                $errors[] = 'Μια υποϋπηρεσία χρειάζεται γονική υπηρεσία (Λογιστικά, Ασφάλειες ή Ρεύμα).';
            }
        }
        if ($p['parent_id'] && $id && (int) $p['parent_id'] === $id) {
            $errors[] = 'Μια σελίδα δεν μπορεί να είναι γονέας του εαυτού της.';
        }
        if ($p['status'] === 'scheduled') {
            $ts = strtotime(str_replace('T', ' ', $p['publish_at']));
            if (!$ts || $ts <= time()) {
                $errors[] = 'Για προγραμματισμένη δημοσίευση ορίστε μελλοντική ημερομηνία.';
            } else {
                $p['publish_at'] = date('Y-m-d H:i:s', $ts);
            }
        } else {
            $p['publish_at'] = null;
        }
        if (trim($p['custom_jsonld']) !== '' && !is_array(json_decode($p['custom_jsonld'], true))) {
            $errors[] = 'Το custom JSON-LD δεν είναι έγκυρο JSON.';
        }
        $path = build_path($p['parent_id'], $p['slug'], $p['type']);
        if (preg_match('#^/(admin|assets|uploads|data|app|views)/#', $path) || in_array($path, ['/sitemap.xml/', '/robots.txt/', '/llms.txt/', '/feed.xml/'], true)) {
            $errors[] = 'Αυτό το slug είναι δεσμευμένο από το σύστημα.';
        }
        $other = page_by_path($path);
        if ($other && (int) $other['id'] !== $id) {
            $errors[] = 'Υπάρχει ήδη σελίδα με διεύθυνση ' . $path . '. Αλλάξτε το slug.';
        }

        if (!$errors) {
            $p['path'] = $path;
            $p['body_html'] = markdown($p['body_md']);
            $p['updated_at'] = now();
            $fields = array_keys(page_from_post());
            $fields = array_values(array_diff($fields, ['id']));
            $fields = array_merge($fields, ['path', 'body_html', 'updated_at']);
            $pdo = db();
            $pdo->beginTransaction();
            if ($existing) {
                $set = implode(', ', array_map(fn($f) => "$f = ?", $fields));
                $pdo->prepare("UPDATE pages SET $set WHERE id = ?")->execute([...array_map(fn($f) => $p[$f], $fields), $id]);
                if ($existing['path'] !== $path) {
                    cascade_path_change($id, $existing['path'], $path);
                }
            } else {
                $fields[] = 'created_at';
                $p['created_at'] = now();
                $pdo->prepare('INSERT INTO pages(' . implode(',', $fields) . ') VALUES(' . implode(',', array_fill(0, count($fields), '?')) . ')')
                    ->execute(array_map(fn($f) => $p[$f], $fields));
                $id = (int) $pdo->lastInsertId();
            }
            // a live page must never be shadowed by an exact redirect
            $pdo->prepare("DELETE FROM redirects WHERE match_type='exact' AND source = ?")->execute([$path]);
            $pdo->commit();
            refresh_scores($id);
            $msg = 'Αποθηκεύτηκε.';
            if ($existing && $existing['path'] !== $path) {
                $msg .= ' Η διεύθυνση άλλαξε: δημιουργήθηκε 301 από ' . $existing['path'] . ' προς ' . $path . '.';
            }
            flash($msg);
            redirect('/admin/pages/edit?id=' . $id);
        }
    }

    $parents = db()->query("SELECT id, title, type FROM pages WHERE type IN ('service','page','faq') ORDER BY sort")->fetchAll();
    $media = db()->query('SELECT path, alt FROM media ORDER BY id DESC')->fetchAll();
    $analysis = analyze($p);
    admin_render('edit', ['title' => $existing ? 'Επεξεργασία: ' . $existing['title'] : 'Νέα σελίδα', 'p' => $p, 'errors' => $errors, 'parents' => $parents, 'media' => $media, 'analysis' => $analysis, 'existing' => $existing]);
}

function admin_api_analyze(): void
{
    $p = page_from_post();
    $p['id'] = (int) ($_POST['id'] ?? 0);
    $a = analyze($p);
    $a['path'] = build_path($p['parent_id'], $p['slug'], $p['type']);
    $a['slug'] = $p['slug'];
    json_out($a);
}

function admin_page_delete(string $method): void
{
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    $p = page_by_id($id);
    if (!$p) {
        flash('Η σελίδα δεν βρέθηκε.', 'err');
        redirect('/admin/pages');
    }
    if ($p['type'] === 'home') {
        flash('Η αρχική σελίδα δεν διαγράφεται.', 'err');
        redirect('/admin/pages');
    }
    $kids = (int) db()->query('SELECT COUNT(*) FROM pages WHERE parent_id = ' . (int) $id)->fetchColumn();
    $parent = $p['parent_id'] ? page_by_id((int) $p['parent_id']) : null;
    if ($method === 'POST') {
        if ($kids > 0) {
            flash('Η σελίδα έχει υποσελίδες. Μετακινήστε ή διαγράψτε τις πρώτα.', 'err');
            redirect('/admin/pages/delete?id=' . $id);
        }
        if (trim((string) ($_POST['confirm'] ?? '')) !== 'ΔΙΑΓΡΑΦΗ') {
            flash('Για επιβεβαίωση πληκτρολογήστε ΔΙΑΓΡΑΦΗ με κεφαλαία.', 'err');
            redirect('/admin/pages/delete?id=' . $id);
        }
        $after = (string) ($_POST['after'] ?? '410');
        db()->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
        if ($after === '301') {
            add_redirect($p['path'], $parent ? $parent['path'] : '/', 301, 'Αυτόματο: διαγραφή σελίδας');
        } elseif ($after === '410') {
            add_redirect($p['path'], '', 410, 'Αυτόματο: διαγραφή σελίδας');
        }
        flash('Η σελίδα «' . $p['title'] . '» διαγράφηκε.');
        redirect('/admin/pages');
    }
    admin_render('confirm', ['title' => 'Διαγραφή σελίδας', 'p' => $p, 'kids' => $kids, 'parent' => $parent]);
}

/* ---------------- import ---------------- */

function admin_import(string $method): void
{
    if ($method === 'POST') {
        $md = (string) ($_POST['markdown'] ?? '');
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            if ($_FILES['file']['size'] > 1024 * 1024) {
                flash('Το αρχείο είναι μεγαλύτερο από 1 MB.', 'err');
                redirect('/admin/import');
            }
            $md = (string) file_get_contents($_FILES['file']['tmp_name']);
        }
        if (trim($md) === '' || !mb_check_encoding($md, 'UTF-8')) {
            flash('Επικολλήστε κείμενο markdown ή ανεβάστε αρχείο .md σε UTF-8.', 'err');
            redirect('/admin/import');
        }
        $draft = import_markdown($md);
        $draft['type'] = in_array($_POST['type'] ?? '', ['subservice', 'page', 'service'], true) ? $_POST['type'] : 'subservice';
        $draft['parent_id'] = (int) ($_POST['parent_id'] ?? 0) ?: null;
        $draft['schema_type'] = $draft['type'] === 'page' ? 'WebPage' : 'Service';
        $draft['status'] = 'draft';
        $_SESSION['import_draft'] = $draft;
        flash('Το markdown εισήχθη ως πρόχειρο. Ελέγξτε τα πεδία και πατήστε Αποθήκευση.');
        redirect('/admin/pages/edit');
    }
    $parents = db()->query("SELECT id, title FROM pages WHERE type = 'service' ORDER BY sort")->fetchAll();
    admin_render('import', ['title' => 'Εισαγωγή markdown', 'parents' => $parents]);
}

/* ---------------- redirects ---------------- */

/** @return string[] errors */
function validate_redirect(string $source, string $target, int $code, string $type, int $id = 0): array
{
    $e = [];
    if (!in_array($code, REDIRECT_CODES, true)) {
        $e[] = 'Μη έγκυρος κωδικός.';
    }
    if ($type === 'exact' && !str_starts_with($source, '/')) {
        $e[] = 'Η πηγή πρέπει να ξεκινά με /.';
    }
    if ($type === 'regex' && @preg_match('#' . str_replace('#', '\#', $source) . '#u', '') === false) {
        $e[] = 'Η κανονική έκφραση (regex) δεν είναι έγκυρη.';
    }
    $needsTarget = !in_array($code, [410, 451], true);
    if ($needsTarget && $target === '') {
        $e[] = 'Ο προορισμός είναι υποχρεωτικός για ' . $code . '.';
    }
    if ($needsTarget && $type === 'exact' && rtrim($source, '/') === rtrim($target, '/')) {
        $e[] = 'Η πηγή και ο προορισμός είναι ίδια.';
    }
    if ($type === 'exact' && page_by_path(str_ends_with($source, '/') ? $source : $source . '/') && (int) ($_POST['force'] ?? 0) !== 1) {
        $e[] = 'Η πηγή είναι ζωντανή σελίδα· η σελίδα υπερισχύει της ανακατεύθυνσης. Αλλάξτε πρώτα το slug της σελίδας.';
    }
    if ($needsTarget && $type === 'exact' && str_starts_with($target, '/')) {
        // walk the chain from target; detect loop back to source
        $seen = [rtrim($source, '/') . '/' => true];
        $cur = $target;
        for ($i = 0; $i < 20; $i++) {
            $key = rtrim($cur, '/') . '/';
            if (isset($seen[$key])) {
                $e[] = 'Η ανακατεύθυνση δημιουργεί βρόχο (loop).';
                break;
            }
            $seen[$key] = true;
            $st = db()->prepare("SELECT id, target, code FROM redirects WHERE active=1 AND match_type='exact' AND (source = ? OR source = ?) AND id != ?");
            $st->execute([$cur, rtrim($cur, '/'), $id]);
            $next = $st->fetch();
            if (!$next || in_array((int) $next['code'], [410, 451], true)) {
                break;
            }
            $cur = $next['target'];
        }
    }
    return $e;
}

/** Follows existing exact redirects from $target to its final destination (prevents chains). */
function final_target(string $target, int $id = 0): string
{
    $cur = $target;
    for ($i = 0; $i < 20; $i++) {
        $st = db()->prepare("SELECT target, code FROM redirects WHERE active=1 AND match_type='exact' AND (source = ? OR source = ?) AND id != ?");
        $st->execute([$cur, rtrim($cur, '/'), $id]);
        $n = $st->fetch();
        if (!$n || in_array((int) $n['code'], [410, 451], true) || $n['target'] === $cur) {
            return $cur;
        }
        $cur = $n['target'];
    }
    return $cur;
}

function admin_redirects(string $method): void
{
    if ($method === 'POST') {
        $act = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        if ($act === 'save') {
            $type = ($_POST['match_type'] ?? 'exact') === 'regex' ? 'regex' : 'exact';
            $source = trim((string) ($_POST['source'] ?? ''));
            $target = trim((string) ($_POST['target'] ?? ''));
            $code = (int) ($_POST['code'] ?? 301);
            if ($type === 'exact' && $source !== '') {
                $source = '/' . ltrim((string) (parse_url($source, PHP_URL_PATH) ?? $source), '/');
            }
            if ($target !== '' && !preg_match('#^https?://#', $target)) {
                $target = '/' . ltrim($target, '/');
            }
            if (in_array($code, [410, 451], true)) {
                $target = '';
            }
            $errs = validate_redirect($source, $target, $code, $type, $id);
            if ($errs) {
                flash(implode(' ', $errs), 'err');
                $_SESSION['redirect_form'] = compact('source', 'target', 'code', 'type', 'id');
                redirect('/admin/redirects');
            }
            $collapsed = '';
            if ($type === 'exact' && $target !== '' && str_starts_with($target, '/')) {
                $final = final_target($target, $id);
                if ($final !== $target) {
                    $collapsed = " Η αλυσίδα συμπτύχθηκε: ο προορισμός έγινε $final.";
                    $target = $final;
                }
            }
            $note = trim((string) ($_POST['note'] ?? ''));
            $active = isset($_POST['active']) ? 1 : 0;
            if ($id) {
                db()->prepare('UPDATE redirects SET source=?, target=?, code=?, match_type=?, active=?, note=? WHERE id=?')->execute([$source, $target, $code, $type, $active, $note, $id]);
            } else {
                db()->prepare('INSERT INTO redirects(source, target, code, match_type, active, note, created_at) VALUES(?,?,?,?,?,?,?)')->execute([$source, $target, $code, $type, $active, $note, now()]);
            }
            if ($type === 'exact' && $target !== '') {
                // anything that pointed to this source now points straight to the new target
                db()->prepare("UPDATE redirects SET target = ? WHERE match_type='exact' AND (target = ? OR target = ?) AND source != ?")->execute([$target, $source, rtrim($source, '/'), $target]);
            }
            db()->prepare('DELETE FROM notfound WHERE path = ?')->execute([$source]);
            flash('Η ανακατεύθυνση αποθηκεύτηκε.' . $collapsed);
        } elseif ($act === 'toggle') {
            db()->prepare('UPDATE redirects SET active = 1 - active WHERE id = ?')->execute([$id]);
            flash('Η κατάσταση άλλαξε.');
        } elseif ($act === 'delete') {
            if (($_POST['confirm'] ?? '') !== 'yes') {
                $_SESSION['confirm_redirect'] = $id;
                redirect('/admin/redirects#confirm');
            }
            db()->prepare('DELETE FROM redirects WHERE id = ?')->execute([$id]);
            flash('Η ανακατεύθυνση διαγράφηκε.');
        } elseif ($act === 'test') {
            $u = (string) ($_POST['url'] ?? '');
            $path = '/' . ltrim((string) (parse_url($u, PHP_URL_PATH) ?? $u), '/');
            $r = find_redirect($path);
            $pg = page_by_path($path);
            $_SESSION['redirect_test'] = $pg ? "$path → ζωντανή σελίδα «{$pg['title']}» (200)" : ($r ? "$path → {$r['code']} " . ($r['_target'] ?: '(χωρίς προορισμό)') . ' (κανόνας #' . $r['id'] . ')' : "$path → 404");
        }
        redirect('/admin/redirects');
    }
    $rows = db()->query('SELECT * FROM redirects ORDER BY id DESC')->fetchAll();
    $form = $_SESSION['redirect_form'] ?? null;
    unset($_SESSION['redirect_form']);
    $edit = isset($_GET['edit']) ? db()->query('SELECT * FROM redirects WHERE id = ' . (int) $_GET['edit'])->fetch() : null;
    $confirm = $_SESSION['confirm_redirect'] ?? null;
    unset($_SESSION['confirm_redirect']);
    $test = $_SESSION['redirect_test'] ?? null;
    unset($_SESSION['redirect_test']);
    if (isset($_GET['source'])) {
        $form = ['source' => (string) $_GET['source'], 'target' => (string) ($_GET['target'] ?? ''), 'code' => (int) ($_GET['code'] ?? 301), 'type' => 'exact', 'id' => 0];
    }
    admin_render('redirects', ['title' => 'Ανακατευθύνσεις', 'rows' => $rows, 'form' => $form, 'edit' => $edit ?: null, 'confirm' => $confirm, 'test' => $test]);
}

/* ---------------- 404 monitor ---------------- */

function suggest_destination(string $path): ?array
{
    $seg = slugify(basename(rtrim($path, '/')));
    $best = null;
    $bestScore = 0.0;
    foreach (published_pages() as $p) {
        $cand = $p['slug'] !== '' ? $p['slug'] : 'home';
        similar_text($seg, $cand, $pct);
        $titlePct = 0.0;
        similar_text($seg, slugify($p['title']), $titlePct);
        $score = max($pct, $titlePct);
        if (str_contains($p['path'], $seg) && strlen($seg) > 3) {
            $score += 20;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $p;
        }
    }
    return $best && $bestScore >= 45 ? ['path' => $best['path'], 'title' => $best['title'], 'score' => (int) min(100, $bestScore)] : null;
}

function admin_404(string $method): void
{
    if ($method === 'POST') {
        $act = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $row = $id ? db()->query('SELECT * FROM notfound WHERE id = ' . $id)->fetch() : null;
        if (in_array($act, ['301', '302', '410'], true) && $row) {
            $code = (int) $act;
            $target = $code === 410 ? '' : trim((string) ($_POST['target'] ?? ''));
            if ($target !== '' && !preg_match('#^https?://#', $target)) {
                $target = '/' . ltrim($target, '/');
            }
            $errs = validate_redirect($row['path'], $target, $code, 'exact');
            if ($errs) {
                flash(implode(' ', $errs), 'err');
            } else {
                if ($target !== '') {
                    $target = final_target($target);
                }
                db()->prepare('INSERT INTO redirects(source, target, code, match_type, active, note, created_at) VALUES(?,?,?,?,1,?,?)')
                    ->execute([$row['path'], $target, $code, 'exact', 'Από 404 Monitor', now()]);
                db()->prepare('DELETE FROM notfound WHERE id = ?')->execute([$id]);
                flash("Δημιουργήθηκε $code για {$row['path']}.");
            }
        } elseif ($act === 'ignore' && $row) {
            db()->prepare('UPDATE notfound SET ignored = 1 WHERE id = ?')->execute([$id]);
            flash('Η διεύθυνση αγνοείται.');
        } elseif ($act === 'delete' && $row) {
            db()->prepare('DELETE FROM notfound WHERE id = ?')->execute([$id]);
            flash('Διαγράφηκε.');
        } elseif ($act === 'cleanup') {
            $n = db()->exec("DELETE FROM notfound WHERE ignored = 1 OR (hits < 2 AND last_hit < datetime('now', '-30 days'))");
            flash("Καθαρισμός: διαγράφηκαν $n εγγραφές (αγνοημένες ή με 1 επίσκεψη παλαιότερη των 30 ημερών).");
        }
        redirect('/admin/404' . (isset($_POST['show']) ? '?show=' . urlencode((string) $_POST['show']) : ''));
    }
    $showIgnored = ($_GET['show'] ?? '') === 'ignored';
    $rows = db()->query('SELECT * FROM notfound WHERE ignored = ' . ($showIgnored ? 1 : 0) . ' ORDER BY hits DESC, last_hit DESC LIMIT 300')->fetchAll();
    foreach ($rows as &$r) {
        $r['suggest'] = suggest_destination($r['path']);
    }
    unset($r);
    admin_render('notfound', ['title' => '404 Monitor', 'rows' => $rows, 'showIgnored' => $showIgnored]);
}

/* ---------------- media ---------------- */

/** @return array{ok:bool, path?:string, error?:string} */
function handle_upload(array $f, string $alt = ''): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return ['ok' => false, 'error' => 'Το αρχείο δεν ανέβηκε (κωδικός ' . (int) ($f['error'] ?? 4) . ').'];
    }
    if ($f['size'] > 10 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Μέγιστο μέγεθος 10 MB.'];
    }
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) {
        return ['ok' => false, 'error' => 'Επιτρέπονται μόνο εικόνες JPG, PNG, WebP και GIF.'];
    }
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || $mime !== $info['mime']) {
        return ['ok' => false, 'error' => 'Ο τύπος του αρχείου δεν ταιριάζει με την επέκτασή του.'];
    }
    if ($info[0] * $info[1] > 50_000_000) {
        return ['ok' => false, 'error' => 'Η εικόνα έχει υπερβολικά μεγάλες διαστάσεις.'];
    }
    $ext = $types[$info[2]];
    $dir = UPLOAD_DIR . '/' . date('Y/m');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'Ο φάκελος uploads δεν είναι εγγράψιμος.'];
    }
    $base = slugify(pathinfo((string) $f['name'], PATHINFO_FILENAME));
    $name = $base . '.' . $ext;
    $i = 2;
    while (is_file("$dir/$name")) {
        $name = $base . '-' . $i++ . '.' . $ext;
    }
    $dest = "$dir/$name";

    // Re-encode through GD: strips metadata/embedded payloads and resizes to max 2000 px.
    $src = match ($ext) {
        'jpg' => @imagecreatefromjpeg($f['tmp_name']),
        'png' => @imagecreatefrompng($f['tmp_name']),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
        'gif' => @imagecreatefromgif($f['tmp_name']),
    };
    if (!$src) {
        return ['ok' => false, 'error' => 'Η εικόνα δεν μπόρεσε να διαβαστεί.'];
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $max = 2000;
    if ($w > $max || $h > $max) {
        $r = min($max / $w, $max / $h);
        $nw = (int) round($w * $r);
        $nh = (int) round($h * $r);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
        [$w, $h] = [$nw, $nh];
    } else {
        imagealphablending($src, false);
        imagesavealpha($src, true);
    }
    $ok = match ($ext) {
        'jpg' => imagejpeg($src, $dest, 82),
        'png' => imagepng($src, $dest, 8),
        'webp' => imagewebp($src, $dest, 80),
        'gif' => imagegif($src, $dest),
    };
    $webp = '';
    if ($ok && in_array($ext, ['jpg', 'png'], true) && function_exists('imagewebp')) {
        $wpath = preg_replace('/\.(jpg|png)$/', '.webp', $dest);
        if ($wpath && !is_file($wpath) && @imagewebp($src, $wpath, 80)) {
            $webp = '/uploads/' . date('Y/m') . '/' . basename($wpath);
        }
    }
    imagedestroy($src);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Η αποθήκευση απέτυχε.'];
    }
    @chmod($dest, 0644);
    $public = '/uploads/' . date('Y/m') . '/' . $name;
    db()->prepare('INSERT INTO media(path, webp, width, height, size, alt, created_at) VALUES(?,?,?,?,?,?,?)')
        ->execute([$public, $webp, $w, $h, (int) filesize($dest), $alt, now()]);
    return ['ok' => true, 'path' => $public];
}

function admin_media(string $method): void
{
    if ($method === 'POST') {
        $act = (string) ($_POST['action'] ?? 'upload');
        if ($act === 'upload') {
            $r = handle_upload($_FILES['file'] ?? [], trim((string) ($_POST['alt'] ?? '')));
            flash($r['ok'] ? 'Η εικόνα ανέβηκε: ' . $r['path'] : $r['error'], $r['ok'] ? 'ok' : 'err');
        } elseif ($act === 'alt') {
            db()->prepare('UPDATE media SET alt = ? WHERE id = ?')->execute([trim((string) ($_POST['alt'] ?? '')), (int) $_POST['id']]);
            flash('Το alt αποθηκεύτηκε.');
        } elseif ($act === 'delete') {
            $m = db()->query('SELECT * FROM media WHERE id = ' . (int) ($_POST['id'] ?? 0))->fetch();
            if ($m && ($_POST['confirm'] ?? '') === 'yes') {
                $st = db()->prepare('SELECT COUNT(*) FROM pages WHERE hero_image = ? OR body_md LIKE ?');
                $st->execute([$m['path'], '%' . $m['path'] . '%']);
                $used = (int) $st->fetchColumn() + (in_array($m['path'], [setting('logo'), setting('og_image'), setting('home_hero_image')], true) ? 1 : 0);
                if ($used) {
                    flash('Η εικόνα χρησιμοποιείται σε ' . $used . ' σημεία και δεν διαγράφηκε.', 'err');
                } else {
                    foreach ([$m['path'], $m['webp']] as $pth) {
                        $file = realpath(ROOT . $pth);
                        if ($pth && $file && str_starts_with($file, realpath(UPLOAD_DIR) . DIRECTORY_SEPARATOR)) {
                            @unlink($file);
                        }
                    }
                    db()->prepare('DELETE FROM media WHERE id = ?')->execute([$m['id']]);
                    flash('Η εικόνα διαγράφηκε.');
                }
            } elseif ($m) {
                $_SESSION['confirm_media'] = (int) $m['id'];
            }
        }
        redirect('/admin/media');
    }
    $rows = db()->query('SELECT * FROM media ORDER BY id DESC')->fetchAll();
    $confirm = $_SESSION['confirm_media'] ?? null;
    unset($_SESSION['confirm_media']);
    admin_render('media', ['title' => 'Πολυμέσα', 'rows' => $rows, 'confirm' => $confirm]);
}

/* ---------------- settings ---------------- */

function settings_fields(): array
{
    return [
        'Στοιχεία επιχείρησης' => [
            'site_name' => ['Επωνυμία (εμφανίζεται στο site)', 'text'],
            'legal_name' => ['Νομική επωνυμία', 'text'],
            'vat_id' => ['ΑΦΜ (προαιρετικό)', 'text'],
            'tagline' => ['Σύντομη περιγραφή', 'text'],
        ],
        'Επικοινωνία' => [
            'phone' => ['Τηλέφωνο', 'tel'],
            'phone2' => ['Δεύτερο τηλέφωνο / κινητό', 'tel'],
            'email' => ['Email', 'email'],
        ],
        'Διεύθυνση & χάρτης' => [
            'address_street' => ['Οδός και αριθμός', 'text'],
            'address_city' => ['Πόλη', 'text'],
            'address_postcode' => ['Ταχυδρομικός κώδικας', 'text'],
            'address_region' => ['Νομός / Περιφέρεια', 'text'],
            'geo_lat' => ['Γεωγραφικό πλάτος (π.χ. 37.9838)', 'text'],
            'geo_lng' => ['Γεωγραφικό μήκος (π.χ. 23.7275)', 'text'],
            'map_query' => ['Αναζήτηση χάρτη (αν δεν δώσετε συντεταγμένες)', 'text'],
            'google_business_url' => ['Σύνδεσμος Google Business Profile', 'url'],
        ],
        'Social profiles' => [
            'social_facebook' => ['Facebook', 'url'],
            'social_instagram' => ['Instagram', 'url'],
            'social_linkedin' => ['LinkedIn', 'url'],
            'social_youtube' => ['YouTube', 'url'],
            'social_tiktok' => ['TikTok', 'url'],
            'social_x' => ['X (Twitter)', 'url'],
        ],
        'Εικόνες' => [
            'logo' => ['Λογότυπο (διαδρομή)', 'media'],
            'og_image' => ['Εικόνα κοινοποίησης (1200×630)', 'media'],
            'home_hero_image' => ['Φωτογραφία hero αρχικής (κενό = ζωγραφισμένο τοπίο)', 'media'],
            'home_hero_alt' => ['Περιγραφή (alt) φωτογραφίας hero', 'text'],
        ],
        'SEO & analytics' => [
            'canonical_url' => ['Κανονικό URL (π.χ. https://anv.gr)', 'url'],
            'gsc_verification' => ['Google Search Console: κωδικός επαλήθευσης (meta content)', 'text'],
            'ga4_id' => ['Google Analytics 4: Measurement ID (G-XXXXXXX)', 'text'],
            'allow_ai_bots' => ['Επιτρέπονται οι AI crawlers (GPTBot, ClaudeBot κ.λπ.)', 'bool'],
            'llms_extra' => ['Επιπλέον κείμενο για το llms.txt', 'textarea'],
        ],
        'Λειτουργία' => [
            'maintenance' => ['Λειτουργία συντήρησης (503 για επισκέπτες)', 'bool'],
        ],
    ];
}

function admin_settings(string $method): void
{
    if ($method === 'POST') {
        $errs = [];
        foreach (settings_fields() as $group) {
            foreach ($group as $key => [$label, $type]) {
                $v = trim((string) ($_POST[$key] ?? ''));
                if ($type === 'bool') {
                    $v = isset($_POST[$key]) ? '1' : '0';
                } elseif ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $errs[] = "$label: μη έγκυρο email.";
                    continue;
                } elseif ($type === 'url' && $v !== '' && !preg_match('#^https?://[^\s<>"]+$#', $v)) {
                    $errs[] = "$label: ο σύνδεσμος πρέπει να ξεκινά με https://";
                    continue;
                } elseif ($type === 'media' && $v !== '' && !preg_match('#^/(uploads|assets/img)/[A-Za-z0-9/_.\-]+$#', $v)) {
                    $errs[] = "$label: επιλέξτε εικόνα από τα Πολυμέσα.";
                    continue;
                }
                if (in_array($key, ['geo_lat', 'geo_lng'], true) && $v !== '' && !is_numeric(str_replace(',', '.', $v))) {
                    $errs[] = "$label: πρέπει να είναι αριθμός.";
                    continue;
                }
                if (in_array($key, ['geo_lat', 'geo_lng'], true)) {
                    $v = str_replace(',', '.', $v);
                }
                if ($key === 'ga4_id' && $v !== '' && !preg_match('/^G-[A-Z0-9]{4,15}$/', $v)) {
                    $errs[] = 'Το GA4 Measurement ID πρέπει να μοιάζει με G-XXXXXXX.';
                    continue;
                }
                if ($key === 'canonical_url') {
                    $v = rtrim($v, '/');
                    if ($v !== '' && strtolower((string) parse_url($v, PHP_URL_HOST)) !== strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')) && !isset($_POST['canonical_confirm'])) {
                        $errs[] = 'Το κανονικό URL διαφέρει από τη διεύθυνση που βλέπετε τώρα. Τσεκάρετε την επιβεβαίωση για να το αποθηκεύσετε (θα γίνεται 301 προς αυτό).';
                        continue;
                    }
                }
                set_setting($key, $v);
            }
        }
        // hours
        $hours = [];
        for ($d = 1; $d <= 7; $d++) {
            $hours[$d] = [];
            for ($s = 0; $s < 2; $s++) {
                $o = (string) ($_POST["h_{$d}_{$s}_o"] ?? '');
                $c = (string) ($_POST["h_{$d}_{$s}_c"] ?? '');
                if (preg_match('/^\d\d:\d\d$/', $o) && preg_match('/^\d\d:\d\d$/', $c)) {
                    if ($c <= $o) {
                        $errs[] = DAY_NAMES[$d] . ': η ώρα κλεισίματος πρέπει να είναι μετά το άνοιγμα.';
                        continue;
                    }
                    $hours[$d][] = [$o, $c];
                }
            }
        }
        set_setting('hours_json', (string) json_encode($hours));
        set_setting('hours_note', trim((string) ($_POST['hours_note'] ?? '')));
        set_setting('hours_confirmed', isset($_POST['hours_confirmed']) ? '1' : '0');
        set_setting('gsc_verification', preg_replace('/[^A-Za-z0-9_\-]/', '', (string) setting('gsc_verification', '')) ?? '');
        flash($errs ? 'Αποθηκεύτηκαν, εκτός από: ' . implode(' ', $errs) : 'Οι ρυθμίσεις αποθηκεύτηκαν.', $errs ? 'err' : 'ok');
        redirect('/admin/settings');
    }
    $media = db()->query('SELECT path FROM media ORDER BY id DESC')->fetchAll(\PDO::FETCH_COLUMN);
    admin_render('settings', ['title' => 'Ρυθμίσεις', 'fields' => settings_fields(), 'media' => $media]);
}

/* ---------------- backup ---------------- */

function admin_backup(): void
{
    if (!class_exists('\ZipArchive')) {
        flash('Η επέκταση zip της PHP δεν είναι διαθέσιμη. Κατεβάστε τον φάκελο data/ από το File Manager.', 'err');
        redirect('/admin/');
    }
    $tmpDb = DATA_DIR . '/backup-' . bin2hex(random_bytes(4)) . '.sqlite';
    db()->exec('VACUUM INTO ' . db()->quote($tmpDb));
    $zipFile = DATA_DIR . '/backup-' . date('Ymd-His') . '.zip';
    $zip = new \ZipArchive();
    $zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFile($tmpDb, 'data/site.sqlite');
    if (is_dir(UPLOAD_DIR)) {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(UPLOAD_DIR, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), 'uploads/' . ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen(UPLOAD_DIR))), '/'));
            }
        }
    }
    $zip->addFromString('README.txt', "Backup ANV " . now() . "\nΕπαναφορά: αντικαταστήστε data/site.sqlite και τον φάκελο uploads/ με αυτά του αρχείου.\n");
    $zip->close();
    @unlink($tmpDb);
    set_setting('last_backup', now());
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    @unlink($zipFile);
    exit;
}
