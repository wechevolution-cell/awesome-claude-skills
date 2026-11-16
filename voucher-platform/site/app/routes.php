<?php
declare(strict_types=1);

function routes(): array
{
    return [
        // system
        ['GET', '/sitemap.xml', 'sys_sitemap'],
        ['GET', '/robots.txt', 'sys_robots'],
        ['GET', '/llms.txt', 'sys_llms'],
        ['GET', '/manifest.webmanifest', 'sys_manifest'],
        ['GET', '/icon-{size}.png', 'sys_icon'],
        ['GET', '/offline', 'sys_offline'],

        // public
        ['GET', '/', 'pub_home'],
        ['GET', '/prosfores', 'pub_offers'],
        ['GET', '/prosfora/{slug}', 'pub_service'],
        ['GET', '/kampanies', 'pub_campaigns'],
        ['GET', '/kampania/{slug}', 'pub_campaign'],
        ['GET', '/katigories', 'pub_categories'],
        ['GET', '/katigoria/{slug}', 'pub_category'],
        ['GET', '/katastima/{slug}', 'pub_business'],
        ['GET', '/anazitisi', 'pub_search'],
        ['GET', '/pos-leitourgei', 'pub_how'],
        ['GET', '/syxnes-erotiseis', 'pub_faq'],
        ['GET|POST', '/epikoinonia', 'pub_contact'],
        ['GET|POST', '/synergates', 'pub_partners'],
        ['GET', '/oroi', 'pub_legal'],
        ['GET', '/aporrito', 'pub_legal'],
        ['GET', '/cookies', 'pub_legal'],

        // cart + checkout
        ['GET', '/kalathi', 'cart_page'],
        ['POST', '/kalathi/add', 'cart_add_action'],
        ['POST', '/kalathi/update', 'cart_update_action'],
        ['GET|POST', '/tameio', 'checkout_page'],
        ['GET', '/paraggelia/{num}', 'order_done'],
        ['GET|POST', '/pay/test/{num}', 'pay_test'],
        ['POST', '/agapimena/{id}', 'favorite_toggle'],

        // auth
        ['GET|POST', '/eisodos', 'auth_login'],
        ['GET|POST', '/eggrafi', 'auth_register'],
        ['GET|POST', '/xehasa-kodiko', 'auth_forgot'],
        ['GET|POST', '/epanafora/{token}', 'auth_reset'],
        ['POST', '/exodos', 'auth_logout'],

        // consumer account
        ['GET', '/logariasmos', 'acc_home'],
        ['GET', '/logariasmos/kodikoi', 'acc_codes'],
        ['GET', '/logariasmos/kodikos/{code}', 'acc_code'],
        ['GET', '/logariasmos/agapimena', 'acc_favorites'],
        ['GET', '/logariasmos/paraggelies', 'acc_orders'],
        ['GET|POST', '/logariasmos/stoixeia', 'acc_profile'],

        // business panel
        ['GET', '/business', 'biz_home'],
        ['GET|POST', '/business/redeem', 'biz_redeem'],
        ['POST', '/business/redeem/check', 'biz_redeem_check'],
        ['GET', '/business/redemptions', 'biz_redemptions'],
        ['GET', '/business/redemptions.csv', 'biz_redemptions_csv'],
        ['POST', '/business/switch', 'biz_switch'],
        ['GET|POST', '/business/staff', 'biz_staff'],

        // agent panel
        ['GET', '/agent', 'agent_home'],
        ['GET|POST', '/agent/new-business', 'agent_new_business'],
        ['GET', '/agent/commissions', 'agent_commissions'],

        // admin
        ['GET|POST', '/admin/setup', 'admin_setup'],
        ['GET|POST', '/admin/login', 'admin_login'],
        ['GET', '/admin', 'admin_home'],
        ['GET', '/admin/approvals', 'admin_approvals'],
        ['POST', '/admin/approve', 'admin_approve'],
        ['GET|POST', '/admin/settings', 'admin_settings'],
        ['GET', '/admin/orders', 'admin_orders'],
        ['GET', '/admin/codes', 'admin_codes'],
        ['GET', '/admin/commissions', 'admin_commissions'],
        ['POST', '/admin/commissions/pay', 'admin_commissions_pay'],
        ['GET', '/admin/messages', 'admin_messages'],
        ['GET|POST', '/admin/demo', 'admin_demo'],
        ['GET', '/admin/export/{what}.csv', 'admin_export'],

        // generic CRUD for admin / business / agent
        ['GET', '/{area}/c/{entity}', 'crud_index'],
        ['GET', '/{area}/c/{entity}/new', 'crud_new'],
        ['GET', '/{area}/c/{entity}/{id}', 'crud_edit'],
        ['POST', '/{area}/c/{entity}/save', 'crud_save_action'],
        ['GET|POST', '/{area}/c/{entity}/{id}/delete', 'crud_delete_action'],

        // JSON API for the mobile app (see docs/ARCHITECTURE.md)
        ['POST', '/api/v1/auth/login', 'api_login'],
        ['POST', '/api/v1/auth/logout', 'api_logout'],
        ['GET', '/api/v1/me', 'api_me'],
        ['GET', '/api/v1/categories', 'api_categories'],
        ['GET', '/api/v1/campaigns', 'api_campaigns'],
        ['GET', '/api/v1/offers', 'api_offers'],
        ['GET', '/api/v1/offers/{slug}', 'api_offer'],
        ['GET', '/api/v1/me/codes', 'api_my_codes'],
        ['POST', '/api/v1/business/redeem', 'api_redeem'],
    ];
}

function dispatch(): void
{
    $_SESSION['old_view'] = $_SESSION['old'] ?? [];
    unset($_SESSION['old']);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = request_path();
    if ($path !== '/' && str_ends_with($path, '/')) {
        redirect(rtrim($path, '/') . (($q = $_SERVER['QUERY_STRING'] ?? '') ? '?' . $q : ''), 301);
    }

    if (str_starts_with($path, '/api/')) {
        $GLOBALS['__api_user'] = api_user_from_header();
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    // first run: send to admin setup until an admin exists
    if (!admin_exists() && !str_starts_with($path, '/admin/setup') && !str_starts_with($path, '/assets/') && !in_array($path, ['/robots.txt', '/manifest.webmanifest'], true) && !str_starts_with($path, '/icon-')) {
        redirect(url('/admin/setup'));
    }

    // referral links: ?ref=CODE is remembered for 30 days
    if (!empty($_GET['ref']) && preg_match('/^[A-Z0-9]{4,12}$/', (string)$_GET['ref'])) {
        setcookie('ref', (string)$_GET['ref'], ['expires' => time() + 30 * 86400, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE['ref'] = (string)$_GET['ref'];
    }

    $allowed = false;
    foreach (routes() as [$m, $pattern, $handler]) {
        $re = '#^' . preg_replace('#\\\{([a-z]+)\\\}#', '(?P<$1>[^/]+)', preg_quote($pattern, '#')) . '$#u';
        if (!preg_match($re, $path, $mm)) {
            continue;
        }
        if (!in_array($method, explode('|', $m), true) && !($method === 'HEAD' && str_contains($m, 'GET'))) {
            $allowed = true;
            continue;
        }
        $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
        if (is_post() && !str_starts_with($path, '/api/')) {
            csrf_check();
        }
        $handler($params);
        return;
    }
    if ($allowed) {
        http_response_code(405);
        exit('Method not allowed');
    }
    abort(404);
}
