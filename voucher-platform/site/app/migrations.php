<?php
declare(strict_types=1);

/**
 * Portable schema. Column spec: "type[:len] [nn] [uq] [d=value]".
 * Types: pk, int, str, text, bool, dt, money (integer cents).
 * All dates are written by PHP (now()), never by SQL functions, so both drivers behave the same.
 */
function col_sql(string $name, string $spec): string
{
    $parts = preg_split('/\s+/', trim($spec));
    [$type, $len] = array_pad(explode(':', array_shift($parts)), 2, null);
    $my = is_mysql();
    $map = [
        'pk'    => $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'int'   => $my ? 'INT' : 'INTEGER',
        'money' => $my ? 'INT' : 'INTEGER',
        'bool'  => $my ? 'TINYINT(1)' : 'INTEGER',
        'str'   => $my ? 'VARCHAR(' . ($len ?: 190) . ')' : 'TEXT',
        'text'  => $my ? 'MEDIUMTEXT' : 'TEXT',
        'dt'    => $my ? 'DATETIME' : 'TEXT',
    ];
    $sql = ident($name) . ' ' . $map[$type];
    foreach ($parts as $p) {
        if ($p === 'nn') {
            $sql .= ' NOT NULL';
        } elseif ($p === 'uq') {
            $sql .= ' UNIQUE';
        } elseif (str_starts_with($p, 'd=')) {
            $v = substr($p, 2);
            $sql .= ' DEFAULT ' . (is_numeric($v) ? $v : "'" . str_replace("'", '', $v) . "'");
        }
    }
    return $sql;
}

function create_table(string $name, array $cols, array $indexes = [], array $uniques = []): void
{
    $defs = [];
    foreach ($cols as $c => $spec) {
        $defs[] = col_sql($c, $spec);
    }
    foreach ($uniques as $u) {
        $defs[] = 'UNIQUE (' . implode(',', array_map('ident', $u)) . ')';
    }
    $sql = 'CREATE TABLE IF NOT EXISTS ' . ident($name) . ' (' . implode(', ', $defs) . ')';
    if (is_mysql()) {
        $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }
    db()->exec($sql);
    foreach ($indexes as $i => $icols) {
        $iname = 'ix_' . $name . '_' . $i;
        if (is_mysql()) {
            $exists = val('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$name, $iname]);
            if (!$exists) {
                db()->exec('CREATE INDEX ' . $iname . ' ON ' . $name . ' (' . implode(',', array_map('ident', $icols)) . ')');
            }
        } else {
            db()->exec('CREATE INDEX IF NOT EXISTS ' . $iname . ' ON ' . $name . ' (' . implode(',', array_map('ident', $icols)) . ')');
        }
    }
}

function migrations(): array
{
    return [
        1 => function () {
            create_table('settings', ['k' => 'str:100 nn uq', 'v' => 'text']);

            create_table('users', [
                'id' => 'pk', 'email' => 'str:190 nn uq', 'password_hash' => 'str:255 nn', 'name' => 'str:190 nn',
                'phone' => 'str:40', 'role' => 'str:20 nn d=consumer', 'status' => 'str:20 nn d=active',
                'affiliate_code' => 'str:40', 'referred_by' => 'str:40',
                'email_verified_at' => 'dt', 'last_login_at' => 'dt', 'created_at' => 'dt nn',
            ], ['role' => ['role']]);

            create_table('login_attempts', ['id' => 'pk', 'email' => 'str:190', 'ip' => 'str:64 nn', 'success' => 'bool nn d=0', 'created_at' => 'dt nn'],
                ['ip' => ['ip', 'created_at'], 'email' => ['email', 'created_at']]);
            create_table('password_resets', ['id' => 'pk', 'user_id' => 'int nn', 'token_hash' => 'str:64 nn uq', 'expires_at' => 'dt nn', 'used_at' => 'dt', 'created_at' => 'dt nn']);
            create_table('api_tokens', ['id' => 'pk', 'user_id' => 'int nn', 'token_hash' => 'str:64 nn uq', 'name' => 'str:100', 'last_used_at' => 'dt', 'created_at' => 'dt nn']);
            create_table('rate_limits', ['id' => 'pk', 'bucket' => 'str:190 nn', 'created_at' => 'dt nn'], ['b' => ['bucket', 'created_at']]);

            create_table('agents', [
                'id' => 'pk', 'user_id' => 'int nn uq', 'parent_agent_id' => 'int', 'region' => 'str:120',
                'commission_percent' => 'int nn d=0', 'commission_fixed_cents' => 'money nn d=0',
                'affiliate_code' => 'str:40 uq', 'status' => 'str:20 nn d=active', 'notes' => 'text', 'created_at' => 'dt nn',
            ]);

            create_table('businesses', [
                'id' => 'pk', 'name' => 'str:190 nn', 'slug' => 'str:190 nn uq', 'legal_name' => 'str:190', 'vat_number' => 'str:20',
                'description' => 'text', 'logo' => 'str:255', 'cover' => 'str:255', 'phone' => 'str:40', 'email' => 'str:190', 'website' => 'str:255',
                'agent_id' => 'int', 'referred_by' => 'str:40', 'free_publish' => 'bool nn d=0', 'plan' => 'str:40',
                'status' => 'str:20 nn d=pending', 'created_at' => 'dt nn',
            ], ['agent' => ['agent_id'], 'status' => ['status']]);

            create_table('business_users', ['id' => 'pk', 'business_id' => 'int nn', 'user_id' => 'int nn', 'role' => 'str:20 nn d=staff', 'store_id' => 'int', 'created_at' => 'dt nn'],
                ['u' => ['user_id']], [['business_id', 'user_id']]);

            create_table('stores', [
                'id' => 'pk', 'business_id' => 'int nn', 'name' => 'str:190 nn', 'address' => 'str:255', 'city' => 'str:120', 'region' => 'str:120',
                'postal_code' => 'str:10', 'lat' => 'str:20', 'lng' => 'str:20', 'phone' => 'str:40', 'hours' => 'text',
                'status' => 'str:20 nn d=active', 'created_at' => 'dt nn',
            ], ['b' => ['business_id'], 'region' => ['region']]);

            create_table('categories', [
                'id' => 'pk', 'parent_id' => 'int', 'name' => 'str:120 nn', 'slug' => 'str:120 nn uq', 'description' => 'text',
                'image' => 'str:255', 'sort' => 'int nn d=0', 'status' => 'str:20 nn d=active',
                'meta_title' => 'str:190', 'meta_description' => 'str:255', 'created_at' => 'dt nn',
            ]);

            create_table('campaigns', [
                'id' => 'pk', 'title' => 'str:190 nn', 'slug' => 'str:190 nn uq', 'kind' => 'str:20 nn d=theme', 'summary' => 'str:255',
                'description' => 'text', 'image' => 'str:255', 'region' => 'str:120', 'starts_at' => 'dt', 'ends_at' => 'dt',
                'owner_type' => 'str:20 nn d=platform', 'business_id' => 'int', 'agent_id' => 'int', 'created_by' => 'int',
                'featured' => 'bool nn d=0', 'status' => 'str:20 nn d=draft', 'review_note' => 'str:255',
                'meta_title' => 'str:190', 'meta_description' => 'str:255', 'created_at' => 'dt nn',
            ], ['status' => ['status', 'ends_at']]);

            create_table('services', [
                'id' => 'pk', 'business_id' => 'int nn', 'category_id' => 'int', 'title' => 'str:190 nn', 'slug' => 'str:190 nn uq',
                'summary' => 'str:255', 'description' => 'text', 'terms' => 'text', 'image' => 'str:255',
                'regular_cents' => 'money nn d=0', 'price_cents' => 'money nn d=0',
                'code_validity_days' => 'int nn d=30', 'max_codes' => 'int', 'per_user_limit' => 'int nn d=1',
                'featured' => 'bool nn d=0', 'status' => 'str:20 nn d=pending', 'review_note' => 'str:255',
                'meta_title' => 'str:190', 'meta_description' => 'str:255', 'created_at' => 'dt nn', 'updated_at' => 'dt',
            ], ['b' => ['business_id'], 'cat' => ['category_id', 'status']]);

            create_table('service_variants', ['id' => 'pk', 'service_id' => 'int nn', 'label' => 'str:120 nn', 'regular_cents' => 'money nn d=0', 'price_cents' => 'money nn d=0', 'sort' => 'int nn d=0'],
                ['s' => ['service_id']]);
            create_table('service_stores', ['id' => 'pk', 'service_id' => 'int nn', 'store_id' => 'int nn'], [], [['service_id', 'store_id']]);
            create_table('campaign_services', ['id' => 'pk', 'campaign_id' => 'int nn', 'service_id' => 'int nn'], ['s' => ['service_id']], [['campaign_id', 'service_id']]);
            create_table('favorites', ['id' => 'pk', 'user_id' => 'int nn', 'service_id' => 'int nn', 'created_at' => 'dt nn'], [], [['user_id', 'service_id']]);

            create_table('orders', [
                'id' => 'pk', 'number' => 'str:30 nn uq', 'user_id' => 'int nn', 'status' => 'str:20 nn d=pending',
                'subtotal_cents' => 'money nn d=0', 'regular_cents' => 'money nn d=0', 'total_cents' => 'money nn d=0', 'platform_fee_cents' => 'money nn d=0',
                'payment_provider' => 'str:40', 'payment_ref' => 'str:190', 'customer_name' => 'str:190', 'customer_email' => 'str:190',
                'accepted_terms_at' => 'dt', 'created_at' => 'dt nn', 'paid_at' => 'dt',
            ], ['u' => ['user_id']]);
            create_table('order_items', [
                'id' => 'pk', 'order_id' => 'int nn', 'service_id' => 'int nn', 'variant_id' => 'int', 'business_id' => 'int nn',
                'title' => 'str:255 nn', 'qty' => 'int nn d=1', 'unit_cents' => 'money nn d=0', 'regular_cents' => 'money nn d=0',
            ], ['o' => ['order_id']]);

            create_table('codes', [
                'id' => 'pk', 'code' => 'str:20 nn uq', 'order_item_id' => 'int', 'user_id' => 'int nn', 'service_id' => 'int nn', 'variant_id' => 'int',
                'business_id' => 'int nn', 'paid_cents' => 'money nn d=0', 'status' => 'str:20 nn d=active', 'expires_at' => 'dt',
                'redeemed_at' => 'dt', 'redeemed_store_id' => 'int', 'redeemed_by' => 'int', 'created_at' => 'dt nn',
            ], ['u' => ['user_id', 'status'], 'b' => ['business_id', 'status'], 's' => ['service_id']]);

            create_table('commissions', [
                'id' => 'pk', 'agent_id' => 'int nn', 'business_id' => 'int nn', 'code_id' => 'int', 'amount_cents' => 'money nn d=0',
                'basis' => 'str:40', 'status' => 'str:20 nn d=pending', 'created_at' => 'dt nn', 'paid_at' => 'dt',
            ], ['a' => ['agent_id', 'status']]);

            create_table('reviews', [
                'id' => 'pk', 'code_id' => 'int nn uq', 'user_id' => 'int nn', 'service_id' => 'int nn', 'business_id' => 'int nn',
                'rating' => 'int nn', 'body' => 'text', 'status' => 'str:20 nn d=pending', 'created_at' => 'dt nn',
            ], ['s' => ['service_id', 'status']]);

            create_table('messages', ['id' => 'pk', 'kind' => 'str:30 nn d=contact', 'name' => 'str:190', 'email' => 'str:190', 'phone' => 'str:40', 'body' => 'text', 'ip' => 'str:64', 'is_read' => 'bool nn d=0', 'created_at' => 'dt nn']);
            create_table('audit_log', ['id' => 'pk', 'user_id' => 'int', 'action' => 'str:60 nn', 'entity' => 'str:40', 'entity_id' => 'int', 'meta' => 'text', 'ip' => 'str:64', 'created_at' => 'dt nn'],
                ['e' => ['entity', 'entity_id']]);
        },
    ];
}

function migrate(): void
{
    create_table('schema_migrations', ['version' => 'int nn uq', 'applied_at' => 'dt nn']);
    $done = array_map('intval', array_column(all('SELECT version FROM schema_migrations'), 'version'));
    foreach (migrations() as $v => $fn) {
        if (in_array($v, $done, true)) {
            continue;
        }
        $fn();
        insert('schema_migrations', ['version' => $v, 'applied_at' => now()]);
    }
}
