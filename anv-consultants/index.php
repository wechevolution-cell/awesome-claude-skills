<?php
declare(strict_types=1);

/*
 * ANV — front controller.
 * Production: Apache/LiteSpeed via .htaccess. Local: php -S localhost:8000 index.php
 */

// Built-in dev server: let it serve real static files directly.
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    $f = __DIR__ . $p;
    if ($p !== '/' && is_file($f)) {
        // Mirror the .htaccess protections for the dev server.
        if (preg_match('#^/(app|data|views)/#', $p) || preg_match('#^/uploads/.*\.(php\d?|phtml|phar)$#i', $p)) {
            http_response_code(403);
            exit('Forbidden');
        }
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';

App\route();
