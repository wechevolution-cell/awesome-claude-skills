<?php
declare(strict_types=1);

namespace App;

function indexable_pages(): array
{
    return array_values(array_filter(published_pages(), fn($p) => !(int) $p['noindex']));
}

function sitemap_xml(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    foreach (indexable_pages() as $p) {
        $x .= '  <url><loc>' . e(abs_url(encode_path($p['path']))) . '</loc><lastmod>' . date('c', strtotime($p['updated_at'])) . '</lastmod>';
        $imgs = [];
        if ($p['hero_image'] !== '') {
            $imgs[] = [$p['hero_image'], $p['hero_alt'] ?: $p['title']];
        }
        if ($p['type'] === 'home' && setting('home_hero_image')) {
            $imgs[] = [setting('home_hero_image'), setting('home_hero_alt', '') ?: $p['title']];
        }
        preg_match_all('#<img[^>]+src="(/uploads/[^"]+)"[^>]*alt="([^"]*)"#u', $p['body_html'], $m, PREG_SET_ORDER);
        foreach ($m as $im) {
            $imgs[] = [$im[1], html_entity_decode($im[2])];
        }
        foreach ($imgs as [$src, $alt]) {
            $x .= '<image:image><image:loc>' . e(abs_url($src)) . '</image:loc><image:title>' . e($alt) . '</image:title></image:image>';
        }
        $x .= "</url>\n";
    }
    $x .= '</urlset>';
    echo $x;
}

function robots_txt(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    $out = "User-agent: *\nAllow: /\nDisallow: /admin/\n\n";
    $ai = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended'];
    $allow = setting('allow_ai_bots', '1') === '1';
    foreach ($ai as $bot) {
        $out .= "User-agent: $bot\n" . ($allow ? "Allow: /\nDisallow: /admin/\n" : "Disallow: /\n") . "\n";
    }
    $out .= 'Sitemap: ' . abs_url('/sitemap.xml') . "\n";
    echo $out;
}

function llms_txt(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    $name = (string) setting('site_name', 'ANV');
    $o = "# $name\n\n> " . setting('tagline', '') . "\n\n";
    $facts = array_filter([
        'Τηλέφωνο' => setting('phone'),
        'Email' => setting('email'),
        'Διεύθυνση' => address_line(),
        'Γλώσσα' => 'Ελληνικά',
    ]);
    foreach ($facts as $k => $v) {
        $o .= "- $k: $v\n";
    }
    if (setting('hours_confirmed') === '1') {
        foreach (hours_grouped() as $r) {
            $o .= '- Ωράριο ' . $r['days'] . ': ' . $r['time'] . "\n";
        }
    }
    $o .= "\n## Υπηρεσίες\n\n";
    foreach (services() as $s) {
        $o .= '- [' . $s['title'] . '](' . abs_url($s['path']) . '): ' . plain($s['intro']) . "\n";
        foreach (published_children((int) $s['id']) as $c) {
            if (!(int) $c['noindex']) {
                $o .= '  - [' . $c['title'] . '](' . abs_url($c['path']) . '): ' . plain($c['intro']) . "\n";
            }
        }
    }
    $o .= "\n## Σελίδες\n\n";
    foreach (published_pages() as $p) {
        if (in_array($p['type'], ['page', 'faq', 'contact'], true) && !(int) $p['noindex']) {
            $o .= '- [' . $p['title'] . '](' . abs_url($p['path']) . '): ' . plain($p['intro']) . "\n";
        }
    }
    if ($x = trim((string) setting('llms_extra', ''))) {
        $o .= "\n## Επιπλέον\n\n" . $x . "\n";
    }
    $o .= "\n## Optional\n\n- [Sitemap](" . abs_url('/sitemap.xml') . ")\n- [RSS](" . abs_url('/feed.xml') . ")\n";
    echo $o;
}

/** No blog: the feed announces page updates (useful for crawlers and monitoring). */
function rss_feed(): void
{
    header('Content-Type: application/rss+xml; charset=utf-8');
    $pages = indexable_pages();
    usort($pages, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
    $pages = array_slice($pages, 0, 30);
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
    $x .= '<title>' . e((string) setting('site_name', 'ANV')) . '</title><link>' . e(abs_url('/')) . '</link>';
    $x .= '<description>' . e((string) setting('tagline', '')) . '</description><language>el</language>';
    $x .= '<atom:link href="' . e(abs_url('/feed.xml')) . '" rel="self" type="application/rss+xml"/>';
    foreach ($pages as $p) {
        $x .= '<item><title>' . e($p['title']) . '</title><link>' . e(abs_url(encode_path($p['path']))) . '</link><guid>' . e(abs_url(encode_path($p['path']))) . '</guid>';
        $x .= '<pubDate>' . date(DATE_RSS, strtotime($p['updated_at'])) . '</pubDate><description>' . e(plain($p['intro'] ?: excerpt($p['body_html']))) . '</description></item>';
    }
    echo $x . '</channel></rss>';
}
