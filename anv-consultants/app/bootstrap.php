<?php
declare(strict_types=1);

namespace App;

const VERSION = '1.0.0';
define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/uploads');
define('DB_FILE', DATA_DIR . '/site.sqlite');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Απαιτείται PHP 8.1 ή νεότερη.');
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Athens');

$debug = is_file(DATA_DIR . '/.debug');
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
if (is_dir(DATA_DIR) && is_writable(DATA_DIR)) {
    ini_set('error_log', DATA_DIR . '/php-error.log');
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/sanitize.php';
require __DIR__ . '/content.php';
require __DIR__ . '/seo.php';
require __DIR__ . '/feeds.php';
require __DIR__ . '/pages.php';
require __DIR__ . '/importer.php';
require __DIR__ . '/admin.php';

set_exception_handler(function (\Throwable $e) use ($debug) {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo $debug
        ? '<pre>' . htmlspecialchars((string) $e) . '</pre>'
        : '<!doctype html><meta charset="utf-8"><title>Σφάλμα</title><p style="font-family:sans-serif;padding:2rem">Παρουσιάστηκε σφάλμα. Δοκιμάστε ξανά σε λίγο.</p>';
});

security_headers();
