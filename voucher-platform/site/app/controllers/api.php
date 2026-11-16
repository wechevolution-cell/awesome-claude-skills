<?php
declare(strict_types=1);

/**
 * JSON API v1 — the same backend the future iOS / Android app will use.
 * Auth: POST /api/v1/auth/login → { token }. Then send "Authorization: Bearer <token>".
 */
function api_body(): array
{
    $raw = file_get_contents('php://input');
    $j = $raw ? json_decode($raw, true) : null;
    return is_array($j) ? $j : $_POST;
}

function api_require(string ...$roles): array
{
    $u = current_user();
    if (!$u) {
        json_out(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        json_out(['ok' => false, 'error' => 'forbidden'], 403);
    }
    return $u;
}

function api_service(array $s): array
{
    return [
        'id' => (int)$s['id'], 'slug' => $s['slug'], 'title' => $s['title'], 'summary' => $s['summary'],
        'image' => $s['image'] ? abs_url($s['image']) : null, 'business' => $s['business_name'] ?? null,
        'price_cents' => (int)$s['price_cents'], 'regular_cents' => (int)$s['regular_cents'],
        'discount_percent' => discount_pct((int)$s['regular_cents'], (int)$s['price_cents']),
        'city' => $s['city'] ?? null, 'codes_left' => $s['left'] ?? null, 'url' => abs_url('/prosfora/' . $s['slug']),
    ];
}

function api_login(): void
{
    if (rate_limited('api-login:' . client_ip(), 20, 900)) {
        json_out(['ok' => false, 'error' => 'too_many_attempts'], 429);
    }
    $b = api_body();
    $r = attempt_login((string)($b['email'] ?? ''), (string)($b['password'] ?? ''));
    if (!$r['ok']) {
        json_out(['ok' => false, 'error' => $r['error']], 401);
    }
    $token = issue_api_token((int)$r['user']['id'], (string)($b['device'] ?? 'app'));
    json_out(['ok' => true, 'token' => $token, 'user' => ['id' => (int)$r['user']['id'], 'name' => $r['user']['name'], 'role' => $r['user']['role']]]);
}

function api_logout(): void
{
    api_require();
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+([a-f0-9]{64})/i', $h, $m)) {
        q('DELETE FROM api_tokens WHERE token_hash = ?', [hash('sha256', strtolower($m[1]))]);
    }
    json_out(['ok' => true]);
}

function api_me(): void
{
    $u = api_require();
    json_out(['ok' => true, 'user' => ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']]]);
}

function api_categories(): void
{
    json_out(['ok' => true, 'data' => array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name'], 'slug' => $c['slug'], 'parent_id' => $c['parent_id'] ? (int)$c['parent_id'] : null],
        all("SELECT * FROM categories WHERE status = 'active' ORDER BY sort, name"))]);
}

function api_campaigns(): void
{
    json_out(['ok' => true, 'data' => array_map(fn($c) => ['id' => (int)$c['id'], 'title' => $c['title'], 'slug' => $c['slug'], 'kind' => $c['kind'], 'summary' => $c['summary'],
        'image' => $c['image'] ? abs_url($c['image']) : null, 'ends_at' => $c['ends_at']], all('SELECT * FROM campaigns c WHERE ' . active_campaign_sql() . ' ORDER BY featured DESC, id DESC'))]);
}

function api_offers(): void
{
    [$where, $params, $sort] = offer_filters();
    $page = max(1, (int)input('page', 1));
    $items = service_cards($where, $params, $sort, 20, ($page - 1) * 20);
    json_out(['ok' => true, 'page' => $page, 'data' => array_map('api_service', $items)]);
}

function api_offer(array $p): void
{
    $rows = service_cards('s.slug = ?', [$p['slug']], 's.id', 1);
    if (!$rows) {
        json_out(['ok' => false, 'error' => 'not_found'], 404);
    }
    $s = $rows[0];
    $out = api_service($s) + [
        'description' => $s['description'], 'terms' => $s['terms'],
        'variants' => array_map(fn($v) => ['id' => (int)$v['id'], 'label' => $v['label'], 'price_cents' => (int)$v['price_cents'], 'regular_cents' => (int)$v['regular_cents']],
            all('SELECT * FROM service_variants WHERE service_id = ? ORDER BY sort, id', [$s['id']])),
        'stores' => array_map(fn($st) => ['name' => $st['name'], 'address' => $st['address'], 'city' => $st['city'], 'lat' => $st['lat'], 'lng' => $st['lng']],
            all("SELECT st.* FROM stores st JOIN service_stores ss ON ss.store_id = st.id WHERE ss.service_id = ? AND st.status = 'active'", [$s['id']])),
    ];
    json_out(['ok' => true, 'data' => $out]);
}

function api_my_codes(): void
{
    $u = api_require();
    $rows = all('SELECT c.code, c.status, c.expires_at, c.redeemed_at, s.title, s.slug, b.name AS business, v.label AS variant FROM codes c
                 JOIN services s ON s.id = c.service_id JOIN businesses b ON b.id = c.business_id LEFT JOIN service_variants v ON v.id = c.variant_id
                 WHERE c.user_id = ? ORDER BY c.id DESC', [$u['id']]);
    json_out(['ok' => true, 'data' => array_map(fn($r) => $r + ['qr' => code_redeem_url($r['code'])], $rows)]);
}

function api_redeem(): void
{
    $u = api_require('merchant', 'staff');
    $GLOBALS['__user'] = $u;
    $b = my_business();
    if (!$b) {
        json_out(['ok' => false, 'error' => 'no_business'], 403);
    }
    if (rate_limited('redeem:' . $u['id'], 60, 600)) {
        json_out(['ok' => false, 'error' => 'too_many_attempts'], 429);
    }
    $body = api_body();
    $confirm = !empty($body['confirm']);
    $r = $confirm ? redeem_code((string)($body['code'] ?? ''), (int)$b['id'], $b['member_store'] ? (int)$b['member_store'] : null, (int)$u['id'])
        : lookup_code((string)($body['code'] ?? ''), (int)$b['id']);
    [$type, $msg] = redeem_message($r);
    json_out(['ok' => $r['status'] === 'ok' || (!$confirm && $r['status'] === 'active'), 'status' => $r['status'], 'message' => $msg,
        'service' => $r['row']['service_title'] ?? null, 'customer' => $r['row']['customer_name'] ?? null]);
}
