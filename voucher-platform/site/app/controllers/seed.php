<?php
declare(strict_types=1);

/** Structural data every install needs: the category tree. Safe to run more than once. */
function seed_base(): void
{
    if ((int)val('SELECT COUNT(*) FROM categories') > 0) {
        return;
    }
    $cats = [
        'Spa & ευεξία' => ['Μασάζ', 'Spa', 'Yoga & pilates'],
        'Ομορφιά' => ['Κομμωτήριο', 'Νύχια', 'Περιποίηση προσώπου'],
        'Εστίαση' => ['Εστιατόρια', 'Καφέ & γλυκά', 'Μπαρ'],
        'Γυμναστική' => ['Γυμναστήρια', 'Προσωπική προπόνηση'],
        'Αυτοκίνητο' => ['Πλύσιμο & detailing', 'Service'],
        'Εμπειρίες' => ['Δραστηριότητες', 'Μαθήματα'],
        'Υγεία' => [],
        'Σπίτι & υπηρεσίες' => [],
    ];
    $i = 0;
    foreach ($cats as $parent => $children) {
        $pid = insert('categories', ['name' => $parent, 'slug' => unique_slug('categories', $parent), 'sort' => $i++, 'status' => 'active', 'created_at' => now()]);
        foreach ($children as $j => $c) {
            insert('categories', ['name' => $c, 'slug' => unique_slug('categories', $c), 'parent_id' => $pid, 'sort' => $j, 'status' => 'active', 'created_at' => now()]);
        }
    }
}

function demo_track(string $table, int $id): void
{
    $ids = json_decode((string)setting('demo_ids', '{}'), true) ?: [];
    $ids[$table][] = $id;
    set_setting('demo_ids', json_encode($ids));
}

/**
 * Demo content for presentations. Every name carries "(δείγμα)"; prices are round example numbers.
 * Demo logins use random passwords shown only to the admin on /admin/demo.
 */
function seed_demo(): array
{
    if (setting('demo_loaded') === '1') {
        return json_decode((string)setting('demo_credentials', '[]'), true) ?: [];
    }
    seed_base();
    $cat = fn(string $name) => (int)val('SELECT id FROM categories WHERE name = ?', [$name]);
    $creds = [];
    $mkUser = function (string $email, string $name, string $role) use (&$creds): int {
        $pass = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 14);
        $id = create_user($email, $name, $pass, $role);
        demo_track('users', $id);
        $creds[] = ['role' => ROLES[$role], 'email' => $email, 'password' => $pass];
        return $id;
    };

    $agentUser = $mkUser('agent@demo.local', 'Αντιπρόσωπος (δείγμα)', 'agent');
    $agentId = insert('agents', ['user_id' => $agentUser, 'region' => 'Αττική', 'commission_percent' => 10, 'commission_fixed_cents' => 50, 'affiliate_code' => new_affiliate_code(), 'status' => 'active', 'created_at' => now()]);
    demo_track('agents', $agentId);

    $businesses = [
        ['Wellness Studio (δείγμα)', 'Γλυφάδα', 'Αττική', 'Spa & ευεξία', [
            ['Spa για δύο, 90 λεπτά', 'Μασάζ', 12000, 6900, ['90 λεπτά' => [12000, 6900], '120 λεπτά' => [15000, 8900]], 1],
            ['Μασάζ χαλάρωσης', 'Μασάζ', 5000, 2900, ['45 λεπτά' => [4000, 2500], '60 λεπτά' => [5000, 2900]], 0],
        ]],
        ['Atelier Ομορφιάς (δείγμα)', 'Θεσσαλονίκη', 'Θεσσαλονίκη', 'Ομορφιά', [
            ['Ανάλυση επιδερμίδας', 'Περιποίηση προσώπου', 3000, 0, [], 0],
            ['Θεραπεία προσώπου με ενυδάτωση', 'Περιποίηση προσώπου', 6000, 3500, [], 0],
        ]],
        ['Bistro Σεφ (δείγμα)', 'Αθήνα', 'Αττική', 'Εστίαση', [
            ['Μενού σεφ για δύο', 'Εστιατόρια', 9000, 5500, [], 1],
            ['Καφές και γλυκό', 'Καφέ & γλυκά', 800, 0, [], 0],
        ]],
        ['Detailing Pro (δείγμα)', 'Ηράκλειο', 'Κρήτη', 'Αυτοκίνητο', [
            ['Premium καθαρισμός αυτοκινήτου', 'Πλύσιμο & detailing', 8000, 4500, ['Μικρό όχημα' => [7000, 3900], 'Μεγάλο όχημα / SUV' => [9000, 4900]], 0],
        ]],
        ['Studio Κίνησης (δείγμα)', 'Πάτρα', 'Αχαΐα', 'Γυμναστική', [
            ['Δοκιμαστική προπόνηση', 'Γυμναστήρια', 1500, 0, [], 0],
            ['Μήνας pilates reformer', 'Yoga & pilates', 12000, 7900, [], 0],
        ]],
    ];
    $serviceIds = [];
    foreach ($businesses as $bi => [$bname, $city, $region, $parentCat, $services]) {
        $bid = insert('businesses', ['name' => $bname, 'slug' => unique_slug('businesses', $bname), 'description' => 'Πρότυπο κείμενο — έλεγξέ το πριν το live. Σύντομη περιγραφή της επιχείρησης.',
            'phone' => '210 000000' . $bi, 'agent_id' => $bi < 3 ? $agentId : null, 'free_publish' => 0, 'status' => 'active', 'created_at' => now()]);
        demo_track('businesses', $bid);
        $sid = insert('stores', ['business_id' => $bid, 'name' => $bname, 'address' => 'Οδός Δείγματος ' . ($bi + 1), 'city' => $city, 'region' => $region, 'postal_code' => '00000', 'hours' => "Δευ–Παρ 09:00–21:00\nΣάβ 10:00–16:00", 'status' => 'active', 'created_at' => now()]);
        demo_track('stores', $sid);
        if ($bi === 0) {
            $owner = $mkUser('merchant@demo.local', 'Ιδιοκτήτης (δείγμα)', 'merchant');
            $staff = $mkUser('staff@demo.local', 'Ταμίας (δείγμα)', 'staff');
            demo_track('business_users', insert('business_users', ['business_id' => $bid, 'user_id' => $owner, 'role' => 'owner', 'created_at' => now()]));
            demo_track('business_users', insert('business_users', ['business_id' => $bid, 'user_id' => $staff, 'role' => 'staff', 'store_id' => $sid, 'created_at' => now()]));
        }
        foreach ($services as [$title, $sub, $regular, $price, $variants, $featured]) {
            $id = insert('services', [
                'business_id' => $bid, 'category_id' => $cat($sub) ?: $cat($parentCat), 'title' => $title, 'slug' => unique_slug('services', $title),
                'summary' => 'Πρότυπο κείμενο — έλεγξέ το πριν το live. Τι περιλαμβάνει η υπηρεσία σε μία πρόταση.',
                'description' => "Πρότυπο κείμενο — έλεγξέ το πριν το live.\n\nΑναλυτική περιγραφή της υπηρεσίας όπως τη δίνει το κατάστημα: τι περιλαμβάνει, πόσο διαρκεί και τι χρειάζεται να ξέρει ο πελάτης.",
                'terms' => "Απαιτείται ραντεβού.\nΙσχύει σε όλα τα καταστήματα της επιχείρησης.\nΠρότυπο κείμενο — έλεγξέ το πριν το live.",
                'regular_cents' => $regular, 'price_cents' => $price, 'code_validity_days' => 60, 'max_codes' => 50, 'per_user_limit' => 2,
                'featured' => $featured, 'status' => 'active', 'created_at' => now(),
            ]);
            demo_track('services', $id);
            $serviceIds[] = $id;
            insert('service_stores', ['service_id' => $id, 'store_id' => $sid]);
            $k = 0;
            foreach ($variants as $label => [$r, $pr]) {
                insert('service_variants', ['service_id' => $id, 'label' => $label, 'regular_cents' => $r, 'price_cents' => $pr, 'sort' => $k++]);
            }
        }
    }
    $camps = [
        ['Χειμερινό self-care', 'theme', 'Spa, ομορφιά και ευεξία σε τιμές προσφοράς.', 1, [0, 1, 2, 3]],
        ['Μόνο αυτό το Σαββατοκύριακο', 'time', 'Προσφορές με περιορισμένους κωδικούς.', 0, [4, 5]],
        ['Fine dining', 'theme', 'Μενού σεφ και γεύσεις.', 0, [4, 5]],
        ['Πρώτη επίσκεψη δωρεάν', 'theme', 'Δωρεάν κωδικοί για να γνωρίσεις νέα καταστήματα.', 0, [2, 5, 7]],
    ];
    foreach ($camps as [$t, $kind, $sum, $feat, $idx]) {
        $cid = insert('campaigns', ['title' => $t . ' (δείγμα)', 'slug' => unique_slug('campaigns', $t), 'kind' => $kind, 'summary' => $sum, 'description' => 'Πρότυπο κείμενο — έλεγξέ το πριν το live.',
            'starts_at' => date('Y-m-d 00:00:00'), 'ends_at' => date('Y-m-d 23:59:59', strtotime('+45 day')), 'owner_type' => 'platform', 'featured' => $feat, 'status' => 'active', 'created_at' => now()]);
        demo_track('campaigns', $cid);
        foreach ($idx as $i) {
            if (isset($serviceIds[$i])) {
                insert('campaign_services', ['campaign_id' => $cid, 'service_id' => $serviceIds[$i]]);
            }
        }
    }
    $mkUser('consumer@demo.local', 'Πελάτης (δείγμα)', 'consumer');
    set_setting('demo_credentials', json_encode($creds, JSON_UNESCAPED_UNICODE));
    set_setting('demo_loaded', '1');
    audit('demo.load');
    return $creds;
}

function clear_demo(): void
{
    $ids = json_decode((string)setting('demo_ids', '{}'), true) ?: [];
    tx(function () use ($ids) {
        $svc = array_map('intval', $ids['services'] ?? []);
        if ($svc) {
            $in = implode(',', $svc);
            foreach (['service_variants', 'service_stores', 'campaign_services', 'favorites', 'reviews'] as $t) {
                q("DELETE FROM $t WHERE service_id IN ($in)");
            }
            q("DELETE FROM commissions WHERE code_id IN (SELECT id FROM codes WHERE service_id IN ($in))");
            q("DELETE FROM codes WHERE service_id IN ($in)");
            q("DELETE FROM order_items WHERE service_id IN ($in)");
            q('DELETE FROM orders WHERE id NOT IN (SELECT order_id FROM order_items)');
        }
        foreach (['campaigns', 'services', 'stores', 'business_users', 'businesses', 'agents', 'users'] as $t) {
            $list = array_map('intval', $ids[$t] ?? []);
            if ($list) {
                if ($t === 'campaigns') {
                    q('DELETE FROM campaign_services WHERE campaign_id IN (' . implode(',', $list) . ')');
                }
                if ($t === 'users') {
                    $in = implode(',', $list);
                    foreach (['favorites', 'api_tokens', 'password_resets'] as $ut) {
                        q("DELETE FROM $ut WHERE user_id IN ($in)");
                    }
                }
                q("DELETE FROM $t WHERE id IN (" . implode(',', $list) . ')');
            }
        }
    });
    set_setting('demo_ids', '{}');
    set_setting('demo_credentials', '[]');
    set_setting('demo_loaded', '0');
    audit('demo.clear');
}
