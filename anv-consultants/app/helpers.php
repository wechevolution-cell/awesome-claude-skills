<?php
declare(strict_types=1);

namespace App;

/* ---------------- output ---------------- */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** Absolute base URL. Uses the canonical setting when present, else the current host. */
function base_url(): string
{
    $c = rtrim((string) setting('canonical_url', ''), '/');
    if ($c !== '') {
        return $c;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[a-z0-9.\-:\[\]]+$/i', $host)) {
        $host = 'localhost';
    }
    return (is_https() ? 'https' : 'http') . '://' . $host;
}

function abs_url(string $path): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : VERSION;
    return '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function view(string $name, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require ROOT . '/views/' . $name . '.php';
    return (string) ob_get_clean();
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------------- settings ---------------- */

function settings(): array
{
    static $cache = null;
    if ($cache === null || isset($GLOBALS['__settings_dirty'])) {
        unset($GLOBALS['__settings_dirty']);
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $r) {
            $cache[$r['key']] = $r['value'];
        }
    }
    return $cache;
}

function setting(string $key, ?string $default = null): ?string
{
    $s = settings();
    return array_key_exists($key, $s) && $s[$key] !== '' ? $s[$key] : $default;
}

function set_setting(string $key, string $value): void
{
    $st = db()->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $st->execute([$key, $value]);
    $GLOBALS['__settings_dirty'] = true;
}

/* ---------------- text ---------------- */

const GREEK_MAP = [
    'ου' => 'ou', 'ού' => 'ou', 'αι' => 'ai', 'αί' => 'ai', 'ει' => 'ei', 'εί' => 'ei', 'οι' => 'oi', 'οί' => 'oi',
    'μπ' => 'b', 'ντ' => 'nt', 'γκ' => 'gk', 'γγ' => 'gg', 'τσ' => 'ts', 'τζ' => 'tz',
    'α' => 'a', 'ά' => 'a', 'β' => 'v', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'έ' => 'e', 'ζ' => 'z', 'η' => 'i', 'ή' => 'i',
    'θ' => 'th', 'ι' => 'i', 'ί' => 'i', 'ϊ' => 'i', 'ΐ' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x',
    'ο' => 'o', 'ό' => 'o', 'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'ς' => 's', 'τ' => 't', 'υ' => 'y', 'ύ' => 'y', 'ϋ' => 'y',
    'ΰ' => 'y', 'φ' => 'f', 'χ' => 'ch', 'ψ' => 'ps', 'ω' => 'o', 'ώ' => 'o',
];

function slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, GREEK_MAP);
    if (function_exists('transliterator_transliterate')) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
        if (is_string($t)) {
            $s = $t;
        }
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', strtolower($s)) ?? '';
    return trim($s, '-') ?: 'selida';
}

/** Lowercase, accent-free form used for keyword comparisons. */
function norm(string $s): string
{
    $s = mb_strtolower($s);
    $s = strtr($s, ['ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ϊ' => 'ι', 'ΐ' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ϋ' => 'υ', 'ΰ' => 'υ', 'ώ' => 'ω', 'ς' => 'σ']);
    if (class_exists('\Normalizer')) {
        $n = \Normalizer::normalize($s, \Normalizer::FORM_D);
        if (is_string($n)) {
            $s = preg_replace('/\p{Mn}+/u', '', $n) ?? $s;
        }
    }
    return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}

function excerpt(string $html, int $len = 160): string
{
    $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) ?? '');
    if (mb_strlen($t) <= $len) {
        return $t;
    }
    $cut = mb_substr($t, 0, $len);
    $sp = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $sp ?: $len), ' ,.;:') . '…';
}

function lines(?string $s): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $s) ?: []), fn($l) => $l !== ''));
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function tel_href(string $phone): string
{
    $d = preg_replace('/[^0-9+]/', '', $phone) ?? '';
    if ($d !== '' && $d[0] !== '+' && strlen($d) === 10) {
        $d = '+30' . $d;
    }
    return 'tel:' . $d;
}

/* ---------------- session & csrf ---------------- */

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('anv_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    if (is_dir(DATA_DIR . '/sessions') || @mkdir(DATA_DIR . '/sessions', 0700, true)) {
        session_save_path(DATA_DIR . '/sessions');
    }
    session_start();
}

function csrf_token(): string
{
    session_start_secure();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    session_start_secure();
    $t = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(419);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif;padding:2rem">Η φόρμα έληξε ή δεν είναι έγκυρη. Επιστρέψτε, ανανεώστε τη σελίδα και δοκιμάστε ξανά.</p>');
    }
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    session_start_secure();
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Only true when an admin session cookie exists and is valid. Does not start a session for visitors. */
function is_admin(): bool
{
    if (empty($_COOKIE['anv_sess'])) {
        return false;
    }
    session_start_secure();
    return !empty($_SESSION['admin_id']);
}

/* ---------------- hours ---------------- */

const DAY_NAMES = [1 => 'Δευτέρα', 2 => 'Τρίτη', 3 => 'Τετάρτη', 4 => 'Πέμπτη', 5 => 'Παρασκευή', 6 => 'Σάββατο', 7 => 'Κυριακή'];
const DAY_SCHEMA = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

/** @return array<int, array<int, array{0:string,1:string}>> day => list of [open, close] */
function hours(): array
{
    $h = json_decode((string) setting('hours_json', '{}'), true);
    $out = [];
    for ($d = 1; $d <= 7; $d++) {
        $out[$d] = [];
        foreach ((array) ($h[$d] ?? $h[(string) $d] ?? []) as $slot) {
            if (is_array($slot) && count($slot) === 2 && preg_match('/^\d\d:\d\d$/', (string) $slot[0]) && preg_match('/^\d\d:\d\d$/', (string) $slot[1])) {
                $out[$d][] = [(string) $slot[0], (string) $slot[1]];
            }
        }
    }
    return $out;
}

function hours_label(array $slots): string
{
    if (!$slots) {
        return 'Κλειστά';
    }
    return implode(' & ', array_map(fn($s) => $s[0] . ' – ' . $s[1], $slots));
}

/** Rows grouped by identical schedules, e.g. "Δευτέρα – Παρασκευή: 09:00 – 17:00". */
function hours_grouped(): array
{
    $h = hours();
    $rows = [];
    $start = 1;
    for ($d = 1; $d <= 7; $d++) {
        if ($d === 7 || hours_label($h[$d + 1] ?? []) !== hours_label($h[$d])) {
            $label = $start === $d ? DAY_NAMES[$d] : DAY_NAMES[$start] . ' – ' . DAY_NAMES[$d];
            $rows[] = ['days' => $label, 'time' => hours_label($h[$d])];
            $start = $d + 1;
        }
    }
    return $rows;
}
