<?php
declare(strict_types=1);

/** Defaults. Everything here can be changed from Admin → Ρυθμίσεις. */
function setting_defaults(): array
{
    return [
        'site_name'          => 'Brand',
        'site_tagline'       => 'Προσφορές από καταστήματα σε όλη την Ελλάδα',
        'hero_line_1'        => 'Πολυτέλεια',
        'hero_line_2'        => 'σε τιμή',
        'hero_line_3'        => 'προσφοράς.',
        'hero_text'          => 'Περιποίηση, γεύσεις και εμπειρίες από καταστήματα της περιοχής σου, με κωδικό προσφοράς. Τον παίρνεις εδώ, τον δείχνεις στο ταμείο.',
        'ticker'             => "Νέες προσφορές κάθε εβδομάδα\nΔωρεάν και επί πληρωμή κωδικοί\nΕξαργύρωση με QR στο ταμείο\nΚαταστήματα σε όλη την Ελλάδα",
        'default_mood'       => 'auto',            // auto | night | day
        'contact_email'      => '',
        'contact_phone'      => '',
        'contact_address'    => '',
        'company_legal_name' => '',
        'company_vat'        => '',
        'social_facebook'    => '',
        'social_instagram'   => '',
        'social_tiktok'      => '',
        'social_linkedin'    => '',
        'social_youtube'     => '',
        'ga4_id'             => '',
        'gsc_verification'   => '',
        'code_prefix'        => 'CD',
        'default_validity'   => '30',
        'fee_percent'        => '0',              // platform fee per paid item (percent)
        'fee_fixed_cents'    => '0',              // platform fee per redemption (cents)
        'payments_mode'      => 'test',           // test | live
        'payments_provider'  => 'test',
        'allow_registrations'=> '1',
        'business_signup'    => '1',
        'ai_bots_allowed'    => '1',
        'noindex_site'       => '1',              // keep the demo out of search engines until launch
        'demo_loaded'        => '0',
    ];
}

function settings_all(): array
{
    static $cache = null;
    if ($cache === null || isset($GLOBALS['__settings_dirty'])) {
        unset($GLOBALS['__settings_dirty']);
        $cache = setting_defaults();
        foreach (all('SELECT k, v FROM settings') as $r) {
            $cache[$r['k']] = $r['v'];
        }
    }
    return $cache;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return $all[$key] ?? $default;
}

function set_setting(string $key, $value): void
{
    $value = (string)$value;
    if (one('SELECT k FROM settings WHERE k = ?', [$key])) {
        q('UPDATE settings SET v = ? WHERE k = ?', [$value, $key]);
    } else {
        insert('settings', ['k' => $key, 'v' => $value]);
    }
    $GLOBALS['__settings_dirty'] = true;
}

function site_name(): string
{
    return (string)setting('site_name', 'Brand');
}
