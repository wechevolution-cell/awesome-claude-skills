<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('VIEWS', APP . '/views');
define('APP_VERSION', '0.1.0');

// ---- config: create app/config.php with a fresh key on first run ----
$cfgFile = APP . '/config.php';
if (!is_file($cfgFile)) {
    $sample = file_get_contents(APP . '/config.sample.php');
    $key = bin2hex(random_bytes(32));
    $sample = str_replace("'app_key' => '',", "'app_key' => '" . $key . "',", $sample);
    if (getenv('APP_ENV') === 'dev') {
        $sample = str_replace("'env' => 'production',", "'env' => 'dev',", $sample);
    }
    @file_put_contents($cfgFile, $sample, LOCK_EX);
}
$GLOBALS['CONFIG'] = is_file($cfgFile) ? require $cfgFile : require APP . '/config.sample.php';
if (getenv('DB_SQLITE_PATH')) {
    $GLOBALS['CONFIG']['db']['driver'] = 'sqlite';
    $GLOBALS['CONFIG']['db']['sqlite_path'] = getenv('DB_SQLITE_PATH');
}

date_default_timezone_set('Europe/Athens');
mb_internal_encoding('UTF-8');

$isDev = ($GLOBALS['CONFIG']['env'] ?? 'production') !== 'production';
error_reporting(E_ALL);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/data/php-error.log');

foreach (['helpers', 'db', 'migrations', 'settings', 'auth', 'mailer', 'uploads', 'codes', 'cart', 'payments', 'seo', 'crud', 'routes'] as $f) {
    require APP . '/' . $f . '.php';
}
foreach (glob(APP . '/controllers/*.php') as $f) {
    require $f;
}

// ---- session hardening ----
if (PHP_SAPI !== 'cli') {
    session_name('sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_start();

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');
}

migrate();
