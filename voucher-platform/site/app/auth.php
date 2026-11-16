<?php
declare(strict_types=1);

const ROLES = [
    'consumer' => 'Καταναλωτής',
    'merchant' => 'Επιχείρηση (ιδιοκτήτης)',
    'staff'    => 'Υπάλληλος επιχείρησης',
    'agent'    => 'Αντιπρόσωπος',
    'admin'    => 'Διαχειριστής',
];

const LOCKOUT_MAX = 5;
const LOCKOUT_MINUTES = 15;

function current_user(): ?array
{
    if (array_key_exists('__user', $GLOBALS)) {
        return $GLOBALS['__user'];
    }
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = one("SELECT * FROM users WHERE id = ? AND status = 'active'", [(int)$_SESSION['uid']]);
    } elseif (!empty($GLOBALS['__api_user'])) {
        $u = $GLOBALS['__api_user'];
    }
    $GLOBALS['__user'] = $u;
    return $u;
}

function is_role(string ...$roles): bool
{
    $u = current_user();
    return $u && in_array($u['role'], $roles, true);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        if (wants_json()) {
            json_out(['ok' => false, 'error' => 'Χρειάζεται σύνδεση.'], 401);
        }
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        flash('info', 'Συνδέσου για να συνεχίσεις.');
        redirect(url('/eisodos'));
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        abort(403);
    }
    return $u;
}

function is_locked_out(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - LOCKOUT_MINUTES * 60);
    $byEmail = (int)val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at > ?', [$email, $since]);
    $byIp = (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > ?', [client_ip(), $since]);
    return $byEmail >= LOCKOUT_MAX || $byIp >= LOCKOUT_MAX * 4;
}

/** @return array{ok:bool,user?:array,error?:string} */
function attempt_login(string $email, string $password, array $allowedRoles = []): array
{
    $email = mb_strtolower(trim($email));
    if (is_locked_out($email)) {
        return ['ok' => false, 'error' => 'Πολλές αποτυχημένες προσπάθειες. Δοκίμασε ξανά σε ' . LOCKOUT_MINUTES . ' λεπτά.'];
    }
    $u = one('SELECT * FROM users WHERE email = ?', [$email]);
    $ok = $u && password_verify($password, $u['password_hash']) && $u['status'] === 'active';
    if ($ok && $allowedRoles && !in_array($u['role'], $allowedRoles, true)) {
        $ok = false;
    }
    insert('login_attempts', ['email' => $email, 'ip' => client_ip(), 'success' => $ok ? 1 : 0, 'created_at' => now()]);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Λάθος email ή κωδικός πρόσβασης.'];
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $u['id']]);
    }
    update('users', ['last_login_at' => now()], ['id' => $u['id']]);
    return ['ok' => true, 'user' => $u];
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    unset($_SESSION['csrf']);
    $GLOBALS['__user'] = $u;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function validate_password(string $p): ?string
{
    if (mb_strlen($p) < 10) {
        return 'Ο κωδικός πρόσβασης χρειάζεται τουλάχιστον 10 χαρακτήρες.';
    }
    return null;
}

/** Creates a user and returns its id. Caller validates input first. */
function create_user(string $email, string $name, string $password, string $role = 'consumer', array $extra = []): int
{
    return insert('users', array_merge([
        'email' => mb_strtolower(trim($email)),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => trim($name),
        'role' => $role,
        'status' => 'active',
        'created_at' => now(),
    ], $extra));
}

function admin_exists(): bool
{
    return (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin'") > 0;
}

// ---------- businesses for merchant / staff ----------
function my_business(): ?array
{
    $u = current_user();
    if (!$u || !in_array($u['role'], ['merchant', 'staff'], true)) {
        return null;
    }
    $want = (int)($_SESSION['business_id'] ?? 0);
    $rows = all('SELECT b.*, bu.role AS member_role, bu.store_id AS member_store FROM business_users bu JOIN businesses b ON b.id = bu.business_id WHERE bu.user_id = ? ORDER BY b.name', [$u['id']]);
    foreach ($rows as $r) {
        if ((int)$r['id'] === $want) {
            return $r;
        }
    }
    return $rows[0] ?? null;
}

function my_agent(): ?array
{
    $u = current_user();
    return $u && $u['role'] === 'agent' ? one('SELECT * FROM agents WHERE user_id = ?', [$u['id']]) : null;
}

// ---------- password reset ----------
function create_reset_token(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    insert('password_resets', ['user_id' => $userId, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => now()]);
    return $token;
}

function find_reset(string $token): ?array
{
    return one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?', [hash('sha256', $token), now()]);
}

// ---------- API tokens (mobile app) ----------
function issue_api_token(int $userId, string $name = 'app'): string
{
    $token = bin2hex(random_bytes(32));
    insert('api_tokens', ['user_id' => $userId, 'token_hash' => hash('sha256', $token), 'name' => mb_substr($name, 0, 100), 'created_at' => now()]);
    return $token;
}

function api_user_from_header(): ?array
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $h, $m)) {
        return null;
    }
    $t = one('SELECT * FROM api_tokens WHERE token_hash = ?', [hash('sha256', strtolower($m[1]))]);
    if (!$t) {
        return null;
    }
    update('api_tokens', ['last_used_at' => now()], ['id' => $t['id']]);
    return one("SELECT * FROM users WHERE id = ? AND status = 'active'", [$t['user_id']]);
}

function new_affiliate_code(): string
{
    do {
        $c = substr(strtoupper(bin2hex(random_bytes(4))), 0, 7);
    } while (val('SELECT COUNT(*) FROM agents WHERE affiliate_code = ?', [$c]) || val('SELECT COUNT(*) FROM users WHERE affiliate_code = ?', [$c]));
    return $c;
}
