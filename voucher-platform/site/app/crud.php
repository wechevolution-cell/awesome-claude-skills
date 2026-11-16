<?php
declare(strict_types=1);

/**
 * Schema-driven list/form/save/delete used by all three panels.
 * Each area sees its own subset of entities; "scope" columns are forced on save and filtered on read,
 * so a business can never see or edit another business's rows.
 */

const STATUS_OPTIONS = [
    'pending' => 'Σε έγκριση', 'active' => 'Ενεργό', 'paused' => 'Σε παύση', 'rejected' => 'Απορρίφθηκε',
    'draft' => 'Πρόχειρο', 'ended' => 'Έληξε', 'suspended' => 'Σε αναστολή',
];

function opts_categories(): array
{
    $o = ['' => '—'];
    foreach (all('SELECT id, name, parent_id FROM categories ORDER BY sort, name') as $r) {
        $o[$r['id']] = ($r['parent_id'] ? '— ' : '') . $r['name'];
    }
    return $o;
}

function opts_businesses(): array
{
    $o = [];
    foreach (all('SELECT id, name FROM businesses ORDER BY name') as $r) {
        $o[$r['id']] = $r['name'];
    }
    return $o;
}

function opts_agents(): array
{
    $o = ['' => '— Χωρίς αντιπρόσωπο —'];
    foreach (all('SELECT a.id, u.name, a.region FROM agents a JOIN users u ON u.id = a.user_id ORDER BY u.name') as $r) {
        $o[$r['id']] = $r['name'] . ($r['region'] ? ' · ' . $r['region'] : '');
    }
    return $o;
}

function crud_defs(string $area): array
{
    $b = $area === 'business' ? my_business() : null;
    $a = $area === 'agent' ? my_agent() : null;

    $categories = [
        'table' => 'categories', 'title' => 'Κατηγορίες', 'singular' => 'κατηγορία', 'order' => 'sort, name',
        'list' => ['name' => 'Όνομα', 'slug' => 'Slug', 'status' => 'Κατάσταση'], 'search' => ['name'],
        'fields' => [
            'name' => ['label' => 'Όνομα', 'type' => 'text', 'required' => true],
            'slug' => ['label' => 'Slug (διεύθυνση)', 'type' => 'slug', 'from' => 'name', 'help' => 'Αφήστε κενό για αυτόματο.'],
            'parent_id' => ['label' => 'Γονική κατηγορία', 'type' => 'select', 'options' => 'opts_categories'],
            'description' => ['label' => 'Περιγραφή', 'type' => 'textarea'],
            'image' => ['label' => 'Εικόνα', 'type' => 'image'],
            'sort' => ['label' => 'Σειρά', 'type' => 'number'],
            'status' => ['label' => 'Κατάσταση', 'type' => 'select', 'options' => ['active' => 'Ενεργή', 'paused' => 'Κρυφή']],
            'meta_title' => ['label' => 'SEO τίτλος', 'type' => 'text', 'group' => 'SEO'],
            'meta_description' => ['label' => 'SEO περιγραφή', 'type' => 'textarea', 'group' => 'SEO'],
        ],
    ];

    $campaignFields = [
        'title' => ['label' => 'Τίτλος', 'type' => 'text', 'required' => true],
        'slug' => ['label' => 'Slug', 'type' => 'slug', 'from' => 'title'],
        'kind' => ['label' => 'Τύπος', 'type' => 'select', 'options' => ['theme' => 'Θεματική', 'time' => 'Χρονική', 'region' => 'Ανά περιοχή']],
        'summary' => ['label' => 'Σύντομη περιγραφή', 'type' => 'text'],
        'description' => ['label' => 'Περιγραφή', 'type' => 'textarea'],
        'image' => ['label' => 'Εικόνα', 'type' => 'image'],
        'region' => ['label' => 'Περιοχή', 'type' => 'text'],
        'starts_at' => ['label' => 'Έναρξη', 'type' => 'datetime'],
        'ends_at' => ['label' => 'Λήξη', 'type' => 'datetime'],
        'services' => ['label' => 'Προσφορές της καμπάνιας', 'type' => 'multi', 'pivot' => 'campaign_services', 'local' => 'campaign_id', 'foreign' => 'service_id',
            'options' => function () use ($area, $b, $a) {
                $where = "s.status IN ('active','pending')";
                $p = [];
                if ($area === 'business') {
                    $where .= ' AND s.business_id = ?';
                    $p[] = $b['id'] ?? 0;
                } elseif ($area === 'agent') {
                    $where .= ' AND b.agent_id = ?';
                    $p[] = $a['id'] ?? 0;
                }
                $o = [];
                foreach (all("SELECT s.id, s.title, b.name FROM services s JOIN businesses b ON b.id = s.business_id WHERE $where ORDER BY b.name, s.title", $p) as $r) {
                    $o[$r['id']] = $r['name'] . ' · ' . $r['title'];
                }
                return $o;
            }],
    ];

    $serviceFields = [
        'title' => ['label' => 'Τίτλος υπηρεσίας', 'type' => 'text', 'required' => true],
        'slug' => ['label' => 'Slug', 'type' => 'slug', 'from' => 'title'],
        'category_id' => ['label' => 'Κατηγορία', 'type' => 'select', 'options' => 'opts_categories', 'required' => true],
        'summary' => ['label' => 'Σύντομη περιγραφή', 'type' => 'text', 'help' => 'Μία πρόταση, εμφανίζεται στις κάρτες και στα αποτελέσματα αναζήτησης.'],
        'description' => ['label' => 'Περιγραφή', 'type' => 'textarea'],
        'terms' => ['label' => 'Όροι εξαργύρωσης', 'type' => 'textarea', 'help' => 'Π.χ. «Απαιτείται ραντεβού», «Δεν ισχύει τα Σαββατοκύριακα».'],
        'image' => ['label' => 'Εικόνα', 'type' => 'image'],
        'regular_cents' => ['label' => 'Κανονική τιμή (€)', 'type' => 'money'],
        'price_cents' => ['label' => 'Τιμή προσφοράς (€)', 'type' => 'money', 'help' => '0 για δωρεάν κωδικό.'],
        'variants' => ['label' => 'Παραλλαγές (πακέτα, διάρκειες)', 'type' => 'variants', 'help' => 'Προαιρετικό. Αν υπάρχουν, οι τιμές τους αντικαθιστούν τις παραπάνω.'],
        'stores' => ['label' => 'Καταστήματα όπου εξαργυρώνεται', 'type' => 'multi', 'pivot' => 'service_stores', 'local' => 'service_id', 'foreign' => 'store_id',
            'options' => function () use ($area, $b) {
                $p = [];
                $w = '1=1';
                if ($area === 'business') {
                    $w = 'st.business_id = ?';
                    $p[] = $b['id'] ?? 0;
                }
                $o = [];
                foreach (all("SELECT st.id, st.name, st.city, bz.name AS bname FROM stores st JOIN businesses bz ON bz.id = st.business_id WHERE $w ORDER BY bz.name, st.name", $p) as $r) {
                    $o[$r['id']] = ($area === 'admin' ? $r['bname'] . ' · ' : '') . $r['name'] . ($r['city'] ? ' (' . $r['city'] . ')' : '');
                }
                return $o;
            }],
        'code_validity_days' => ['label' => 'Ισχύς κωδικού (ημέρες)', 'type' => 'number', 'default' => 30],
        'max_codes' => ['label' => 'Συνολικοί κωδικοί', 'type' => 'number', 'help' => 'Κενό για απεριόριστους.'],
        'per_user_limit' => ['label' => 'Κωδικοί ανά πελάτη', 'type' => 'number', 'default' => 1],
        'meta_title' => ['label' => 'SEO τίτλος', 'type' => 'text', 'group' => 'SEO'],
        'meta_description' => ['label' => 'SEO περιγραφή', 'type' => 'textarea', 'group' => 'SEO'],
    ];

    $storeFields = [
        'name' => ['label' => 'Όνομα καταστήματος', 'type' => 'text', 'required' => true],
        'address' => ['label' => 'Διεύθυνση', 'type' => 'text'],
        'city' => ['label' => 'Πόλη', 'type' => 'text'],
        'region' => ['label' => 'Περιοχή / Νομός', 'type' => 'text'],
        'postal_code' => ['label' => 'ΤΚ', 'type' => 'text'],
        'phone' => ['label' => 'Τηλέφωνο', 'type' => 'text'],
        'hours' => ['label' => 'Ωράριο', 'type' => 'textarea'],
        'lat' => ['label' => 'Γεωγρ. πλάτος', 'type' => 'text', 'group' => 'Χάρτης'],
        'lng' => ['label' => 'Γεωγρ. μήκος', 'type' => 'text', 'group' => 'Χάρτης'],
        'status' => ['label' => 'Κατάσταση', 'type' => 'select', 'options' => ['active' => 'Ενεργό', 'paused' => 'Κλειστό']],
    ];

    $businessFields = [
        'name' => ['label' => 'Επωνυμία (εμπορική)', 'type' => 'text', 'required' => true],
        'slug' => ['label' => 'Slug', 'type' => 'slug', 'from' => 'name'],
        'legal_name' => ['label' => 'Νομική επωνυμία', 'type' => 'text'],
        'vat_number' => ['label' => 'ΑΦΜ', 'type' => 'text'],
        'description' => ['label' => 'Περιγραφή', 'type' => 'textarea'],
        'logo' => ['label' => 'Λογότυπο', 'type' => 'image'],
        'cover' => ['label' => 'Εικόνα εξωφύλλου', 'type' => 'image'],
        'phone' => ['label' => 'Τηλέφωνο', 'type' => 'text'],
        'email' => ['label' => 'Email', 'type' => 'email'],
        'website' => ['label' => 'Ιστότοπος', 'type' => 'text'],
    ];

    if ($area === 'admin') {
        return [
            'categories' => $categories,
            'campaigns' => [
                'table' => 'campaigns', 'title' => 'Καμπάνιες', 'singular' => 'καμπάνια', 'order' => 'id DESC', 'search' => ['title'],
                'list' => ['title' => 'Τίτλος', 'kind' => 'Τύπος', 'owner_type' => 'Από', 'ends_at' => 'Λήξη', 'status' => 'Κατάσταση'],
                'fields' => $campaignFields + [
                    'featured' => ['label' => 'Προβολή στην αρχική', 'type' => 'bool'],
                    'status' => ['label' => 'Κατάσταση', 'type' => 'select', 'options' => ['draft' => 'Πρόχειρο', 'pending' => 'Σε έγκριση', 'active' => 'Ενεργή', 'ended' => 'Έληξε', 'rejected' => 'Απορρίφθηκε']],
                    'review_note' => ['label' => 'Σημείωση έγκρισης', 'type' => 'text'],
                    'meta_title' => ['label' => 'SEO τίτλος', 'type' => 'text', 'group' => 'SEO'],
                    'meta_description' => ['label' => 'SEO περιγραφή', 'type' => 'textarea', 'group' => 'SEO'],
                ],
            ],
            'businesses' => [
                'table' => 'businesses', 'title' => 'Επιχειρήσεις', 'singular' => 'επιχείρηση', 'order' => 'id DESC', 'search' => ['name', 'vat_number'],
                'list' => ['name' => 'Επωνυμία', 'vat_number' => 'ΑΦΜ', 'agent_id' => 'Αντιπρόσωπος', 'status' => 'Κατάσταση'],
                'fields' => $businessFields + [
                    'agent_id' => ['label' => 'Αντιπρόσωπος', 'type' => 'select', 'options' => 'opts_agents'],
                    'free_publish' => ['label' => 'Ελεύθερη δημοσίευση (χωρίς έγκριση admin)', 'type' => 'bool'],
                    'plan' => ['label' => 'Πακέτο / συνδρομή', 'type' => 'text'],
                    'status' => ['label' => 'Κατάσταση', 'type' => 'select', 'options' => ['pending' => 'Σε έγκριση', 'active' => 'Ενεργή', 'suspended' => 'Σε αναστολή', 'rejected' => 'Απορρίφθηκε']],
                ],
            ],
            'stores' => [
                'table' => 'stores', 'title' => 'Καταστήματα', 'singular' => 'κατάστημα', 'order' => 'id DESC', 'search' => ['name', 'city'],
                'list' => ['name' => 'Κατάστημα', 'business_id' => 'Επιχείρηση', 'city' => 'Πόλη', 'status' => 'Κατάσταση'],
                'fields' => ['business_id' => ['label' => 'Επιχείρηση', 'type' => 'select', 'options' => 'opts_businesses', 'required' => true]] + $storeFields,
            ],
            'services' => [
                'table' => 'services', 'title' => 'Προσφορές / υπηρεσίες', 'singular' => 'προσφορά', 'order' => 'id DESC', 'search' => ['title'],
                'list' => ['title' => 'Τίτλος', 'business_id' => 'Επιχείρηση', 'price_cents' => 'Τιμή', 'status' => 'Κατάσταση'],
                'fields' => ['business_id' => ['label' => 'Επιχείρηση', 'type' => 'select', 'options' => 'opts_businesses', 'required' => true]] + $serviceFields + [
                    'featured' => ['label' => 'Προβολή στην αρχική', 'type' => 'bool'],
                    'status' => ['label' => 'Κατάσταση', 'type' => 'select', 'options' => ['pending' => 'Σε έγκριση', 'active' => 'Ενεργή', 'paused' => 'Σε παύση', 'rejected' => 'Απορρίφθηκε']],
                    'review_note' => ['label' => 'Σημείωση έγκρισης', 'type' => 'text'],
                ],
            ],
            'users' => [
                'table' => 'users', 'title' => 'Χρήστες', 'singular' => 'χρήστης', 'order' => 'id DESC', 'search' => ['name', 'email'],
                'list' => ['name' => 'Όνομα', 'email' => 'Email', 'role' => 'Ρόλος', 'status' => 'Κατάσταση', 'created_at' => 'Εγγραφή'],
                'fields' => [
                    'name' => ['label' => 'Όνομα', 'type' => 'text', 'required' => true],
                    'email' => ['label' => 'Email', 'type' => 'email', 'required' => true, 'unique' => true],
                    'phone' => ['label' => 'Τηλέφωνο', 'type' => 'text'],
                    'role' => ['label' => 'Ρόλος', 'type' => 'select', 'options' => ROLES],
                    'status' => ['label' => 'Κατάσταση', 'type' => 'select', 'options' => ['active' => 'Ενεργός', 'suspended' => 'Σε αναστολή']],
                    'password' => ['label' => 'Νέος κωδικός πρόσβασης', 'type' => 'password', 'help' => 'Αφήστε κενό για να μείνει ο ίδιος. Τουλάχιστον 10 χαρακτήρες.'],
                ],
                'no_delete_self' => true,
            ],
            'members' => [
                'table' => 'business_users', 'title' => 'Μέλη επιχειρήσεων', 'singular' => 'σύνδεση χρήστη με επιχείρηση', 'order' => 'id DESC',
                'list' => ['business_id' => 'Επιχείρηση', 'user_id' => 'Χρήστης', 'role' => 'Ρόλος'],
                'fields' => [
                    'business_id' => ['label' => 'Επιχείρηση', 'type' => 'select', 'options' => 'opts_businesses', 'required' => true],
                    'user_id' => ['label' => 'Χρήστης', 'type' => 'select', 'required' => true, 'options' => function () {
                        $o = [];
                        foreach (all("SELECT id, name, email FROM users WHERE role IN ('merchant','staff') ORDER BY name") as $r) {
                            $o[$r['id']] = $r['name'] . ' · ' . $r['email'];
                        }
                        return $o;
                    }],
                    'role' => ['label' => 'Ρόλος', 'type' => 'select', 'options' => ['owner' => 'Ιδιοκτήτης', 'staff' => 'Υπάλληλος']],
                ],
            ],
            'agents' => [
                'table' => 'agents', 'title' => 'Αντιπρόσωποι', 'singular' => 'αντιπρόσωπος', 'order' => 'id DESC',
                'list' => ['user_id' => 'Χρήστης', 'region' => 'Περιοχή', 'commission_percent' => 'Ποσοστό %', 'commission_fixed_cents' => 'Σταθερό', 'affiliate_code' => 'Κωδ. affiliate', 'status' => 'Κατάσταση'],
                'fields' => [
                    'user_id' => ['label' => 'Χρήστης (ρόλος: αντιπρόσωπος)', 'type' => 'select', 'required' => true, 'options' => function () {
                        $o = [];
                        foreach (all("SELECT id, name, email FROM users WHERE role = 'agent' ORDER BY name") as $r) {
                            $o[$r['id']] = $r['name'] . ' · ' . $r['email'];
                        }
                        return $o;
                    }],
                    'parent_agent_id' => ['label' => 'Ανήκει στον αντιπρόσωπο', 'type' => 'select', 'options' => 'opts_agents'],
                    'region' => ['label' => 'Περιοχή ευθύνης', 'type' => 'text'],
                    'commission_percent' => ['label' => 'Προμήθεια (% επί της πληρωμής)', 'type' => 'number'],
                    'commission_fixed_cents' => ['label' => 'Σταθερή προμήθεια ανά εξαργύρωση (€)', 'type' => 'money'],
                    'status' => ['label' => 'Κατάσταση', 'type' => 'select', 'options' => ['active' => 'Ενεργός', 'suspended' => 'Σε αναστολή']],
                    'notes' => ['label' => 'Σημειώσεις', 'type' => 'textarea'],
                ],
            ],
        ];
    }

    if ($area === 'business' && $b) {
        $pub = (int)$b['free_publish'] === 1;
        $statusOnSave = $pub ? null : 'pending';
        return [
            'services' => [
                'table' => 'services', 'title' => 'Οι προσφορές μου', 'singular' => 'προσφορά', 'order' => 'id DESC', 'search' => ['title'],
                'scope' => ['business_id' => (int)$b['id']], 'force_on_save' => $statusOnSave ? ['status' => $statusOnSave] : [],
                'list' => ['title' => 'Τίτλος', 'price_cents' => 'Τιμή', 'status' => 'Κατάσταση', 'review_note' => 'Σημείωση'],
                'fields' => $serviceFields + ($pub ? ['status' => ['label' => 'Κατάσταση', 'type' => 'select', 'options' => ['active' => 'Ενεργή', 'paused' => 'Σε παύση']]] : []),
                'notice' => $pub ? null : 'Κάθε νέα προσφορά ή αλλαγή πηγαίνει για έγκριση από την ομάδα της πλατφόρμας πριν δημοσιευτεί.',
            ],
            'stores' => [
                'table' => 'stores', 'title' => 'Καταστήματα', 'singular' => 'κατάστημα', 'order' => 'name',
                'scope' => ['business_id' => (int)$b['id']],
                'list' => ['name' => 'Κατάστημα', 'city' => 'Πόλη', 'phone' => 'Τηλέφωνο', 'status' => 'Κατάσταση'],
                'fields' => $storeFields,
            ],
            'campaigns' => [
                'table' => 'campaigns', 'title' => 'Καμπάνιες', 'singular' => 'καμπάνια', 'order' => 'id DESC',
                'scope' => ['business_id' => (int)$b['id'], 'owner_type' => 'business'], 'force_on_save' => ['status' => $pub ? 'active' : 'pending'],
                'list' => ['title' => 'Τίτλος', 'ends_at' => 'Λήξη', 'status' => 'Κατάσταση', 'review_note' => 'Σημείωση'],
                'fields' => $campaignFields,
                'notice' => $pub ? null : 'Οι καμπάνιες σου δημοσιεύονται μετά από έγκριση.',
            ],
            'profile' => [
                'table' => 'businesses', 'title' => 'Στοιχεία επιχείρησης', 'singular' => 'στοιχεία', 'single_id' => (int)$b['id'],
                'list' => ['name' => 'Επωνυμία'], 'fields' => $businessFields, 'no_create' => true, 'no_delete' => true,
            ],
        ];
    }

    if ($area === 'agent' && $a) {
        return [
            'businesses' => [
                'table' => 'businesses', 'title' => 'Οι επιχειρήσεις μου', 'singular' => 'επιχείρηση', 'order' => 'name',
                'scope' => ['agent_id' => (int)$a['id']], 'no_create' => true, 'no_delete' => true,
                'list' => ['name' => 'Επωνυμία', 'vat_number' => 'ΑΦΜ', 'phone' => 'Τηλέφωνο', 'status' => 'Κατάσταση'],
                'fields' => $businessFields,
            ],
            'campaigns' => [
                'table' => 'campaigns', 'title' => 'Καμπάνιες', 'singular' => 'καμπάνια', 'order' => 'id DESC',
                'scope' => ['agent_id' => (int)$a['id'], 'owner_type' => 'agent'], 'force_on_save' => ['status' => 'pending'],
                'list' => ['title' => 'Τίτλος', 'ends_at' => 'Λήξη', 'status' => 'Κατάσταση', 'review_note' => 'Σημείωση'],
                'fields' => $campaignFields,
                'notice' => 'Οι καμπάνιες σου δημοσιεύονται μετά από έγκριση.',
            ],
        ];
    }
    return [];
}

function crud_guard(string $area): void
{
    match ($area) {
        'admin' => require_role('admin'),
        'business' => require_role('merchant'),
        'agent' => require_role('agent'),
        default => abort(404),
    };
}

function crud_def(string $area, string $entity): array
{
    crud_guard($area);
    $defs = crud_defs($area);
    if (!isset($defs[$entity])) {
        abort(404);
    }
    $d = $defs[$entity];
    $d['entity'] = $entity;
    $d['area'] = $area;
    $d['base'] = '/' . $area . '/c/' . $entity;
    return $d;
}

function crud_options($o): array
{
    return is_callable($o) ? $o() : $o;
}

function crud_find(array $d, int $id): ?array
{
    if (!empty($d['single_id']) && $id !== $d['single_id']) {
        return null;
    }
    $w = ['id = ?'];
    $p = [$id];
    foreach ($d['scope'] ?? [] as $k => $v) {
        $w[] = ident($k) . ' = ?';
        $p[] = $v;
    }
    return one('SELECT * FROM ' . ident($d['table']) . ' WHERE ' . implode(' AND ', $w), $p);
}

function crud_display(array $d, string $col, array $row): string
{
    $v = $row[$col] ?? null;
    $f = $d['fields'][$col] ?? null;
    if ($col === 'status' || $col === 'kind' || $col === 'owner_type') {
        $labels = STATUS_OPTIONS + ['theme' => 'Θεματική', 'time' => 'Χρονική', 'region' => 'Περιοχή', 'platform' => 'Πλατφόρμα', 'business' => 'Επιχείρηση', 'agent' => 'Αντιπρόσωπος'];
        return '<span class="badge badge-' . e($v) . '">' . e($labels[$v] ?? $v) . '</span>';
    }
    if (str_ends_with($col, '_cents')) {
        return e(money((int)$v));
    }
    if (str_ends_with($col, '_at')) {
        return e(date_gr($v, true));
    }
    if ($col === 'role') {
        return e(ROLES[$v] ?? (['owner' => 'Ιδιοκτήτης', 'staff' => 'Υπάλληλος'][$v] ?? $v));
    }
    if ($col === 'business_id') {
        return e((string)val('SELECT name FROM businesses WHERE id = ?', [$v]));
    }
    if ($col === 'user_id') {
        return e((string)val('SELECT name FROM users WHERE id = ?', [$v]));
    }
    if ($col === 'agent_id') {
        return $v ? e((string)val('SELECT u.name FROM agents a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$v])) : '—';
    }
    if ($f && $f['type'] === 'select') {
        $o = crud_options($f['options']);
        return e($o[$v] ?? $v);
    }
    return e(mb_strimwidth((string)$v, 0, 60, '…'));
}

/** Validates POST input for a definition. Returns [data, errors, pivots, variants]. */
function crud_collect(array $d, ?array $row): array
{
    $data = [];
    $errors = [];
    $pivots = [];
    $variants = null;
    foreach ($d['fields'] as $name => $f) {
        $raw = $_POST[$name] ?? null;
        switch ($f['type']) {
            case 'bool':
                $data[$name] = !empty($raw) ? 1 : 0;
                break;
            case 'number':
                $data[$name] = ($raw === '' || $raw === null) ? ($f['default'] ?? null) : (int)$raw;
                break;
            case 'money':
                $data[$name] = to_cents($raw ?? 0);
                if ($data[$name] < 0) {
                    $errors[$name] = 'Το ποσό δεν μπορεί να είναι αρνητικό.';
                }
                break;
            case 'datetime':
                $data[$name] = $raw ? date('Y-m-d H:i:s', strtotime((string)$raw) ?: time()) : null;
                break;
            case 'select':
                $raw = is_string($raw) ? $raw : '';
                $opts = crud_options($f['options']);
                if ($raw !== '' && !array_key_exists($raw, $opts)) {
                    $errors[$name] = 'Μη έγκυρη επιλογή.';
                }
                $data[$name] = $raw === '' ? null : $raw;
                break;
            case 'multi':
                $opts = crud_options($f['options']);
                $vals = array_values(array_filter(array_map('intval', (array)($raw ?? [])), fn($v) => array_key_exists($v, $opts)));
                $pivots[$name] = $vals;
                break;
            case 'variants':
                $variants = [];
                foreach ((array)($_POST['variant_label'] ?? []) as $i => $label) {
                    $label = trim((string)$label);
                    if ($label === '') {
                        continue;
                    }
                    $variants[] = ['label' => mb_substr($label, 0, 120), 'regular_cents' => to_cents($_POST['variant_regular'][$i] ?? 0), 'price_cents' => to_cents($_POST['variant_price'][$i] ?? 0), 'sort' => $i];
                }
                break;
            case 'image':
                if (!empty($_FILES[$name]['name'])) {
                    try {
                        $data[$name] = store_image($_FILES[$name], (string)($_POST['title'] ?? $_POST['name'] ?? $name));
                    } catch (RuntimeException $ex) {
                        $errors[$name] = $ex->getMessage();
                    }
                } elseif (!empty($_POST[$name . '__remove'])) {
                    $data[$name] = null;
                }
                break;
            case 'password':
                if ($raw !== null && $raw !== '') {
                    if ($err = validate_password((string)$raw)) {
                        $errors[$name] = $err;
                    } else {
                        $data['password_hash'] = password_hash((string)$raw, PASSWORD_DEFAULT);
                    }
                } elseif (!$row) {
                    $errors[$name] = 'Ορίστε κωδικό πρόσβασης για τον νέο χρήστη.';
                }
                break;
            case 'email':
                $data[$name] = mb_strtolower(trim((string)$raw));
                if ($data[$name] !== '' && !filter_var($data[$name], FILTER_VALIDATE_EMAIL)) {
                    $errors[$name] = 'Μη έγκυρο email.';
                }
                break;
            case 'slug':
                $data[$name] = trim((string)$raw);
                break;
            default:
                $data[$name] = is_string($raw) ? trim($raw) : null;
                if (isset($f['max']) && mb_strlen((string)$data[$name]) > $f['max']) {
                    $errors[$name] = 'Πολύ μεγάλο κείμενο.';
                }
        }
        if (!empty($f['required']) && !in_array($f['type'], ['image', 'multi', 'variants', 'password', 'slug'], true)) {
            $v = $data[$name] ?? null;
            if (($v === null || $v === '') && !isset($errors[$name])) {
                $errors[$name] = 'Υποχρεωτικό πεδίο.';
            }
        }
        if (!empty($f['unique']) && !empty($data[$name])) {
            $dupe = one('SELECT id FROM ' . ident($d['table']) . ' WHERE ' . ident($name) . ' = ?', [$data[$name]]);
            if ($dupe && (!$row || (int)$dupe['id'] !== (int)$row['id'])) {
                $errors[$name] = 'Υπάρχει ήδη.';
            }
        }
    }
    // slugs
    foreach ($d['fields'] as $name => $f) {
        if ($f['type'] === 'slug') {
            $base = $data[$name] ?: ($data[$f['from']] ?? '');
            $data[$name] = unique_slug($d['table'], (string)$base, $row ? (int)$row['id'] : null);
        }
    }
    if (isset($data['regular_cents'], $data['price_cents']) && $data['regular_cents'] > 0 && $data['price_cents'] > $data['regular_cents']) {
        $errors['price_cents'] = 'Η τιμή προσφοράς δεν μπορεί να είναι μεγαλύτερη από την κανονική.';
    }
    if (!empty($data['starts_at']) && !empty($data['ends_at']) && $data['ends_at'] < $data['starts_at']) {
        $errors['ends_at'] = 'Η λήξη πρέπει να είναι μετά την έναρξη.';
    }
    unset($data['password']);
    return [$data, $errors, $pivots, $variants];
}

function crud_save(array $d, ?array $row): array
{
    [$data, $errors, $pivots, $variants] = crud_collect($d, $row);
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }
    foreach ($d['scope'] ?? [] as $k => $v) {
        $data[$k] = $v;
    }
    foreach ($d['force_on_save'] ?? [] as $k => $v) {
        $data[$k] = $v;
    }
    $cols = columns_of($d['table']);
    $data = array_intersect_key($data, array_flip($cols));
    if (in_array('updated_at', $cols, true)) {
        $data['updated_at'] = now();
    }
    $id = tx(function () use ($d, $row, $data, $pivots, $variants, $cols) {
        if ($row) {
            update($d['table'], $data, ['id' => $row['id']]);
            $id = (int)$row['id'];
        } else {
            if (in_array('created_at', $cols, true)) {
                $data['created_at'] = now();
            }
            if ($d['table'] === 'campaigns' && empty($data['owner_type'])) {
                $data['owner_type'] = 'platform';
                $data['created_by'] = current_user()['id'];
            }
            if ($d['table'] === 'agents' && empty($data['affiliate_code'])) {
                $data['affiliate_code'] = new_affiliate_code();
            }
            $id = insert($d['table'], $data);
        }
        foreach ($pivots as $name => $vals) {
            $f = $d['fields'][$name];
            q('DELETE FROM ' . ident($f['pivot']) . ' WHERE ' . ident($f['local']) . ' = ?', [$id]);
            foreach (array_unique($vals) as $v) {
                insert($f['pivot'], [$f['local'] => $id, $f['foreign'] => $v]);
            }
        }
        if ($variants !== null) {
            q('DELETE FROM service_variants WHERE service_id = ?', [$id]);
            foreach ($variants as $v) {
                insert('service_variants', $v + ['service_id' => $id]);
            }
        }
        return $id;
    });
    audit(($row ? 'update' : 'create'), $d['table'], $id);
    return ['ok' => true, 'id' => $id];
}

function columns_of(string $table): array
{
    static $cache = [];
    if (!isset($cache[$table])) {
        if (is_mysql()) {
            $cache[$table] = array_column(all('SHOW COLUMNS FROM ' . ident($table)), 'Field');
        } else {
            $cache[$table] = array_column(all('PRAGMA table_info(' . ident($table) . ')'), 'name');
        }
    }
    return $cache[$table];
}

function crud_pivot_values(array $f, int $id): array
{
    return array_map('intval', array_column(all('SELECT ' . ident($f['foreign']) . ' AS v FROM ' . ident($f['pivot']) . ' WHERE ' . ident($f['local']) . ' = ?', [$id]), 'v'));
}

// ---------- route handlers ----------
function crud_index(array $p): void
{
    $d = crud_def($p['area'], $p['entity']);
    if (!empty($d['single_id'])) {
        redirect(url($d['base'] . '/' . $d['single_id']));
    }
    $w = ['1=1'];
    $params = [];
    foreach ($d['scope'] ?? [] as $k => $v) {
        $w[] = ident($k) . ' = ?';
        $params[] = $v;
    }
    $qs = trim((string)input('q'));
    if ($qs !== '' && !empty($d['search'])) {
        $or = [];
        foreach ($d['search'] as $c) {
            $or[] = ident($c) . ' LIKE ?';
            $params[] = '%' . $qs . '%';
        }
        $w[] = '(' . implode(' OR ', $or) . ')';
    }
    $status = (string)input('status');
    if ($status !== '' && in_array('status', columns_of($d['table']), true)) {
        $w[] = 'status = ?';
        $params[] = $status;
    }
    $where = implode(' AND ', $w);
    $total = (int)val('SELECT COUNT(*) FROM ' . ident($d['table']) . " WHERE $where", $params);
    $pg = paginate($total, 30, (int)input('page', 1));
    $rows = all('SELECT * FROM ' . ident($d['table']) . " WHERE $where ORDER BY " . $d['order'] . ' LIMIT ' . $pg['limit'] . ' OFFSET ' . $pg['offset'], $params);
    render('panel/crud_list', ['d' => $d, 'rows' => $rows, 'pg' => $pg, 'title' => $d['title'], 'noindex' => true, 'area' => $p['area']], 'panel/layout');
}

function crud_new(array $p): void
{
    $d = crud_def($p['area'], $p['entity']);
    if (!empty($d['no_create'])) {
        abort(404);
    }
    render('panel/crud_form', ['d' => $d, 'row' => null, 'errors' => [], 'title' => 'Νέα εγγραφή · ' . $d['title'], 'noindex' => true, 'area' => $p['area']], 'panel/layout');
}

function crud_edit(array $p): void
{
    if (!ctype_digit($p['id'])) {
        abort(404);
    }
    $d = crud_def($p['area'], $p['entity']);
    $row = crud_find($d, (int)$p['id']);
    if (!$row) {
        abort(404);
    }
    render('panel/crud_form', ['d' => $d, 'row' => $row, 'errors' => [], 'title' => 'Επεξεργασία · ' . $d['title'], 'noindex' => true, 'area' => $p['area']], 'panel/layout');
}

function crud_save_action(array $p): void
{
    $d = crud_def($p['area'], $p['entity']);
    $id = (int)input('id', 0);
    $row = $id ? crud_find($d, $id) : null;
    if ($id && !$row) {
        abort(404);
    }
    if (!$row && !empty($d['no_create'])) {
        abort(403);
    }
    $r = crud_save($d, $row);
    if (!$r['ok']) {
        $_SESSION['old_view'] = $_POST;
        render('panel/crud_form', ['d' => $d, 'row' => $row ?? ['id' => null], 'errors' => $r['errors'], 'title' => $d['title'], 'noindex' => true, 'area' => $p['area']], 'panel/layout', 422);
    }
    $msg = 'Αποθηκεύτηκε.';
    if (!empty($d['force_on_save']['status']) && $d['force_on_save']['status'] === 'pending') {
        $msg = 'Αποθηκεύτηκε και στάλθηκε για έγκριση.';
    }
    flash('ok', $msg);
    redirect(url($d['base'] . '/' . $r['id']));
}

function crud_delete_action(array $p): void
{
    if (!ctype_digit($p['id'])) {
        abort(404);
    }
    $d = crud_def($p['area'], $p['entity']);
    if (!empty($d['no_delete'])) {
        abort(403);
    }
    $row = crud_find($d, (int)$p['id']);
    if (!$row) {
        abort(404);
    }
    if (!empty($d['no_delete_self']) && (int)$row['id'] === (int)current_user()['id']) {
        flash('err', 'Δεν μπορείς να διαγράψεις τον δικό σου λογαριασμό.');
        redirect(url($d['base']));
    }
    if (is_post()) {
        if (input('confirm') !== 'yes') {
            flash('err', 'Επιβεβαίωσε τη διαγραφή τσεκάροντας το κουτί.');
            redirect(url($d['base'] . '/' . $row['id'] . '/delete'));
        }
        tx(function () use ($d, $row) {
            q('DELETE FROM ' . ident($d['table']) . ' WHERE id = ?', [$row['id']]);
            foreach ($d['fields'] as $f) {
                if ($f['type'] === 'multi') {
                    q('DELETE FROM ' . ident($f['pivot']) . ' WHERE ' . ident($f['local']) . ' = ?', [$row['id']]);
                }
                if ($f['type'] === 'variants') {
                    q('DELETE FROM service_variants WHERE service_id = ?', [$row['id']]);
                }
            }
        });
        audit('delete', $d['table'], (int)$row['id']);
        flash('ok', 'Διαγράφηκε.');
        redirect(url($d['base']));
    }
    $label = $row['name'] ?? $row['title'] ?? ('#' . $row['id']);
    render('panel/confirm_delete', ['d' => $d, 'row' => $row, 'label' => $label, 'title' => 'Διαγραφή', 'noindex' => true, 'area' => $p['area']], 'panel/layout');
}
