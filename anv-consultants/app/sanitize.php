<?php
declare(strict_types=1);

namespace App;

use DOMDocument;
use DOMElement;
use DOMNode;

/* =========================================================
 * Markdown → HTML (small, dependency-free, safe by default).
 * Output always passes through sanitize_html().
 * ========================================================= */

function markdown(string $md): string
{
    $md = str_replace(["\r\n", "\r"], "\n", $md);
    $html = md_blocks(explode("\n", $md));
    return sanitize_html($html);
}

function md_blocks(array $lines): string
{
    $out = [];
    $n = count($lines);
    $i = 0;
    $usedIds = [];
    while ($i < $n) {
        $line = $lines[$i];
        $t = trim($line);

        if ($t === '') {
            $i++;
            continue;
        }

        // fenced code
        if (preg_match('/^(```|~~~)\s*([\w-]*)/', $t, $m)) {
            $fence = $m[1];
            $buf = [];
            $i++;
            while ($i < $n && !str_starts_with(trim($lines[$i]), $fence)) {
                $buf[] = $lines[$i];
                $i++;
            }
            $i++;
            $out[] = '<pre><code>' . e(implode("\n", $buf)) . '</code></pre>';
            continue;
        }

        // heading (H1 inside a body becomes H2: the page owns the only H1)
        if (preg_match('/^(#{1,4})\s+(.+?)\s*#*$/u', $t, $m)) {
            $lvl = max(2, strlen($m[1]));
            $text = $m[2];
            $id = slugify(strip_tags(md_inline($text)));
            $base = $id;
            $k = 2;
            while (isset($usedIds[$id])) {
                $id = $base . '-' . $k++;
            }
            $usedIds[$id] = true;
            $out[] = "<h$lvl id=\"" . e($id) . "\">" . md_inline($text) . "</h$lvl>";
            $i++;
            continue;
        }

        // horizontal rule
        if (preg_match('/^(\*\s*){3,}$|^(-\s*){3,}$|^(_\s*){3,}$/', $t)) {
            $out[] = '<hr>';
            $i++;
            continue;
        }

        // blockquote
        if (str_starts_with($t, '>')) {
            $buf = [];
            while ($i < $n && str_starts_with(trim($lines[$i]), '>')) {
                $buf[] = preg_replace('/^\s*>\s?/', '', $lines[$i]);
                $i++;
            }
            $out[] = '<blockquote>' . md_blocks($buf) . '</blockquote>';
            continue;
        }

        // table
        if (str_contains($t, '|') && $i + 1 < $n && preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?$/', trim($lines[$i + 1]))) {
            $head = md_row($t);
            $i += 2;
            $rows = [];
            while ($i < $n && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
                $rows[] = md_row(trim($lines[$i]));
                $i++;
            }
            $h = '<div class="table-wrap"><table><thead><tr>';
            foreach ($head as $c) {
                $h .= '<th scope="col">' . md_inline($c) . '</th>';
            }
            $h .= '</tr></thead><tbody>';
            foreach ($rows as $r) {
                $h .= '<tr>';
                foreach ($head as $ci => $_) {
                    $h .= '<td>' . md_inline($r[$ci] ?? '') . '</td>';
                }
                $h .= '</tr>';
            }
            $out[] = $h . '</tbody></table></div>';
            continue;
        }

        // lists
        if (preg_match('/^([-*+]|\d+[.)])\s+/', $t, $m)) {
            $ordered = ctype_digit($m[1][0]);
            $items = [];
            while ($i < $n) {
                $lt = trim($lines[$i]);
                if (preg_match($ordered ? '/^\d+[.)]\s+(.*)$/' : '/^[-*+]\s+(.*)$/', $lt, $mm)) {
                    $items[] = $mm[1];
                } elseif ($lt !== '' && $items && preg_match('/^\s{2,}/', $lines[$i])) {
                    $items[count($items) - 1] .= ' ' . $lt; // continuation line
                } else {
                    break;
                }
                $i++;
            }
            $tag = $ordered ? 'ol' : 'ul';
            $out[] = "<$tag>" . implode('', array_map(fn($x) => '<li>' . md_inline($x) . '</li>', $items)) . "</$tag>";
            continue;
        }

        // raw HTML block (sanitised later)
        if (preg_match('/^<\/?[a-z][a-z0-9]*[\s>\/]/i', $t)) {
            $buf = [];
            while ($i < $n && trim($lines[$i]) !== '') {
                $buf[] = $lines[$i];
                $i++;
            }
            $out[] = implode("\n", $buf);
            continue;
        }

        // paragraph
        $buf = [];
        while ($i < $n) {
            $lt = trim($lines[$i]);
            if ($lt === '' || preg_match('/^(#{1,4}\s|>|```|~~~|[-*+]\s|\d+[.)]\s)/', $lt)) {
                break;
            }
            $buf[] = $lt;
            $i++;
        }
        $out[] = '<p>' . md_inline(implode("\n", $buf)) . '</p>';
    }
    return implode("\n", $out);
}

function md_row(string $line): array
{
    $line = trim(trim($line), '|');
    return array_map('trim', explode('|', $line));
}

function md_inline(string $s): string
{
    $codes = [];
    $s = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . e($m[1]) . '</code>';
        return "\x01" . (count($codes) - 1) . "\x01";
    }, $s) ?? $s;

    $s = e($s);

    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+&quot;([^&]*)&quot;)?\)/u', function ($m) {
        $src = safe_url(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'), true);
        return $src === '' ? '' : '<img src="' . e($src) . '" alt="' . $m[1] . '" loading="lazy">';
    }, $s) ?? $s;

    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+&quot;([^&]*)&quot;)?\)/u', function ($m) {
        $href = safe_url(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
        if ($href === '') {
            return $m[1];
        }
        $title = isset($m[3]) && $m[3] !== '' ? ' title="' . $m[3] . '"' : '';
        return '<a href="' . e($href) . '"' . $title . '>' . $m[1] . '</a>';
    }, $s) ?? $s;

    $s = preg_replace('/\*\*(.+?)\*\*|__(.+?)__/u', '<strong>$1$2</strong>', $s) ?? $s;
    $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])|(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/u', '<em>$1$2</em>', $s) ?? $s;
    $s = str_replace("\n", "<br>\n", $s);

    return preg_replace_callback("/\x01(\d+)\x01/", fn($m) => $codes[(int) $m[1]], $s) ?? $s;
}

/** Returns a safe URL or '' . Internal absolute links become relative. */
function safe_url(string $url, bool $image = false): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if ($image && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$#i', $url)) {
        return $url;
    }
    if (preg_match('#^(/|\#|\?)#', $url) && !str_starts_with($url, '//')) {
        return $url;
    }
    if (preg_match('#^https?://#i', $url)) {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $own = [strtolower((string) parse_url(base_url(), PHP_URL_HOST))];
        $own[] = preg_replace('/^www\./', '', $own[0]);
        $own[] = 'www.' . $own[1];
        if (in_array($host, $own, true)) {
            $p = parse_url($url);
            return ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '') . (isset($p['fragment']) ? '#' . $p['fragment'] : '');
        }
        return $url;
    }
    if (!$image && preg_match('#^(mailto|tel):#i', $url)) {
        return $url;
    }
    if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
        return $url; // relative like "page/"
    }
    return '';
}

/* =========================================================
 * Allow-list HTML sanitizer.
 * ========================================================= */

const ALLOWED_TAGS = [
    'p' => [], 'br' => [], 'hr' => [], 'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'],
    'ul' => [], 'ol' => ['start'], 'li' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
    'a' => ['href', 'title', 'rel', 'target'], 'blockquote' => [], 'code' => [], 'pre' => [],
    'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['scope', 'colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    'img' => ['src', 'alt', 'width', 'height', 'loading', 'title'], 'figure' => [], 'figcaption' => [],
    'div' => ['class'], 'span' => [], 'small' => [], 'sup' => [], 'sub' => [], 'mark' => [],
    'iframe' => ['src', 'width', 'height', 'title', 'allowfullscreen', 'loading', 'allow'],
];
const DROP_WITH_CONTENT = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'base', 'frame', 'frameset', 'applet'];

function iframe_allowed(string $src): bool
{
    if (!preg_match('#^https://#i', $src)) {
        return false;
    }
    $host = strtolower((string) parse_url($src, PHP_URL_HOST));
    $path = (string) parse_url($src, PHP_URL_PATH);
    return in_array($host, ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com', 'player.vimeo.com'], true)
        || (in_array($host, ['www.google.com', 'google.com', 'maps.google.com'], true) && str_starts_with($path, '/maps'));
}

function sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__root');
    if (!$root) {
        return e(strip_tags($html));
    }
    clean_node($root);
    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function clean_node(DOMNode $node): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
            $node->removeChild($child);
            continue;
        }
        if (!$child instanceof DOMElement) {
            continue;
        }
        $tag = strtolower($child->tagName);
        if (in_array($tag, DROP_WITH_CONTENT, true)) {
            $node->removeChild($child);
            continue;
        }
        if (!array_key_exists($tag, ALLOWED_TAGS)) {
            clean_node($child);
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }
        $allowed = ALLOWED_TAGS[$tag];
        foreach (iterator_to_array($child->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (!in_array($name, $allowed, true)) {
                $child->removeAttribute($attr->name);
            }
        }
        if ($tag === 'a') {
            $href = safe_url($child->getAttribute('href'));
            if ($href === '') {
                $child->removeAttribute('href');
            } else {
                $child->setAttribute('href', $href);
                if (preg_match('#^https?://#', $href)) {
                    $child->setAttribute('rel', 'noopener');
                }
            }
            if ($child->hasAttribute('target') && $child->getAttribute('target') !== '_blank') {
                $child->removeAttribute('target');
            }
        }
        if ($tag === 'img') {
            $src = safe_url($child->getAttribute('src'), true);
            if ($src === '') {
                $node->removeChild($child);
                continue;
            }
            $child->setAttribute('src', $src);
            if (!$child->hasAttribute('alt')) {
                $child->setAttribute('alt', '');
            }
            $child->setAttribute('loading', 'lazy');
        }
        if ($tag === 'iframe') {
            if (!iframe_allowed($child->getAttribute('src'))) {
                $node->removeChild($child);
                continue;
            }
            $child->setAttribute('loading', 'lazy');
            if (!$child->hasAttribute('title')) {
                $child->setAttribute('title', 'Ενσωματωμένο περιεχόμενο');
            }
        }
        if ($tag === 'div' && $child->hasAttribute('class') && $child->getAttribute('class') !== 'table-wrap') {
            $child->removeAttribute('class');
        }
        clean_node($child);
    }
}

/** Plain-text with no markup at all (for meta, JSON-LD, FAQ answers). */
function plain(string $s): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8')) ?? '');
}

/** Table of contents from H2s that carry ids. */
function toc(string $html): array
{
    preg_match_all('#<h2 id="([^"]+)">(.*?)</h2>#su', $html, $m, PREG_SET_ORDER);
    return array_map(fn($x) => ['id' => $x[1], 'text' => plain($x[2])], $m);
}
