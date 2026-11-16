<?php
/**
 * Copy of this file is created automatically as app/config.php on first visit.
 * Edit app/config.php (never this file) for the live server.
 */
return [
    // 'production' hides errors and enables secure cookies over HTTPS.
    'env' => 'production',

    // Leave empty to detect automatically. Example: 'https://example.gr'
    'base_url' => '',

    // Database. Local tests use SQLite. On the VPS / Hostinger use MySQL / MariaDB.
    'db' => [
        'driver'      => 'sqlite',            // 'mysql' or 'sqlite'
        'sqlite_path' => __DIR__ . '/../data/app.sqlite',
        'host'        => 'localhost',
        'port'        => 3306,
        'name'        => '',
        'user'        => '',
        'pass'        => '',
    ],

    // Outgoing email. Leave host empty to use PHP mail() (not recommended).
    'smtp' => [
        'host'      => '',
        'port'      => 587,
        'secure'    => 'tls',                 // 'tls', 'ssl' or ''
        'user'      => '',
        'pass'      => '',
        'from'      => '',
        'from_name' => '',
    ],

    // Filled in automatically on first visit. Keep it secret.
    'app_key' => '',
];
