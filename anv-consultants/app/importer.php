<?php
declare(strict_types=1);

namespace App;

/*
 * Markdown import.
 * - First "# H1" becomes the page title / H1.
 * - The first paragraph becomes the intro (direct answer).
 * - A section "## FAQ" (or "## Συχνές ερωτήσεις") whose items are "### Question" + answer
 *   becomes the FAQ repeater and is removed from the body.
 * - Optional "## TL;DR" section becomes the TL;DR field.
 * - Optional "## Βασικά σημεία" / "## Key points" list becomes key points.
 * - Optional "## Πηγές" / "## Sources" list of links becomes the sources field.
 */
function import_markdown(string $md): array
{
    $md = str_replace(["\r\n", "\r"], "\n", trim($md));
    // Optional YAML-ish front matter: title, slug, description, keyword
    $front = [];
    if (preg_match('/^---\n(.*?)\n---\n/s', $md, $m)) {
        foreach (explode("\n", $m[1]) as $l) {
            if (preg_match('/^([a-z_]+):\s*(.+)$/i', trim($l), $kv)) {
                $front[strtolower($kv[1])] = trim($kv[2], " \"'");
            }
        }
        $md = substr($md, strlen($m[0]));
    }

    $title = $front['title'] ?? '';
    if (preg_match('/^#\s+(.+)$/m', $md, $m)) {
        $title = $title ?: trim($m[1]);
        $md = preg_replace('/^#\s+.+\n?/m', '', $md, 1) ?? $md;
    }

    $sections = preg_split('/^(?=##\s)/m', $md) ?: [$md];
    $keep = [];
    $faq = [];
    $tldr = '';
    $points = [];
    $sources = [];
    foreach ($sections as $sec) {
        if (!preg_match('/^##\s+(.+)$/m', $sec, $h)) {
            $keep[] = $sec;
            continue;
        }
        $head = norm(trim($h[1]));
        $content = trim(substr($sec, strlen($h[0]) + (int) strpos($sec, $h[0])));
        if (in_array($head, ['faq', 'συχνες ερωτησεις', 'ερωτησεις'], true)) {
            foreach (preg_split('/^###\s+/m', $content) ?: [] as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '') {
                    continue;
                }
                $parts = explode("\n", $chunk, 2);
                $q = trim($parts[0]);
                $a = trim($parts[1] ?? '');
                if ($q !== '' && $a !== '') {
                    $faq[] = ['q' => $q, 'a' => plain(markdown($a))];
                }
            }
        } elseif (in_array($head, ['tl;dr', 'tldr', 'συνοψη', 'με μια ματια'], true)) {
            $tldr = plain(markdown($content));
        } elseif (in_array($head, ['βασικα σημεια', 'key points'], true)) {
            foreach (lines($content) as $l) {
                if (preg_match('/^([-*+]|\d+[.)])\s+(.+)$/', $l, $mm)) {
                    $points[] = plain(md_inline($mm[2]));
                }
            }
        } elseif (in_array($head, ['πηγες', 'sources'], true)) {
            foreach (lines($content) as $l) {
                if (preg_match('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', $l, $mm)) {
                    $sources[] = $mm[1] . ' | ' . $mm[2];
                } elseif (preg_match('#(https?://\S+)#', $l, $mm)) {
                    $sources[] = $mm[1];
                }
            }
        } else {
            $keep[] = $sec;
        }
    }
    $body = trim(implode("\n", $keep));

    $intro = '';
    $paras = preg_split('/\n{2,}/', $body) ?: [];
    if ($paras && !preg_match('/^(#|[-*+]\s|\d+[.)]\s|>|\||```)/', ltrim($paras[0]))) {
        $intro = plain(md_inline(trim($paras[0])));
        array_shift($paras);
        $body = trim(implode("\n\n", $paras));
    }

    return [
        'title' => $title ?: 'Νέα σελίδα',
        'slug' => $front['slug'] ?? slugify($title ?: 'nea-selida'),
        'meta_desc' => $front['description'] ?? excerpt($intro, 158),
        'focus_kw' => $front['keyword'] ?? '',
        'intro' => $intro,
        'tldr' => $tldr,
        'key_points' => implode("\n", $points),
        'body_md' => $body,
        'faq_json' => json_encode($faq, JSON_UNESCAPED_UNICODE),
        'sources' => implode("\n", $sources),
    ];
}
