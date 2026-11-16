<?php
declare(strict_types=1);

/** Service rows ready for cards: lowest variant price, first store city, business name. */
function service_cards(string $where = '1=1', array $params = [], string $order = 's.featured DESC, s.id DESC', int $limit = 12, int $offset = 0): array
{
    $sql = "SELECT s.*, b.name AS business_name, b.slug AS business_slug, c.name AS category_name, c.slug AS category_slug,
                   (SELECT MIN(v.price_cents) FROM service_variants v WHERE v.service_id = s.id) AS v_price,
                   (SELECT MAX(v.regular_cents) FROM service_variants v WHERE v.service_id = s.id AND v.price_cents = (SELECT MIN(v2.price_cents) FROM service_variants v2 WHERE v2.service_id = s.id)) AS v_regular,
                   (SELECT st.city FROM service_stores ss JOIN stores st ON st.id = ss.store_id WHERE ss.service_id = s.id ORDER BY st.id LIMIT 1) AS city,
                   (SELECT COUNT(*) FROM service_variants v WHERE v.service_id = s.id) AS n_variants
            FROM services s JOIN businesses b ON b.id = s.business_id LEFT JOIN categories c ON c.id = s.category_id
            WHERE " . public_service_sql('s') . " AND ($where) ORDER BY $order LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset;
    $rows = all($sql, $params);
    foreach ($rows as &$r) {
        if ($r['v_price'] !== null) {
            $r['price_cents'] = (int)$r['v_price'];
            $r['regular_cents'] = (int)($r['v_regular'] ?? $r['regular_cents']);
        }
        $r['pct'] = discount_pct((int)$r['regular_cents'], (int)$r['price_cents']);
        $r['left'] = codes_left($r);
    }
    return $rows;
}

function active_campaign_sql(string $a = 'c'): string
{
    return "$a.status = 'active' AND ($a.starts_at IS NULL OR $a.starts_at <= '" . now() . "') AND ($a.ends_at IS NULL OR $a.ends_at >= '" . now() . "')";
}

function nav_categories(): array
{
    static $c = null;
    return $c ??= all("SELECT id, name, slug FROM categories WHERE status = 'active' AND parent_id IS NULL ORDER BY sort, name");
}

function favorite_ids(): array
{
    $u = current_user();
    return $u ? array_map('intval', array_column(all('SELECT service_id FROM favorites WHERE user_id = ?', [$u['id']]), 'service_id')) : [];
}

function pub_home(): void
{
    $deal = service_cards('s.featured = 1', [], 's.id DESC', 1)[0] ?? service_cards('1=1', [], 's.price_cents ASC', 1)[0] ?? null;
    $deals = service_cards('1=1', [], 's.featured DESC, s.id DESC', 8);
    usort($deals, fn($a, $b) => $b['pct'] <=> $a['pct']);
    $campaigns = all('SELECT * FROM campaigns c WHERE ' . active_campaign_sql() . ' ORDER BY featured DESC, id DESC LIMIT 4');
    render('public/home', [
        'title' => site_name() . ' · ' . setting('site_tagline'),
        'description' => excerpt((string)setting('hero_text'), 155),
        'canonical' => '/', 'deal' => $deal, 'deals' => $deals, 'campaigns' => $campaigns, 'favs' => favorite_ids(),
        'jsonld' => [webpage_ld(site_name(), (string)setting('site_tagline'), '/')],
    ]);
}

function offer_filters(): array
{
    $where = ['1=1'];
    $params = [];
    $cat = (string)input('k');
    if ($cat !== '') {
        $c = one('SELECT id FROM categories WHERE slug = ?', [$cat]);
        if ($c) {
            $where[] = '(s.category_id = ? OR s.category_id IN (SELECT id FROM categories WHERE parent_id = ?))';
            array_push($params, $c['id'], $c['id']);
        }
    }
    $type = (string)input('t');
    if ($type === 'free') {
        $where[] = "s.price_cents = 0 AND NOT EXISTS (SELECT 1 FROM service_variants v WHERE v.service_id = s.id AND v.price_cents > 0)";
    } elseif ($type === 'paid') {
        $where[] = "(s.price_cents > 0 OR EXISTS (SELECT 1 FROM service_variants v WHERE v.service_id = s.id AND v.price_cents > 0))";
    }
    $area = trim((string)input('perioxi'));
    if ($area !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM service_stores ss JOIN stores st ON st.id = ss.store_id WHERE ss.service_id = s.id AND (st.city LIKE ? OR st.region LIKE ? OR st.postal_code LIKE ?))';
        array_push($params, "%$area%", "%$area%", "$area%");
    }
    $q = trim((string)input('q'));
    if ($q !== '') {
        $where[] = '(s.title LIKE ? OR s.summary LIKE ? OR b.name LIKE ?)';
        array_push($params, "%$q%", "%$q%", "%$q%");
    }
    $sorts = ['new' => 's.id DESC', 'price' => 's.price_cents ASC', 'ending' => 's.max_codes IS NULL, s.max_codes ASC', 'featured' => 's.featured DESC, s.id DESC'];
    $sort = $sorts[(string)input('sort', 'featured')] ?? $sorts['featured'];
    return [implode(' AND ', $where), $params, $sort];
}

function pub_offers(): void
{
    [$where, $params, $sort] = offer_filters();
    $total = count(service_cards($where, $params, 's.id', 10000));
    $pg = paginate($total, 16, (int)input('page', 1));
    $items = service_cards($where, $params, $sort, $pg['limit'], $pg['offset']);
    $noindex = count(array_diff(array_keys($_GET), ['page'])) > 0;
    render('public/offers', [
        'title' => 'Προσφορές · ' . site_name(), 'description' => 'Όλες οι ενεργές προσφορές καταστημάτων με κωδικό εξαργύρωσης.',
        'canonical' => '/prosfores', 'items' => $items, 'pg' => $pg, 'favs' => favorite_ids(), 'heading' => 'Όλες οι προσφορές', 'noindex' => $noindex,
        'jsonld' => [breadcrumbs_ld(['Αρχική' => '/', 'Προσφορές' => '/prosfores'])],
    ]);
}

function pub_search(): void
{
    $_GET['q'] = (string)input('q');
    [$where, $params, $sort] = offer_filters();
    $items = service_cards($where, $params, $sort, 48);
    render('public/offers', [
        'title' => 'Αναζήτηση · ' . site_name(), 'description' => 'Αποτελέσματα αναζήτησης', 'canonical' => '/anazitisi', 'noindex' => true,
        'items' => $items, 'pg' => paginate(count($items), 48, 1), 'favs' => favorite_ids(), 'heading' => 'Αποτελέσματα για «' . (string)input('q') . '»',
    ]);
}

function pub_service(array $p): void
{
    $s = one('SELECT s.* FROM services s WHERE s.slug = ? AND ' . public_service_sql('s'), [$p['slug']]);
    if (!$s) {
        $gone = one("SELECT id FROM services WHERE slug = ? AND status = 'rejected'", [$p['slug']]);
        abort($gone ? 410 : 404);
    }
    $business = one('SELECT * FROM businesses WHERE id = ?', [$s['business_id']]);
    $variants = all('SELECT * FROM service_variants WHERE service_id = ? ORDER BY sort, id', [$s['id']]);
    $stores = all("SELECT st.* FROM stores st JOIN service_stores ss ON ss.store_id = st.id WHERE ss.service_id = ? AND st.status = 'active' ORDER BY st.city, st.name", [$s['id']]);
    $cat = $s['category_id'] ? one('SELECT * FROM categories WHERE id = ?', [$s['category_id']]) : null;
    $related = service_cards('s.category_id = ? AND s.id <> ?', [(int)$s['category_id'], (int)$s['id']], 's.featured DESC, s.id DESC', 4);
    $reviews = all("SELECT r.*, u.name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.service_id = ? AND r.status = 'approved' ORDER BY r.id DESC LIMIT 10", [$s['id']]);
    $faq = redemption_faq();
    $crumbs = ['Αρχική' => '/', 'Προσφορές' => '/prosfores'];
    if ($cat) {
        $crumbs[$cat['name']] = '/katigoria/' . $cat['slug'];
    }
    $crumbs[$s['title']] = '/prosfora/' . $s['slug'];
    render('public/service', [
        'title' => ($s['meta_title'] ?: $s['title'] . ' · ' . $business['name']) . ' · ' . site_name(),
        'description' => $s['meta_description'] ?: excerpt($s['summary'] ?: (string)$s['description'], 155),
        'canonical' => '/prosfora/' . $s['slug'], 'og_image' => $s['image'], 'og_type' => 'product',
        's' => $s, 'business' => $business, 'variants' => $variants, 'stores' => $stores, 'cat' => $cat, 'related' => $related,
        'reviews' => $reviews, 'faq' => $faq, 'crumbs' => $crumbs, 'favs' => favorite_ids(), 'left' => codes_left($s),
        'jsonld' => [webpage_ld($s['title'], excerpt($s['summary'] ?: (string)$s['description'], 155), '/prosfora/' . $s['slug'], 'ItemPage'), breadcrumbs_ld($crumbs), service_ld($s, $variants, $business), faq_ld($faq)],
    ]);
}

function pub_campaigns(): void
{
    $items = all('SELECT * FROM campaigns c WHERE ' . active_campaign_sql() . ' ORDER BY featured DESC, ends_at IS NULL, ends_at ASC');
    render('public/campaigns', [
        'title' => 'Καμπάνιες · ' . site_name(), 'description' => 'Θεματικές, χρονικές και τοπικές καμπάνιες με προσφορές καταστημάτων.',
        'canonical' => '/kampanies', 'items' => $items,
        'jsonld' => [breadcrumbs_ld(['Αρχική' => '/', 'Καμπάνιες' => '/kampanies'])],
    ]);
}

function pub_campaign(array $p): void
{
    $c = one('SELECT * FROM campaigns c WHERE slug = ? AND ' . active_campaign_sql(), [$p['slug']]);
    if (!$c) {
        abort(one("SELECT id FROM campaigns WHERE slug = ? AND (status = 'ended' OR (ends_at IS NOT NULL AND ends_at < ?))", [$p['slug'], now()]) ? 410 : 404);
    }
    $items = service_cards('s.id IN (SELECT service_id FROM campaign_services WHERE campaign_id = ?)', [$c['id']], 's.featured DESC, s.id DESC', 60);
    $crumbs = ['Αρχική' => '/', 'Καμπάνιες' => '/kampanies', $c['title'] => '/kampania/' . $c['slug']];
    render('public/campaign', [
        'title' => ($c['meta_title'] ?: $c['title']) . ' · ' . site_name(),
        'description' => $c['meta_description'] ?: excerpt($c['summary'] ?: (string)$c['description'], 155),
        'canonical' => '/kampania/' . $c['slug'], 'og_image' => $c['image'], 'c' => $c, 'items' => $items, 'favs' => favorite_ids(), 'crumbs' => $crumbs,
        'jsonld' => [webpage_ld($c['title'], excerpt($c['summary'] ?: (string)$c['description'], 155), '/kampania/' . $c['slug'], 'CollectionPage'), breadcrumbs_ld($crumbs)],
    ]);
}

function pub_categories(): void
{
    $cats = all("SELECT c.*, (SELECT COUNT(*) FROM services s WHERE (s.category_id = c.id OR s.category_id IN (SELECT id FROM categories WHERE parent_id = c.id)) AND " . public_service_sql('s') . ") AS n
                 FROM categories c WHERE c.status = 'active' AND c.parent_id IS NULL ORDER BY c.sort, c.name");
    render('public/categories', [
        'title' => 'Κατηγορίες · ' . site_name(), 'description' => 'Όλες οι κατηγορίες υπηρεσιών με προσφορές.', 'canonical' => '/katigories', 'cats' => $cats,
        'jsonld' => [breadcrumbs_ld(['Αρχική' => '/', 'Κατηγορίες' => '/katigories'])],
    ]);
}

function pub_category(array $p): void
{
    $c = one("SELECT * FROM categories WHERE slug = ? AND status = 'active'", [$p['slug']]);
    if (!$c) {
        abort(404);
    }
    $_GET['k'] = $c['slug'];
    [$where, $params, $sort] = offer_filters();
    $total = count(service_cards($where, $params, 's.id', 10000));
    $pg = paginate($total, 16, (int)input('page', 1));
    $items = service_cards($where, $params, $sort, $pg['limit'], $pg['offset']);
    $subs = all("SELECT * FROM categories WHERE parent_id = ? AND status = 'active' ORDER BY sort, name", [$c['id']]);
    $crumbs = ['Αρχική' => '/', 'Κατηγορίες' => '/katigories', $c['name'] => '/katigoria/' . $c['slug']];
    render('public/offers', [
        'title' => ($c['meta_title'] ?: $c['name'] . ': προσφορές') . ' · ' . site_name(),
        'description' => $c['meta_description'] ?: ('Προσφορές στην κατηγορία ' . $c['name'] . ' με κωδικό εξαργύρωσης σε καταστήματα.'),
        'canonical' => '/katigoria/' . $c['slug'], 'items' => $items, 'pg' => $pg, 'favs' => favorite_ids(), 'heading' => $c['name'],
        'intro' => $c['description'], 'subs' => $subs, 'crumbs' => $crumbs, 'hide_cat_filter' => true,
        'noindex' => count(array_diff(array_keys($_GET), ['page', 'k'])) > 0,
        'jsonld' => [webpage_ld($c['name'], 'Προσφορές: ' . $c['name'], '/katigoria/' . $c['slug'], 'CollectionPage'), breadcrumbs_ld($crumbs)],
    ]);
}

function pub_business(array $p): void
{
    $b = one("SELECT * FROM businesses WHERE slug = ? AND status = 'active'", [$p['slug']]);
    if (!$b) {
        abort(404);
    }
    $stores = all("SELECT * FROM stores WHERE business_id = ? AND status = 'active' ORDER BY city, name", [$b['id']]);
    $items = service_cards('s.business_id = ?', [$b['id']], 's.featured DESC, s.id DESC', 60);
    $crumbs = ['Αρχική' => '/', $b['name'] => '/katastima/' . $b['slug']];
    $ld = ['@type' => 'LocalBusiness', '@id' => abs_url('/katastima/' . $b['slug']) . '#business', 'name' => $b['name'], 'url' => abs_url('/katastima/' . $b['slug'])];
    if ($b['phone']) {
        $ld['telephone'] = $b['phone'];
    }
    if ($b['logo']) {
        $ld['image'] = abs_url($b['logo']);
    }
    if ($stores && $stores[0]['address']) {
        $ld['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $stores[0]['address'], 'addressLocality' => $stores[0]['city'], 'postalCode' => $stores[0]['postal_code'], 'addressCountry' => 'GR']);
    }
    render('public/business', [
        'title' => $b['name'] . ': προσφορές · ' . site_name(), 'description' => excerpt($b['description'] ?: 'Προσφορές από ' . $b['name'], 155),
        'canonical' => '/katastima/' . $b['slug'], 'og_image' => $b['cover'] ?: $b['logo'], 'b' => $b, 'stores' => $stores, 'items' => $items, 'favs' => favorite_ids(), 'crumbs' => $crumbs,
        'jsonld' => [breadcrumbs_ld($crumbs), $ld],
    ]);
}

function pub_how(): void
{
    $steps = [
        ['Καμπάνια', 'Διάλεξε θέμα, περιοχή ή μια προσφορά με χρονικό όριο.'],
        ['Κατηγορία', 'Ομορφιά, γεύση, γυμναστική, αυτοκίνητο και άλλες.'],
        ['Κατάστημα', 'Δες καταστήματα κοντά σου και τις υπηρεσίες τους.'],
        ['Υπηρεσία', 'Διάλεξε πακέτο ή διάρκεια και πρόσθεσέ τη στο καλάθι.'],
        ['Κωδικός', 'Ο μοναδικός κωδικός με QR εμφανίζεται στον λογαριασμό σου. Τον δείχνεις στο ταμείο.'],
    ];
    $howto = [
        '@type' => 'HowTo', 'name' => 'Πώς παίρνω και εξαργυρώνω κωδικό προσφοράς',
        'step' => array_map(fn($s, $i) => ['@type' => 'HowToStep', 'position' => $i + 1, 'name' => $s[0], 'text' => $s[1]], $steps, array_keys($steps)),
    ];
    render('public/how', [
        'title' => 'Πώς λειτουργεί · ' . site_name(), 'description' => 'Πώς παίρνεις κωδικό προσφοράς και πώς τον εξαργυρώνεις στο κατάστημα, σε 5 βήματα.',
        'canonical' => '/pos-leitourgei', 'steps' => $steps,
        'jsonld' => [webpage_ld('Πώς λειτουργεί', 'Πώς παίρνεις και εξαργυρώνεις κωδικό προσφοράς', '/pos-leitourgei'), breadcrumbs_ld(['Αρχική' => '/', 'Πώς λειτουργεί' => '/pos-leitourgei']), $howto],
    ]);
}

function pub_faq(): void
{
    $faq = array_merge(redemption_faq(), [
        ['Τι γίνεται αν το κατάστημα δεν δέχεται τον κωδικό;', 'Επικοινώνησε μαζί μας από τη σελίδα επικοινωνίας με τον αριθμό παραγγελίας και θα το εξετάσουμε.'],
        ['Πώς γίνεται μια επιχείρηση συνεργάτης;', 'Από τη σελίδα «Για επιχειρήσεις» συμπληρώνεις τη φόρμα. Μετά την έγκριση αποκτάς δικό σου περιβάλλον για προσφορές και εξαργυρώσεις.'],
    ]);
    render('public/faq', [
        'title' => 'Συχνές ερωτήσεις · ' . site_name(), 'description' => 'Απαντήσεις για κωδικούς, εξαργύρωση, λογαριασμό και συνεργασίες.',
        'canonical' => '/syxnes-erotiseis', 'faq' => $faq,
        'jsonld' => [webpage_ld('Συχνές ερωτήσεις', 'Απαντήσεις για κωδικούς και εξαργύρωση', '/syxnes-erotiseis', 'FAQPage') + ['mainEntity' => faq_ld($faq)['mainEntity']], breadcrumbs_ld(['Αρχική' => '/', 'Συχνές ερωτήσεις' => '/syxnes-erotiseis'])],
    ]);
}

function pub_contact(): void
{
    if (is_post()) {
        if (looks_like_bot()) {
            flash('ok', 'Το μήνυμά σου στάλθηκε.');
            redirect(url('/epikoinonia'));
        }
        if (rate_limited('contact:' . client_ip(), 5, 3600)) {
            flash('err', 'Έστειλες πολλά μηνύματα. Δοκίμασε ξανά σε μία ώρα.');
            redirect(url('/epikoinonia'));
        }
        $name = mb_substr((string)input('name'), 0, 190);
        $email = mb_strtolower((string)input('email'));
        $body = mb_substr((string)input('message'), 0, 5000);
        $errors = [];
        if ($name === '') {
            $errors[] = 'Γράψε το όνομά σου.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Γράψε ένα έγκυρο email.';
        }
        if (mb_strlen($body) < 10) {
            $errors[] = 'Το μήνυμα χρειάζεται τουλάχιστον 10 χαρακτήρες.';
        }
        if (!input('consent')) {
            $errors[] = 'Χρειάζεται η συγκατάθεσή σου για την επεξεργασία των στοιχείων.';
        }
        if ($errors) {
            keep_old($_POST);
            flash('err', implode(' ', $errors));
            redirect(url('/epikoinonia'));
        }
        insert('messages', ['kind' => 'contact', 'name' => $name, 'email' => $email, 'phone' => mb_substr((string)input('phone'), 0, 40), 'body' => $body, 'ip' => client_ip(), 'created_at' => now()]);
        if ($to = setting('contact_email')) {
            send_mail($to, 'Νέο μήνυμα επικοινωνίας · ' . site_name(), "Από: $name <$email>\n\n$body");
        }
        flash('ok', 'Το μήνυμά σου στάλθηκε. Θα σου απαντήσουμε σύντομα.');
        redirect(url('/epikoinonia'));
    }
    render('public/contact', ['title' => 'Επικοινωνία · ' . site_name(), 'description' => 'Επικοινώνησε μαζί μας για ερωτήσεις σχετικά με κωδικούς, παραγγελίες ή συνεργασίες.', 'canonical' => '/epikoinonia',
        'jsonld' => [webpage_ld('Επικοινωνία', 'Επικοινωνία', '/epikoinonia', 'ContactPage'), breadcrumbs_ld(['Αρχική' => '/', 'Επικοινωνία' => '/epikoinonia'])]]);
}

/** Business application: creates a pending business + merchant account. Agent/affiliate from ?ref= is kept. */
function pub_partners(): void
{
    if (is_post()) {
        if (setting('business_signup') !== '1') {
            abort(403);
        }
        if (looks_like_bot()) {
            flash('ok', 'Η αίτηση στάλθηκε.');
            redirect(url('/synergates'));
        }
        if (rate_limited('partner:' . client_ip(), 5, 3600)) {
            flash('err', 'Πολλές αιτήσεις από τη σύνδεσή σου. Δοκίμασε ξανά αργότερα.');
            redirect(url('/synergates'));
        }
        $f = [
            'business' => trim((string)input('business')), 'vat' => preg_replace('/\D/', '', (string)input('vat')), 'name' => trim((string)input('name')),
            'email' => mb_strtolower(trim((string)input('email'))), 'phone' => trim((string)input('phone')), 'city' => trim((string)input('city')),
            'password' => (string)input('password'),
        ];
        $errors = [];
        if ($f['business'] === '') {
            $errors[] = 'Γράψε την επωνυμία της επιχείρησης.';
        }
        if ($f['name'] === '') {
            $errors[] = 'Γράψε το όνομα του υπεύθυνου.';
        }
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Γράψε ένα έγκυρο email.';
        } elseif (one('SELECT id FROM users WHERE email = ?', [$f['email']])) {
            $errors[] = 'Υπάρχει ήδη λογαριασμός με αυτό το email. Συνδέσου ή χρησιμοποίησε άλλο email.';
        }
        if ($e = validate_password($f['password'])) {
            $errors[] = $e;
        }
        if (!input('consent')) {
            $errors[] = 'Χρειάζεται η αποδοχή των όρων συνεργασίας.';
        }
        if ($errors) {
            keep_old($_POST);
            flash('err', implode(' ', $errors));
            redirect(url('/synergates') . '#aitisi');
        }
        $ref = (string)($_COOKIE['ref'] ?? '');
        $agent = $ref ? one("SELECT * FROM agents WHERE affiliate_code = ? AND status = 'active'", [$ref]) : null;
        $uid = tx(function () use ($f, $ref, $agent) {
            $uid = create_user($f['email'], $f['name'], $f['password'], 'merchant', ['phone' => $f['phone'], 'referred_by' => $ref ?: null]);
            $bid = insert('businesses', [
                'name' => $f['business'], 'slug' => unique_slug('businesses', $f['business']), 'vat_number' => $f['vat'], 'phone' => $f['phone'], 'email' => $f['email'],
                'agent_id' => $agent['id'] ?? null, 'referred_by' => $ref ?: null, 'status' => 'pending', 'created_at' => now(),
            ]);
            insert('business_users', ['business_id' => $bid, 'user_id' => $uid, 'role' => 'owner', 'created_at' => now()]);
            if ($f['city'] !== '') {
                insert('stores', ['business_id' => $bid, 'name' => $f['business'], 'city' => $f['city'], 'status' => 'active', 'created_at' => now()]);
            }
            return $uid;
        });
        login_user(one('SELECT * FROM users WHERE id = ?', [$uid]));
        if ($to = setting('contact_email')) {
            send_mail($to, 'Νέα αίτηση επιχείρησης · ' . site_name(), "Επιχείρηση: {$f['business']}\nΥπεύθυνος: {$f['name']} <{$f['email']}>\nΤηλ: {$f['phone']}\nΠόλη: {$f['city']}");
        }
        flash('ok', 'Η αίτηση στάλθηκε. Μέχρι την έγκριση μπορείς να ετοιμάσεις τα καταστήματα και τις προσφορές σου.');
        redirect(url('/business'));
    }
    render('public/partners', ['title' => 'Για επιχειρήσεις · ' . site_name(), 'description' => 'Φέρε νέους πελάτες στο κατάστημά σου με προσφορές και κωδικούς εξαργύρωσης. Δωρεάν αίτηση συνεργασίας.',
        'canonical' => '/synergates', 'jsonld' => [webpage_ld('Για επιχειρήσεις', 'Συνεργασία επιχειρήσεων', '/synergates'), breadcrumbs_ld(['Αρχική' => '/', 'Για επιχειρήσεις' => '/synergates'])]]);
}

function pub_legal(): void
{
    $path = request_path();
    $titles = ['/oroi' => 'Όροι χρήσης', '/aporrito' => 'Πολιτική απορρήτου', '/cookies' => 'Πολιτική cookies'];
    render('public/legal', ['title' => $titles[$path] . ' · ' . site_name(), 'description' => $titles[$path] . ' της πλατφόρμας ' . site_name(),
        'canonical' => $path, 'heading' => $titles[$path], 'page' => ltrim($path, '/')]);
}

// ---------- system ----------
function sys_sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    echo sitemap_xml();
}

function sys_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo robots_txt();
}

function sys_llms(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo llms_txt();
}

function sys_manifest(): void
{
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode([
        'name' => site_name(), 'short_name' => mb_substr(site_name(), 0, 12), 'description' => setting('site_tagline'),
        'id' => '/', 'start_url' => '/?source=pwa', 'scope' => '/', 'display' => 'standalone', 'orientation' => 'portrait',
        'background_color' => '#140A1E', 'theme_color' => '#140A1E', 'lang' => 'el', 'dir' => 'ltr', 'categories' => ['shopping', 'lifestyle'],
        'icons' => [
            ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
        'shortcuts' => [
            ['name' => 'Οι κωδικοί μου', 'url' => '/logariasmos/kodikoi'],
            ['name' => 'Προσφορές', 'url' => '/prosfores'],
            ['name' => 'Εξαργύρωση (επιχειρήσεις)', 'url' => '/business/redeem'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** App icon drawn with GD: three-colour ring on the brand night colour. Replace by uploading a logo later. */
function sys_icon(array $p): void
{
    $size = (int)$p['size'];
    if (!in_array($size, [180, 192, 512], true)) {
        abort(404);
    }
    $cache = ROOT . '/data/icon-' . $size . '.png';
    if (!is_file($cache)) {
        $im = imagecreatetruecolor($size, $size);
        imagefill($im, 0, 0, imagecolorallocate($im, 0x14, 0x0A, 0x1E));
        $c = $size / 2;
        $r = (int)($size * 0.62);
        $cols = [imagecolorallocate($im, 0xFF, 0x4F, 0x3A), imagecolorallocate($im, 0xF2, 0xC1, 0x4E), imagecolorallocate($im, 0x2B, 0xD4, 0xA4)];
        imagefilledarc($im, (int)$c, (int)$c, $r, $r, 270, 30, $cols[0], IMG_ARC_PIE);
        imagefilledarc($im, (int)$c, (int)$c, $r, $r, 30, 150, $cols[1], IMG_ARC_PIE);
        imagefilledarc($im, (int)$c, (int)$c, $r, $r, 150, 270, $cols[2], IMG_ARC_PIE);
        $hole = (int)($r * 0.46);
        imagefilledellipse($im, (int)$c, (int)$c, $hole, $hole, imagecolorallocate($im, 0x14, 0x0A, 0x1E));
        imagepng($im, $cache);
        imagedestroy($im);
    }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    readfile($cache);
}

function sys_offline(): void
{
    render('public/offline', ['title' => 'Χωρίς σύνδεση · ' . site_name(), 'noindex' => true]);
}
