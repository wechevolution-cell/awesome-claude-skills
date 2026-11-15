<?php
declare(strict_types=1);

namespace App;

/* =========================================================
 * Meta + JSON-LD
 * ========================================================= */

function page_meta(array $page): array
{
    $site = (string) setting('site_name', 'ANV');
    $title = $page['meta_title'] !== '' ? $page['meta_title'] : ($page['type'] === 'home' ? $site : $page['title'] . ' · ' . $site);
    $desc = $page['meta_desc'] !== '' ? $page['meta_desc'] : excerpt($page['intro'] . ' ' . $page['body_html'], 158);
    $img = $page['hero_image'] !== '' ? $page['hero_image'] : (string) setting('og_image', '/assets/img/og-default.png');
    return [
        'title' => $title,
        'description' => $desc,
        'canonical' => abs_url($page['path']),
        'image' => abs_url($img),
        'noindex' => (bool) $page['noindex'] || $page['status'] !== 'published',
    ];
}

function org_id(): string
{
    return abs_url('/') . '#organization';
}

function same_as(): array
{
    $out = [];
    foreach (['facebook', 'instagram', 'linkedin', 'youtube', 'tiktok', 'x'] as $k) {
        $v = (string) setting('social_' . $k, '');
        if (preg_match('#^https://#', $v)) {
            $out[] = $v;
        }
    }
    $g = (string) setting('google_business_url', '');
    if (preg_match('#^https://#', $g)) {
        $out[] = $g;
    }
    return $out;
}

function business_node(): array
{
    $n = [
        '@type' => ['LocalBusiness', 'ProfessionalService'],
        '@id' => org_id(),
        'name' => (string) setting('site_name', 'ANV'),
        'url' => abs_url('/'),
        'logo' => abs_url((string) setting('logo', '/assets/img/logo.png')),
        'image' => abs_url((string) setting('og_image', '/assets/img/og-default.png')),
        'description' => (string) setting('tagline', ''),
        'knowsAbout' => array_map(fn($s) => $s['title'], services()),
    ];
    if ($v = setting('legal_name')) {
        $n['legalName'] = $v;
    }
    if ($v = setting('vat_id')) {
        $n['vatID'] = $v;
    }
    if ($v = setting('phone')) {
        $n['telephone'] = $v;
    }
    if ($v = setting('email')) {
        $n['email'] = $v;
    }
    if (setting('address_street') && setting('address_city')) {
        $n['address'] = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => setting('address_street'),
            'addressLocality' => setting('address_city'),
            'postalCode' => setting('address_postcode'),
            'addressRegion' => setting('address_region'),
            'addressCountry' => setting('address_country', 'GR'),
        ]);
    }
    if (is_numeric(setting('geo_lat')) && is_numeric(setting('geo_lng'))) {
        $n['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) setting('geo_lat'), 'longitude' => (float) setting('geo_lng')];
        $n['hasMap'] = 'https://www.google.com/maps?q=' . setting('geo_lat') . ',' . setting('geo_lng');
    }
    if (setting('hours_confirmed') === '1') {
        $spec = [];
        foreach (hours() as $d => $slots) {
            foreach ($slots as $s) {
                $spec[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => DAY_SCHEMA[$d], 'opens' => $s[0], 'closes' => $s[1]];
            }
        }
        if ($spec) {
            $n['openingHoursSpecification'] = $spec;
        }
    }
    if ($s = same_as()) {
        $n['sameAs'] = $s;
    }
    $catalog = [];
    foreach (services() as $svc) {
        $items = array_map(fn($c) => ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $c['title'], 'url' => abs_url($c['path'])]], published_children((int) $svc['id']));
        $catalog[] = ['@type' => 'OfferCatalog', 'name' => $svc['title'], 'url' => abs_url($svc['path']), 'itemListElement' => $items];
    }
    if ($catalog) {
        $n['hasOfferCatalog'] = ['@type' => 'OfferCatalog', 'name' => 'Υπηρεσίες', 'itemListElement' => $catalog];
    }
    return $n;
}

function breadcrumbs(array $page): array
{
    $trail = [];
    $p = $page;
    $guard = 0;
    while ($p && $guard++ < 10) {
        array_unshift($trail, ['name' => $p['type'] === 'home' ? 'Αρχική' : ($p['nav_label'] ?: $p['title']), 'path' => $p['path']]);
        $p = $p['parent_id'] ? page_by_id((int) $p['parent_id']) : null;
    }
    if ($trail && $trail[0]['path'] !== '/') {
        array_unshift($trail, ['name' => 'Αρχική', 'path' => '/']);
    }
    return $trail;
}

function faq_items(array $page): array
{
    $f = json_decode($page['faq_json'] ?: '[]', true);
    return array_values(array_filter(is_array($f) ? $f : [], fn($x) => is_array($x) && trim((string) ($x['q'] ?? '')) !== '' && trim((string) ($x['a'] ?? '')) !== ''));
}

function howto_steps(array $page): array
{
    $f = json_decode($page['howto_json'] ?: '[]', true);
    return array_values(array_filter(is_array($f) ? $f : [], fn($x) => is_array($x) && trim((string) ($x['name'] ?? '')) !== ''));
}

function sources_list(array $page): array
{
    $out = [];
    foreach (lines($page['sources']) as $l) {
        $parts = array_map('trim', explode('|', $l, 2));
        $url = $parts[1] ?? $parts[0];
        if (preg_match('#^https?://#', $url)) {
            $out[] = ['label' => count($parts) === 2 ? $parts[0] : $url, 'url' => $url];
        }
    }
    return $out;
}

/** Organised @graph for one page. $extra lets list pages (FAQ hub) add nodes. */
function jsonld(array $page, array $extraFaq = []): string
{
    $url = abs_url($page['path']);
    $meta = page_meta($page);
    $graph = [];
    $graph[] = business_node();
    $graph[] = [
        '@type' => 'WebSite',
        '@id' => abs_url('/') . '#website',
        'url' => abs_url('/'),
        'name' => (string) setting('site_name', 'ANV'),
        'inLanguage' => 'el-GR',
        'publisher' => ['@id' => org_id()],
    ];
    $types = ['WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'CollectionPage'];
    $pageType = in_array($page['schema_type'], $types, true) ? $page['schema_type'] : 'WebPage';
    $webpage = [
        '@type' => $pageType,
        '@id' => $url . '#webpage',
        'url' => $url,
        'name' => $meta['title'],
        'description' => $meta['description'],
        'inLanguage' => 'el-GR',
        'isPartOf' => ['@id' => abs_url('/') . '#website'],
        'about' => ['@id' => org_id()],
        'dateModified' => date('c', strtotime($page['updated_at'])),
    ];
    $crumbs = breadcrumbs($page);
    if (count($crumbs) > 1) {
        $graph[] = [
            '@type' => 'BreadcrumbList',
            '@id' => $url . '#breadcrumb',
            'itemListElement' => array_map(fn($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => abs_url($c['path'])], $crumbs, array_keys($crumbs)),
        ];
        $webpage['breadcrumb'] = ['@id' => $url . '#breadcrumb'];
    }
    $faq = array_merge(faq_items($page), $extraFaq);
    if ($faq) {
        $faqNode = [
            '@type' => 'FAQPage',
            '@id' => $url . '#faq',
            'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => plain($f['q']), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => plain($f['a'])]], $faq),
        ];
        if ($pageType === 'FAQPage') {
            $webpage = array_merge($webpage, ['mainEntity' => $faqNode['mainEntity']]);
        } else {
            $graph[] = $faqNode;
        }
    }
    $graph[] = $webpage;

    if ($page['schema_type'] === 'Service' || in_array($page['type'], ['service', 'subservice'], true)) {
        $svc = [
            '@type' => 'Service',
            '@id' => $url . '#service',
            'name' => $page['title'],
            'description' => $meta['description'],
            'url' => $url,
            'provider' => ['@id' => org_id()],
            'areaServed' => ['@type' => 'Country', 'name' => 'Ελλάδα'],
            'inLanguage' => 'el-GR',
        ];
        if ($page['type'] === 'service') {
            $kids = published_children((int) $page['id']);
            if ($kids) {
                $svc['hasOfferCatalog'] = ['@type' => 'OfferCatalog', 'name' => $page['title'], 'itemListElement' => array_map(fn($c) => ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $c['title'], 'url' => abs_url($c['path'])]], $kids)];
            }
        } elseif ($page['parent_id'] && ($parent = page_by_id((int) $page['parent_id']))) {
            $svc['serviceType'] = $parent['title'];
        }
        $graph[] = $svc;
    }
    $steps = howto_steps($page);
    if ($steps) {
        $graph[] = [
            '@type' => 'HowTo',
            '@id' => $url . '#howto',
            'name' => $page['howto_title'] ?: $page['title'],
            'step' => array_map(fn($s, $i) => ['@type' => 'HowToStep', 'position' => $i + 1, 'name' => plain($s['name']), 'text' => plain((string) ($s['text'] ?? $s['name']))], $steps, array_keys($steps)),
        ];
    }
    if ($page['type'] === 'home') {
        $graph[] = [
            '@type' => 'ItemList',
            '@id' => $url . '#services',
            'name' => 'Υπηρεσίες',
            'itemListElement' => array_map(fn($s, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $s['title'], 'url' => abs_url($s['path'])], services(), array_keys(services())),
        ];
    }
    $custom = trim($page['custom_jsonld']);
    if ($custom !== '') {
        $c = json_decode($custom, true);
        if (is_array($c)) {
            foreach (isset($c['@graph']) ? $c['@graph'] : (array_is_list($c) ? $c : [$c]) as $node) {
                if (is_array($node)) {
                    unset($node['@context']);
                    $graph[] = $node;
                }
            }
        }
    }
    $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    return '<script type="application/ld+json">' . $json . '</script>';
}

/* =========================================================
 * SEO / AEO analyzer
 * ========================================================= */

/**
 * @param array $p page fields (may be unsaved form data)
 * @return array{seo:int, aeo:int, checks:array, cannibal:array}
 */
function analyze(array $p): array
{
    $kw = norm((string) ($p['focus_kw'] ?? ''));
    $title = (string) ($p['meta_title'] ?? '') !== '' ? (string) $p['meta_title'] : (string) ($p['title'] ?? '');
    $desc = (string) ($p['meta_desc'] ?? '');
    $h1 = (string) ($p['h1'] ?? '') ?: (string) ($p['title'] ?? '');
    $intro = (string) ($p['intro'] ?? '');
    $bodyMd = (string) ($p['body_md'] ?? '');
    $html = markdown($bodyMd);
    $text = plain($intro . ' ' . $html);
    $ntext = norm($text);
    $words = max(1, count(preg_split('/\s+/u', trim($text)) ?: []));
    $faq = json_decode((string) ($p['faq_json'] ?? '[]'), true) ?: [];
    $faq = array_filter($faq, fn($f) => is_array($f) && trim((string) ($f['q'] ?? '')) !== '' && trim((string) ($f['a'] ?? '')) !== '');
    $howto = json_decode((string) ($p['howto_json'] ?? '[]'), true) ?: [];
    preg_match_all('#<h2\b[^>]*>(.*?)</h2>#su', $html, $h2);
    preg_match_all('#<h3\b[^>]*>(.*?)</h3>#su', $html, $h3);
    preg_match_all('#<a\s[^>]*href="([^"]+)"#u', $html, $links);
    preg_match_all('#<img\s[^>]*>#u', $html, $imgs);
    $internal = array_filter($links[1], fn($h) => str_starts_with($h, '/'));
    $external = array_filter($links[1], fn($h) => preg_match('#^https?://#', $h));
    $imgsNoAlt = array_filter($imgs[0], fn($t) => !preg_match('/alt="[^"]+"/u', $t));
    $slug = (string) ($p['slug'] ?? '');
    $type = (string) ($p['type'] ?? 'page');
    $isLegal = $type === 'legal';

    $c = [];
    $add = function (string $group, string $label, bool $ok, int $weight, string $hint = '', bool $warnOnly = false) use (&$c) {
        $c[] = ['group' => $group, 'label' => $label, 'ok' => $ok, 'weight' => $weight, 'hint' => $hint, 'level' => $ok ? 'ok' : ($warnOnly ? 'warn' : 'bad')];
    };

    // ----- SEO -----
    $add('seo', 'Έχει οριστεί λέξη-κλειδί', $kw !== '', 10, 'Συμπληρώστε τη «Λέξη-κλειδί».');
    $add('seo', 'Η λέξη-κλειδί υπάρχει στον τίτλο SEO', $kw !== '' && str_contains(norm($title), $kw), 10, 'Βάλτε τη λέξη-κλειδί στον τίτλο SEO, ιδανικά στην αρχή.');
    $tl = mb_strlen($title);
    $add('seo', "Μήκος τίτλου SEO: $tl χαρακτήρες (ιδανικά 30–60)", $tl >= 30 && $tl <= 60, 8, 'Ο τίτλος πρέπει να είναι 30–60 χαρακτήρες.', $tl > 0);
    $dl = mb_strlen($desc);
    $add('seo', "Meta description: $dl χαρακτήρες (ιδανικά 120–160)", $dl >= 120 && $dl <= 160, 8, 'Γράψτε μια περιγραφή 120–160 χαρακτήρων.', $dl > 0);
    $add('seo', 'Η λέξη-κλειδί υπάρχει στη meta description', $kw !== '' && str_contains(norm($desc), $kw), 6, 'Αναφέρετε τη λέξη-κλειδί στην περιγραφή.');
    $add('seo', 'Το slug είναι σύντομο και με λατινικούς χαρακτήρες', $type === 'home' || ($slug !== '' && preg_match('/^[a-z0-9-]{2,60}$/', $slug) === 1), 5, 'Μόνο πεζά λατινικά, αριθμοί και παύλες, έως 60 χαρακτήρες.');
    $add('seo', 'Η λέξη-κλειδί υπάρχει στον H1', $kw !== '' && str_contains(norm($h1), $kw), 8, 'Βάλτε τη λέξη-κλειδί στον κύριο τίτλο (H1).');
    $add('seo', 'Η λέξη-κλειδί υπάρχει στην εισαγωγή', $kw !== '' && str_contains(norm($intro !== '' ? $intro : mb_substr($text, 0, 300)), $kw), 8, 'Αναφέρετε τη λέξη-κλειδί στην πρώτη παράγραφο.');
    $add('seo', 'Υπάρχουν υπότιτλοι H2', count($h2[0]) > 0, 6, 'Χωρίστε το κείμενο με υπότιτλους (## στο markdown).');
    $occ = $kw !== '' ? substr_count($ntext, $kw) : 0;
    $kwWords = max(1, count(explode(' ', $kw)));
    $density = $kw !== '' ? round($occ * $kwWords / $words * 100, 2) : 0;
    $add('seo', "Πυκνότητα λέξης-κλειδιού: {$density}% (ιδανικά 0,5–3%)", $density >= 0.5 && $density <= 3, 5, $density > 3 ? 'Υπερβολική επανάληψη. Χρησιμοποιήστε συνώνυμα.' : 'Αναφέρετε τη λέξη-κλειδί λίγο πιο συχνά.', $density > 0);
    $minWords = $isLegal ? 150 : ($type === 'subservice' ? 250 : 300);
    $add('seo', "Μήκος περιεχομένου: $words λέξεις (στόχος $minWords+)", $words >= $minWords, 8, 'Προσθέστε περισσότερο χρήσιμο περιεχόμενο.', $words >= $minWords / 2);
    $add('seo', 'Εσωτερικοί σύνδεσμοι: ' . count($internal), count($internal) > 0 || in_array($type, ['service', 'home', 'contact', 'faq'], true), 5, 'Προσθέστε σύνδεσμο προς σχετική σελίδα του site.', true);
    $add('seo', 'Εξωτερικοί σύνδεσμοι ή πηγές: ' . (count($external) + count(lines((string) ($p['sources'] ?? '')))), count($external) + count(lines((string) ($p['sources'] ?? ''))) > 0, 3, 'Παραπέμψτε σε επίσημη πηγή (π.χ. ΑΑΔΕ, ΕΦΚΑ).', true);
    $add('seo', 'Όλες οι εικόνες έχουν alt', count($imgsNoAlt) === 0, 4, 'Συμπληρώστε περιγραφή (alt) σε κάθε εικόνα.');
    $add('seo', 'Η σελίδα είναι indexable', empty($p['noindex']) || $isLegal, 6, 'Η σελίδα έχει noindex.');
    $kw2 = array_filter(array_map('trim', explode(',', (string) ($p['secondary_kws'] ?? ''))));
    $kw2hit = array_filter($kw2, fn($k) => str_contains($ntext . ' ' . norm($title), norm($k)));
    $add('seo', 'Δευτερεύουσες λέξεις στο κείμενο: ' . count($kw2hit) . '/' . count($kw2), !$kw2 || count($kw2hit) === count($kw2), 3, 'Χρησιμοποιήστε τις δευτερεύουσες λέξεις-κλειδιά στο κείμενο.', true);
    $add('seo', 'Έγκυρο custom JSON-LD', trim((string) ($p['custom_jsonld'] ?? '')) === '' || is_array(json_decode((string) $p['custom_jsonld'], true)), 3, 'Το custom JSON-LD δεν είναι έγκυρο JSON.');

    // ----- AEO -----
    $add('aeo', 'Υπάρχει TL;DR (σύντομη απάντηση)', trim((string) ($p['tldr'] ?? '')) !== '', 12, 'Γράψτε 1–2 προτάσεις που απαντούν κατευθείαν.');
    $il = mb_strlen($intro);
    $add('aeo', "Η εισαγωγή απαντά άμεσα ($il χαρακτήρες, ιδανικά 80–300)", $il >= 80 && $il <= 300, 12, 'Η πρώτη παράγραφος να απαντά «τι είναι / τι κάνουμε» σε 1–2 προτάσεις.');
    $add('aeo', 'Υπάρχουν FAQ: ' . count($faq), count($faq) >= 2, 14, 'Προσθέστε τουλάχιστον 2 ερωτήσεις με απαντήσεις.', count($faq) === 1);
    $qHead = array_filter(array_merge($h2[1], $h3[1]), fn($h) => preg_match('/[;?]\s*$/u', plain($h)) || preg_match('/^(τι|πώς|πως|ποιος|ποια|ποιο|πότε|γιατί|πόσο|μπορώ|χρειάζεται)\b/iu', plain($h)));
    $add('aeo', 'Υπότιτλοι σε μορφή ερώτησης: ' . count($qHead), count($qHead) > 0 || count($faq) >= 2, 8, 'Γράψτε κάποιους υπότιτλους ως ερωτήσεις (π.χ. «Τι χρειάζεται;»).', true);
    $add('aeo', 'Υπάρχουν βασικά σημεία (key points)', count(lines((string) ($p['key_points'] ?? ''))) >= 2, 10, 'Προσθέστε 2+ βασικά σημεία (ένα ανά γραμμή).');
    $add('aeo', 'Υπάρχουν λίστες', (bool) preg_match('#<(ul|ol)>#', $html) || count(lines((string) ($p['key_points'] ?? ''))) >= 2, 8, 'Χρησιμοποιήστε λίστες για βήματα και επιλογές.');
    $add('aeo', 'Υπάρχει πίνακας', str_contains($html, '<table'), 4, 'Ένας πίνακας βοηθά όπου υπάρχει σύγκριση.', true);
    $add('aeo', 'Υπάρχουν πηγές', count(lines((string) ($p['sources'] ?? ''))) > 0 || $isLegal, 10, 'Προσθέστε επίσημες πηγές (μία ανά γραμμή: Τίτλος | https://…).');
    $add('aeo', 'Υπάρχει δομημένο schema', in_array((string) ($p['schema_type'] ?? ''), ['Service', 'FAQPage', 'AboutPage', 'ContactPage', 'WebPage', 'CollectionPage'], true), 8, 'Επιλέξτε τύπο schema.');
    $add('aeo', 'Βήματα HowTo', count(array_filter($howto, fn($s) => is_array($s) && trim((string) ($s['name'] ?? '')) !== '')) > 0 || !in_array($type, ['service', 'subservice'], true), 6, 'Για διαδικασίες, προσθέστε βήματα HowTo.', true);
    $add('aeo', 'Συντάκτης / υπεύθυνος περιεχομένου', trim((string) ($p['author'] ?? '')) !== '' || in_array($type, ['home', 'contact', 'legal', 'faq'], true), 8, 'Συμπληρώστε τον συντάκτη (π.χ. το όνομα του λογιστή).', true);

    $score = function (string $g) use ($c): int {
        $tot = 0;
        $got = 0.0;
        foreach ($c as $x) {
            if ($x['group'] !== $g) {
                continue;
            }
            $tot += $x['weight'];
            $got += $x['ok'] ? $x['weight'] : ($x['level'] === 'warn' ? $x['weight'] * 0.5 : 0);
        }
        return $tot ? (int) round($got / $tot * 100) : 0;
    };

    return ['seo' => $score('seo'), 'aeo' => $score('aeo'), 'checks' => $c, 'cannibal' => cannibalization($kw, (int) ($p['id'] ?? 0))];
}

/** Pages whose focus keyword collides with $kw (accent- and case-insensitive). */
function cannibalization(string $nkw, int $excludeId = 0): array
{
    if ($nkw === '') {
        return [];
    }
    $mine = array_filter(explode(' ', $nkw), fn($w) => mb_strlen($w) > 2);
    $out = [];
    foreach (db()->query("SELECT id, title, path, focus_kw FROM pages WHERE focus_kw != ''") as $r) {
        if ((int) $r['id'] === $excludeId) {
            continue;
        }
        $o = norm($r['focus_kw']);
        $theirs = array_filter(explode(' ', $o), fn($w) => mb_strlen($w) > 2);
        $inter = count(array_intersect($mine, $theirs));
        $union = count(array_unique(array_merge($mine, $theirs))) ?: 1;
        $jac = $inter / $union;
        if ($o === $nkw || $jac >= 0.6) {
            $out[] = ['id' => (int) $r['id'], 'title' => $r['title'], 'path' => $r['path'], 'kw' => $r['focus_kw'], 'level' => $o === $nkw ? 'ίδια' : 'πολύ παρόμοια'];
        }
    }
    return $out;
}

function refresh_scores(int $id): void
{
    $p = page_by_id($id);
    if (!$p) {
        return;
    }
    $a = analyze($p);
    db()->prepare('UPDATE pages SET seo_score = ?, aeo_score = ? WHERE id = ?')->execute([$a['seo'], $a['aeo'], $id]);
}
