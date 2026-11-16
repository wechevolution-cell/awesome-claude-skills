<?php
declare(strict_types=1);

function org_ld(): array
{
    $same = array_values(array_filter([
        setting('social_facebook'), setting('social_instagram'), setting('social_tiktok'), setting('social_linkedin'), setting('social_youtube'),
    ]));
    $org = [
        '@type' => 'Organization',
        '@id' => abs_url('/') . '#organization',
        'name' => site_name(),
        'url' => abs_url('/'),
        'logo' => abs_url('/icon-512.png'),
    ];
    if ($same) {
        $org['sameAs'] = $same;
    }
    if (setting('contact_email') || setting('contact_phone')) {
        $org['contactPoint'] = array_filter([
            '@type' => 'ContactPoint', 'contactType' => 'customer support', 'availableLanguage' => 'el',
            'email' => setting('contact_email') ?: null, 'telephone' => setting('contact_phone') ?: null,
        ]);
    }
    if (setting('company_legal_name')) {
        $org['legalName'] = setting('company_legal_name');
    }
    return $org;
}

function website_ld(): array
{
    return [
        '@type' => 'WebSite',
        '@id' => abs_url('/') . '#website',
        'url' => abs_url('/'),
        'name' => site_name(),
        'inLanguage' => 'el-GR',
        'publisher' => ['@id' => abs_url('/') . '#organization'],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => abs_url('/anazitisi') . '?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

function breadcrumbs_ld(array $crumbs): array
{
    $items = [];
    $i = 1;
    foreach ($crumbs as $label => $path) {
        $items[] = ['@type' => 'ListItem', 'position' => $i++, 'name' => $label, 'item' => abs_url($path)];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

function webpage_ld(string $title, string $description, string $path, string $type = 'WebPage'): array
{
    return [
        '@type' => $type, '@id' => abs_url($path) . '#webpage', 'url' => abs_url($path), 'name' => $title,
        'description' => $description, 'inLanguage' => 'el-GR', 'isPartOf' => ['@id' => abs_url('/') . '#website'],
    ];
}

/** Product + Offer for a service. No ratings unless real, approved reviews exist. */
function service_ld(array $s, array $variants, array $business): array
{
    $offers = [];
    $list = $variants ?: [['label' => null, 'price_cents' => $s['price_cents']]];
    foreach ($list as $v) {
        $offers[] = array_filter([
            '@type' => 'Offer',
            'name' => $v['label'] ?? null,
            'price' => number_format(((int)$v['price_cents']) / 100, 2, '.', ''),
            'priceCurrency' => 'EUR',
            'availability' => 'https://schema.org/InStock',
            'url' => abs_url('/prosfora/' . $s['slug']),
            'seller' => ['@type' => 'LocalBusiness', 'name' => $business['name']],
        ], fn($x) => $x !== null);
    }
    $ld = [
        '@type' => 'Product',
        '@id' => abs_url('/prosfora/' . $s['slug']) . '#product',
        'name' => $s['title'],
        'description' => excerpt($s['summary'] ?: $s['description'], 300),
        'sku' => 'SRV-' . $s['id'],
        'brand' => ['@type' => 'Brand', 'name' => $business['name']],
        'offers' => count($offers) === 1 ? $offers[0] : $offers,
    ];
    if ($s['image']) {
        $ld['image'] = abs_url($s['image']);
    }
    $rating = one("SELECT COUNT(*) n, AVG(rating) avg FROM reviews WHERE service_id = ? AND status = 'approved'", [$s['id']]);
    if ($rating && (int)$rating['n'] > 0) {
        $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round((float)$rating['avg'], 1), 'reviewCount' => (int)$rating['n']];
    }
    return $ld;
}

function faq_ld(array $faq): array
{
    return [
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq),
    ];
}

function jsonld(array $nodes): string
{
    $graph = array_merge([org_ld(), website_ld()], $nodes);
    $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    return '<script type="application/ld+json">' . $json . '</script>';
}

/** Common redemption FAQ, used on service pages and the FAQ page (also answers AI/answer engines directly). */
function redemption_faq(): array
{
    return [
        ['Πώς εξαργυρώνω τον κωδικό;', 'Άνοιξε «Οι κωδικοί μου», δείξε το QR ή τον κωδικό στο ταμείο του καταστήματος. Ο υπάλληλος τον σαρώνει ή τον πληκτρολογεί και η εξαργύρωση επιβεβαιώνεται αμέσως.'],
        ['Πόσο ισχύει ο κωδικός;', 'Κάθε κωδικός έχει ημερομηνία λήξης που φαίνεται δίπλα του. Μετά από αυτή δεν μπορεί να εξαργυρωθεί.'],
        ['Μπορώ να χρησιμοποιήσω τον κωδικό δύο φορές;', 'Όχι. Κάθε κωδικός είναι μίας χρήσης και κλειδώνει τη στιγμή της εξαργύρωσης.'],
        ['Χρειάζομαι λογαριασμό;', 'Ναι. Ο λογαριασμός κρατά τους κωδικούς και το ιστορικό σου, ώστε να τους βρίσκεις πάντα από το κινητό.'],
    ];
}

function sitemap_xml(): string
{
    $urls = [['/', now()], ['/prosfores', now()], ['/kampanies', now()], ['/katigories', now()], ['/pos-leitourgei', null], ['/synergates', null], ['/syxnes-erotiseis', null], ['/epikoinonia', null]];
    foreach (all("SELECT slug, COALESCE(updated_at, created_at) AS m FROM services s WHERE " . public_service_sql('s')) as $r) {
        $urls[] = ['/prosfora/' . $r['slug'], $r['m']];
    }
    foreach (all("SELECT slug, created_at AS m FROM campaigns WHERE status = 'active'") as $r) {
        $urls[] = ['/kampania/' . $r['slug'], $r['m']];
    }
    foreach (all("SELECT slug, created_at AS m FROM categories WHERE status = 'active'") as $r) {
        $urls[] = ['/katigoria/' . $r['slug'], $r['m']];
    }
    foreach (all("SELECT slug, created_at AS m FROM businesses WHERE status = 'active'") as $r) {
        $urls[] = ['/katastima/' . $r['slug'], $r['m']];
    }
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    $imgs = [];
    foreach (all("SELECT slug, image, title FROM services s WHERE image IS NOT NULL AND image <> '' AND " . public_service_sql('s')) as $r) {
        $imgs['/prosfora/' . $r['slug']] = [$r['image'], $r['title']];
    }
    foreach ($urls as [$p, $m]) {
        $x .= '  <url><loc>' . e(abs_url($p)) . '</loc>' . ($m ? '<lastmod>' . date('Y-m-d', strtotime($m)) . '</lastmod>' : '');
        if (isset($imgs[$p])) {
            $x .= '<image:image><image:loc>' . e(abs_url($imgs[$p][0])) . '</image:loc><image:title>' . e($imgs[$p][1]) . '</image:title></image:image>';
        }
        $x .= "</url>\n";
    }
    return $x . '</urlset>';
}

function robots_txt(): string
{
    if (setting('noindex_site') === '1') {
        return "# Το site είναι σε προετοιμασία. Άλλαξέ το από Admin → Ρυθμίσεις → SEO πριν το live.\nUser-agent: *\nDisallow: /\n";
    }
    $t = "User-agent: *\nDisallow: /admin/\nDisallow: /business/\nDisallow: /agent/\nDisallow: /logariasmos/\nDisallow: /kalathi\nDisallow: /tameio\nDisallow: /pay/\nDisallow: /api/\n\n";
    if (setting('ai_bots_allowed') === '1') {
        foreach (['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'PerplexityBot', 'Google-Extended'] as $bot) {
            $t .= "User-agent: $bot\nAllow: /\nDisallow: /admin/\nDisallow: /business/\nDisallow: /agent/\nDisallow: /logariasmos/\nDisallow: /api/\n\n";
        }
    }
    return $t . 'Sitemap: ' . abs_url('/sitemap.xml') . "\n";
}

function llms_txt(): string
{
    $t = '# ' . site_name() . "\n\n> " . setting('site_tagline') . "\n\n";
    $t .= "Πλατφόρμα όπου καταναλωτές βρίσκουν υπηρεσίες καταστημάτων σε προσφορά, παίρνουν μοναδικό κωδικό εξαργύρωσης (με QR) και τον εξαργυρώνουν στο κατάστημα. Κάθε κωδικός είναι μίας χρήσης και έχει ημερομηνία λήξης.\n\n";
    $t .= "## Βασικές σελίδες\n\n- [Προσφορές](" . abs_url('/prosfores') . "): όλες οι ενεργές προσφορές\n- [Καμπάνιες](" . abs_url('/kampanies') . ")\n- [Κατηγορίες](" . abs_url('/katigories') . ")\n- [Πώς λειτουργεί](" . abs_url('/pos-leitourgei') . ")\n- [Συχνές ερωτήσεις](" . abs_url('/syxnes-erotiseis') . ")\n- [Για επιχειρήσεις](" . abs_url('/synergates') . ")\n\n";
    $cats = all("SELECT name, slug FROM categories WHERE status = 'active' AND parent_id IS NULL ORDER BY sort, name");
    if ($cats) {
        $t .= "## Κατηγορίες\n\n";
        foreach ($cats as $c) {
            $t .= '- [' . $c['name'] . '](' . abs_url('/katigoria/' . $c['slug']) . ")\n";
        }
        $t .= "\n";
    }
    $t .= "## Συχνές ερωτήσεις\n\n";
    foreach (redemption_faq() as [$q, $a]) {
        $t .= "### $q\n$a\n\n";
    }
    return $t;
}
