<?php
/**
 * Front controller. Every request that is not a real file goes through here.
 * Local test:  php -S localhost:8000 index.php
 */
declare(strict_types=1);

// Built-in PHP server: serve existing static files directly, but never app/data files.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if (preg_match('#^/(app|data)(/|$)#', $path) || preg_match('#^/uploads/.*\.php#i', $path)) {
        http_response_code(403);
        exit('Forbidden');
    }
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';
dispatch();
