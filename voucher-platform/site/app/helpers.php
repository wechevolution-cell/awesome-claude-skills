<?php
declare(strict_types=1);

function config(string $key, $default = null)
{
    return $GLOBALS['CONFIG'][$key] ?? $default;
}

function is_dev(): bool
{
    return config('env', 'production') !== 'production';
}

function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function base_url(): string
{
    $b = rtrim((string)config('base_url', ''), '/');
    if ($b !== '') {
        return $b;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', $host);
    return (is_https() ? 'https' : 'http') . '://' . $host;
}

function url(string $path = '/', array $query = []): string
{
    $u = '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function abs_url(string $path = '/'): string
{
    return base_url() . url($path);
}

function asset(string $path): string
{
    $file = ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string)filemtime($file)), 0, 8) : APP_VERSION;
    return '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function request_path(): string
{
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $p = rawurldecode($p);
    return $p === '' ? '/' : $p;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url($ref, PHP_URL_HOST);
    if ($ref && $host === parse_url(base_url(), PHP_URL_HOST)) {
        redirect($ref);
    }
    redirect($fallback);
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

// ---------- views ----------
function view(string $__view, array $__data = []): string
{
    extract($__data, EXTR_SKIP);
    unset($__data);
    ob_start();
    require VIEWS . '/' . $__view . '.php';
    return (string)ob_get_clean();
}

function render(string $name, array $data = [], string $layout = 'layout', int $status = 200): never
{
    http_response_code($status);
    $data['content'] = view($name, $data);
    header('Content-Type: text/html; charset=utf-8');
    echo view($layout, $data);
    exit;
}

function partial(string $name, array $data = []): void
{
    echo view('partials/' . $name, $data);
}

// ---------- flash + old input ----------
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function keep_old(array $data): void
{
    unset($data['password'], $data['password2'], $data['_csrf']);
    $_SESSION['old'] = $data;
}

function old(string $key, $default = ''): string
{
    return (string)($_SESSION['old_view'][$key] ?? $default);
}

// ---------- CSRF ----------
function csrf_token(): string
{
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
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        if (wants_json()) {
            json_out(['ok' => false, 'error' => 'Η φόρμα έληξε. Ανανέωσε τη σελίδα και δοκίμασε ξανά.'], 419);
        }
        http_response_code(419);
        render('public/error', ['title' => 'Η φόρμα έληξε', 'message' => 'Ανανέωσε τη σελίδα και δοκίμασε ξανά.', 'noindex' => true], 'layout', 419);
    }
}

/** Honeypot + minimum fill time for public forms. Returns true when the request looks like a bot. */
function looks_like_bot(int $minSeconds = 3): bool
{
    if (!empty($_POST['website_url'])) {
        return true;
    }
    $t = (int)($_POST['_t'] ?? 0);
    return $t > 0 && (time() - $t) < $minSeconds;
}

function bot_fields(): string
{
    return '<div class="hp" aria-hidden="true"><label>Αφήστε κενό<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="_t" value="' . time() . '">';
}

// ---------- rate limit ----------
function rate_limited(string $bucket, int $max, int $windowSeconds): bool
{
    $since = date('Y-m-d H:i:s', time() - $windowSeconds);
    $n = (int)val('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at > ?', [$bucket, $since]);
    if ($n >= $max) {
        return true;
    }
    insert('rate_limits', ['bucket' => $bucket, 'created_at' => now()]);
    if (random_int(1, 50) === 1) {
        q('DELETE FROM rate_limits WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
    return false;
}

// ---------- text ----------
function slugify(string $s): string
{
    $map = [
        'ου' => 'ou', 'ού' => 'ou', 'αι' => 'ai', 'ει' => 'ei', 'οι' => 'oi', 'μπ' => 'b', 'ντ' => 'nt', 'γκ' => 'gk', 'αυ' => 'av', 'ευ' => 'ev',
        'α' => 'a', 'ά' => 'a', 'β' => 'v', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'έ' => 'e', 'ζ' => 'z', 'η' => 'i', 'ή' => 'i', 'θ' => 'th',
        'ι' => 'i', 'ί' => 'i', 'ϊ' => 'i', 'ΐ' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x', 'ο' => 'o', 'ό' => 'o',
        'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'ς' => 's', 'τ' => 't', 'υ' => 'y', 'ύ' => 'y', 'ϋ' => 'y', 'ΰ' => 'y', 'φ' => 'f', 'χ' => 'ch',
        'ψ' => 'ps', 'ω' => 'o', 'ώ' => 'o',
    ];
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-') ?: 'item';
}

function unique_slug(string $table, string $base, ?int $exceptId = null): string
{
    $slug = slugify($base);
    $try = $slug;
    $i = 2;
    while (true) {
        $row = one('SELECT id FROM ' . ident($table) . ' WHERE slug = ?', [$try]);
        if (!$row || ($exceptId && (int)$row['id'] === $exceptId)) {
            return $try;
        }
        $try = $slug . '-' . $i++;
    }
}

/** Accent-free lowercase for comparisons (Greek and Latin). */
function fold(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, ['ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ϊ' => 'ι', 'ΐ' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ϋ' => 'υ', 'ΰ' => 'υ', 'ώ' => 'ω', 'ς' => 'σ']);
}

function money(int $cents): string
{
    return number_format($cents / 100, 2, ',', '.') . ' €';
}

function money_short(int $cents): string
{
    return ($cents % 100 === 0 ? number_format($cents / 100, 0, ',', '.') : number_format($cents / 100, 2, ',', '.')) . ' €';
}

function to_cents($v): int
{
    $v = str_replace([' ', '€'], '', (string)$v);
    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (int)round(((float)$v) * 100);
}

function discount_pct(int $regular, int $price): int
{
    return ($regular > 0 && $price < $regular) ? (int)round(100 - ($price / $regular * 100)) : 0;
}

function date_gr(?string $dt, bool $time = false): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date($time ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '—';
}

function excerpt(string $html, int $len = 160): string
{
    $t = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len - 1)) . '…' : $t;
}

/** Plain text with paragraphs and line breaks, safe. */
function nl2p(?string $text): string
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    $out = '';
    foreach (preg_split("/\n{2,}/", str_replace("\r", '', $text)) as $p) {
        $out .= '<p>' . nl2br(e($p)) . '</p>';
    }
    return $out;
}

function audit(string $action, ?string $entity = null, ?int $id = null, array $meta = []): void
{
    insert('audit_log', [
        'user_id' => current_user()['id'] ?? null, 'action' => $action, 'entity' => $entity, 'entity_id' => $id,
        'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, 'ip' => client_ip(), 'created_at' => now(),
    ]);
}

function abort(int $code, string $title = '', string $message = ''): never
{
    if (wants_json()) {
        json_out(['ok' => false, 'error' => $title ?: 'Σφάλμα'], $code);
    }
    $titles = [403 => 'Δεν έχεις πρόσβαση', 404 => 'Η σελίδα δεν βρέθηκε', 410 => 'Η σελίδα δεν υπάρχει πια'];
    $msgs = [
        403 => 'Συνδέσου με λογαριασμό που έχει δικαίωμα πρόσβασης σε αυτή τη σελίδα.',
        404 => 'Μπορεί η διεύθυνση να άλλαξε ή η προσφορά να έληξε. Δες τις τρέχουσες προσφορές.',
        410 => 'Το περιεχόμενο αφαιρέθηκε οριστικά. Δες τις τρέχουσες προσφορές.',
    ];
    render('public/error', [
        'title' => $title ?: ($titles[$code] ?? 'Σφάλμα'),
        'message' => $message ?: ($msgs[$code] ?? 'Κάτι πήγε στραβά. Δοκίμασε ξανά σε λίγο.'),
        'code' => $code, 'noindex' => true,
    ], 'layout', $code);
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage, 'total' => $total];
}

function query_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return $q ? '?' . http_build_query($q) : '?';
}
