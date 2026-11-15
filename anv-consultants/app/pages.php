<?php
declare(strict_types=1);

namespace App;

function request_path(): string
{
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $p = is_string($p) ? rawurldecode($p) : '/';
    if (!mb_check_encoding($p, 'UTF-8')) {
        $p = '/';
    }
    $p = '/' . ltrim(preg_replace('#/{2,}#', '/', $p) ?? '/', '/');
    return $p;
}

function route(): void
{
    $path = request_path();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Canonical scheme + host (only once the owner sets the canonical URL in admin).
    $canon = (string) setting('canonical_url', '');
    if ($canon !== '' && in_array($method, ['GET', 'HEAD'], true)) {
        $cHost = strtolower((string) parse_url($canon, PHP_URL_HOST));
        $cScheme = (string) parse_url($canon, PHP_URL_SCHEME);
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($cHost !== '' && ($host !== $cHost || ($cScheme === 'https' && !is_https()))) {
            redirect(rtrim($canon, '/') . ($_SERVER['REQUEST_URI'] ?? '/'), 301);
        }
    }

    if ($path === '/index.php') {
        redirect('/', 301);
    }

    if ($path === '/admin' || str_starts_with($path, '/admin/')) {
        admin_route($path, $method);
        return;
    }

    switch ($path) {
        case '/sitemap.xml':
            sitemap_xml();
            return;
        case '/robots.txt':
            robots_txt();
            return;
        case '/llms.txt':
            llms_txt();
            return;
        case '/feed.xml':
            rss_feed();
            return;
    }

    if (setting('maintenance') === '1' && !is_admin()) {
        render_error(503);
        return;
    }

    publish_due();

    // Trailing slash for page-like URLs.
    if (!str_ends_with($path, '/') && !preg_match('#\.[a-z0-9]{2,5}$#i', $path)) {
        $q = $_SERVER['QUERY_STRING'] ?? '';
        $hit = page_by_path($path . '/');
        if ($hit || find_redirect($path . '/')) {
            redirect(encode_path($path . '/') . ($q !== '' ? '?' . $q : ''), 301);
        }
    }

    $page = page_by_path($path);
    $preview = $page && $page['status'] !== 'published' && is_admin();
    if ($page && ($page['status'] === 'published' || $preview)) {
        render_page($page, $preview);
        return;
    }

    if ($r = find_redirect($path)) {
        apply_redirect($r);
        return;
    }

    log_404($path);
    render_error(404);
}

function encode_path(string $path): string
{
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

/* ---------------- redirects ---------------- */

function find_redirect(string $path): ?array
{
    $st = db()->prepare("SELECT * FROM redirects WHERE active = 1 AND match_type = 'exact' AND (source = ? OR source = ?) LIMIT 1");
    $alt = str_ends_with($path, '/') ? rtrim($path, '/') : $path . '/';
    $st->execute([$path, $alt]);
    if ($r = $st->fetch()) {
        $r['_target'] = $r['target'];
        return $r;
    }
    foreach (db()->query("SELECT * FROM redirects WHERE active = 1 AND match_type = 'regex' ORDER BY id") as $r) {
        $re = '#' . str_replace('#', '\#', $r['source']) . '#u';
        if (@preg_match($re, $path, $m) === 1) {
            $r['_target'] = preg_replace_callback('/\$(\d+)/', fn($x) => $m[(int) $x[1]] ?? '', $r['target']) ?? $r['target'];
            return $r;
        }
    }
    return null;
}

function apply_redirect(array $r): void
{
    db()->prepare('UPDATE redirects SET hits = hits + 1, last_hit = ? WHERE id = ?')->execute([now(), $r['id']]);
    $code = (int) $r['code'];
    if (in_array($code, [410, 451], true)) {
        render_error($code);
        return;
    }
    $to = $r['_target'];
    if (!preg_match('#^https?://#', $to)) {
        $to = '/' . ltrim($to, '/');
    }
    redirect($to, in_array($code, [301, 302, 307, 308], true) ? $code : 301);
}

/* ---------------- 404 monitor ---------------- */

function is_scanner_path(string $path): bool
{
    return (bool) preg_match('#(wp-login|wp-admin|wp-content|wp-includes|xmlrpc|\.env|\.git|\.svn|\.aws|\.ds_store|phpmyadmin|pma/|cgi-bin|/vendor/|\.php\d?$|\.asp|\.jsp|\.sql|\.bak|\.zip$|\.tar|config\.|/actuator|/\.well-known/(?!security\.txt)|/owa/|/boaform|/hnap1|/solr/|eval-stdin)#i', $path);
}

function log_404(string $path): void
{
    if (is_scanner_path($path) || mb_strlen($path) > 300) {
        return;
    }
    $ref = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 300);
    db()->prepare('INSERT INTO notfound(path, hits, referrer, first_hit, last_hit) VALUES(?, 1, ?, ?, ?)
        ON CONFLICT(path) DO UPDATE SET hits = hits + 1, last_hit = excluded.last_hit, referrer = CASE WHEN excluded.referrer != \'\' THEN excluded.referrer ELSE referrer END')
        ->execute([$path, $ref, now(), now()]);
}

/* ---------------- rendering ---------------- */

function render_page(array $page, bool $preview = false): void
{
    $tpl = match ($page['type']) {
        'home' => 'home',
        'service' => 'service',
        'subservice' => 'subservice',
        'faq' => 'faq',
        'contact' => 'contact',
        default => 'page',
    };
    $extraFaq = [];
    if ($page['type'] === 'faq') {
        $groups = faq_groups();
        foreach ($groups as $g) {
            $extraFaq = array_merge($extraFaq, $g['items']);
        }
    }
    $content = view($tpl, ['page' => $page, 'groups' => $groups ?? []]);
    send_html(view('layout', [
        'page' => $page,
        'meta' => page_meta($page),
        'content' => $content,
        'jsonld' => jsonld($page, $extraFaq),
        'preview' => $preview,
    ]));
}

/** All FAQs of published service pages, grouped per top-level service, for the FAQ hub. */
function faq_groups(): array
{
    $groups = [];
    foreach (services() as $svc) {
        $items = faq_items($svc);
        foreach (published_children((int) $svc['id']) as $c) {
            $items = array_merge($items, faq_items($c));
        }
        // de-duplicate by question
        $seen = [];
        $items = array_values(array_filter($items, function ($f) use (&$seen) {
            $k = norm($f['q']);
            if (isset($seen[$k])) {
                return false;
            }
            return $seen[$k] = true;
        }));
        if ($items) {
            $groups[] = ['title' => $svc['title'], 'path' => $svc['path'], 'items' => $items];
        }
    }
    return $groups;
}

function send_html(string $html, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    if ($code === 200 && !is_admin()) {
        header('Cache-Control: public, max-age=300');
    } else {
        header('Cache-Control: no-store');
    }
    echo $html;
}

function render_error(int $code): void
{
    $titles = [
        404 => ['Η σελίδα δεν βρέθηκε', 'Η διεύθυνση που ζητήσατε δεν υπάρχει. Ίσως άλλαξε ή γράφτηκε λάθος.'],
        410 => ['Η σελίδα αφαιρέθηκε', 'Αυτή η σελίδα δεν είναι πλέον διαθέσιμη και δεν θα επιστρέψει.'],
        451 => ['Μη διαθέσιμο για νομικούς λόγους', 'Το περιεχόμενο αυτό δεν είναι διαθέσιμο για νομικούς λόγους.'],
        503 => ['Γίνεται συντήρηση', 'Ο ιστότοπος ενημερώνεται και θα είναι ξανά διαθέσιμος σε λίγο.'],
    ];
    [$t, $msg] = $titles[$code] ?? $titles[404];
    if ($code === 503) {
        header('Retry-After: 3600');
    }
    $page = [
        'id' => 0, 'type' => 'error', 'parent_id' => null, 'path' => request_path(), 'title' => $t, 'nav_label' => $t, 'h1' => $t, 'intro' => $msg,
        'meta_title' => $t . ' · ' . setting('site_name', 'ANV'), 'meta_desc' => $msg, 'hero_image' => '', 'noindex' => 1, 'status' => 'published',
        'body_html' => '', 'updated_at' => now(), 'schema_type' => 'WebPage', 'faq_json' => '[]', 'howto_json' => '[]', 'custom_jsonld' => '', 'is_placeholder' => 0,
    ];
    $content = view('error', ['page' => $page, 'code' => $code]);
    send_html(view('layout', ['page' => $page, 'meta' => page_meta($page), 'content' => $content, 'jsonld' => '', 'preview' => false]), $code);
}

/* ---------------- view helpers ---------------- */

function icon(string $name, int $size = 22): string
{
    $paths = [
        'ledger' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'shield' => '<path d="M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'chevron' => '<path d="M6 9l6 6 6-6"/>',
    ];
    $d = $paths[$name] ?? $paths['arrow'];
    return '<svg class="i" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

function tags_of(array $page): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) ($page['tags'] ?? '')))));
}

function address_line(): string
{
    $parts = array_filter([setting('address_street'), trim((setting('address_postcode') ?? '') . ' ' . (setting('address_city') ?? ''))]);
    return implode(', ', $parts);
}

function map_query(): string
{
    if (is_numeric(setting('geo_lat')) && is_numeric(setting('geo_lng'))) {
        return setting('geo_lat') . ',' . setting('geo_lng');
    }
    return (string) (setting('map_query') ?: address_line());
}

function socials(): array
{
    $labels = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X'];
    $out = [];
    foreach ($labels as $k => $l) {
        $v = (string) setting('social_' . $k, '');
        if (preg_match('#^https://#', $v)) {
            $out[$l] = $v;
        }
    }
    return $out;
}

/** <img> or <picture> for an uploaded image, with WebP alternative when present. */
function picture(string $src, string $alt, string $attrs = '', string $sizes = '100vw'): string
{
    if ($src === '') {
        return '';
    }
    $w = $h = 0;
    $webp = '';
    $st = db()->prepare('SELECT * FROM media WHERE path = ?');
    $st->execute([$src]);
    if ($m = $st->fetch()) {
        $w = (int) $m['width'];
        $h = (int) $m['height'];
        $webp = $m['webp'];
    }
    $dim = $w && $h ? ' width="' . $w . '" height="' . $h . '"' : '';
    $img = '<img src="' . e($src) . '" alt="' . e($alt) . '"' . $dim . ' ' . $attrs . '>';
    if ($webp !== '') {
        return '<picture><source type="image/webp" srcset="' . e($webp) . '">' . $img . '</picture>';
    }
    return $img;
}
