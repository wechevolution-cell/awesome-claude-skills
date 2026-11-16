<?php
/** Public + account layout (app shell). */
$title = $title ?? site_name();
$description = $description ?? (string)setting('site_tagline');
$canonical = isset($canonical) ? abs_url($canonical) : null;
$noindex = !empty($noindex) || setting('noindex_site') === '1';
$ogImage = !empty($og_image) ? abs_url($og_image) : abs_url('/icon-512.png');
$path = request_path();
$u = current_user();
$cartCount = cart_count();
$mood = (string)setting('default_mood', 'auto');
$ticker = array_values(array_filter(array_map('trim', explode("\n", (string)setting('ticker')))));
$dockItems = [['/', 'Αρχική', 'home'], ['/prosfores', 'Προσφορές', 'tag'], ['/logariasmos/kodikoi', 'Κωδικοί', 'qr'], [$u ? '/logariasmos' : '/eisodos', $u ? 'Προφίλ' : 'Είσοδος', 'user']];
?><!doctype html>
<html lang="el" <?= $mood !== 'auto' ? 'data-mood="' . e($mood) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php else: ?><meta name="robots" content="index, follow, max-image-preview:large"><?php endif ?>
<?php if ($canonical): ?><link rel="canonical" href="<?= e($canonical) ?>">
<link rel="alternate" hreflang="el-GR" href="<?= e($canonical) ?>"><link rel="alternate" hreflang="x-default" href="<?= e($canonical) ?>"><?php endif ?>
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:locale" content="el_GR">
<meta property="og:type" content="<?= e($og_type ?? 'website') ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<?php if ($canonical): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#140A1E">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(site_name()) ?>">
<?php if (setting('gsc_verification')): ?><meta name="google-site-verification" content="<?= e(setting('gsc_verification')) ?>"><?php endif ?>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="/icon-180.png">
<link rel="alternate" type="text/plain" href="/llms.txt" title="llms.txt">
<link rel="preload" href="/assets/fonts/noto-sans-display-greek-wdth-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/noto-sans-greek-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script>
(function(){try{var m=localStorage.getItem('mood');if(m==='day'||m==='night')document.documentElement.setAttribute('data-mood',m);else if(m==='auto')document.documentElement.removeAttribute('data-mood');}catch(e){}})();
</script>
<?= !empty($jsonld) ? jsonld($jsonld) : jsonld([]) ?>
</head>
<body>
<a class="skip" href="#main">Μετάβαση στο περιεχόμενο</a>
<?php if ($ticker): ?>
<div class="ticker" aria-hidden="true"><div><?php for ($r = 0; $r < 2; $r++) foreach ($ticker as $t): ?><span><?= e($t) ?></span><?php endforeach ?></div></div>
<?php endif ?>
<header class="top wrap">
  <div class="inner head">
    <a class="logo" href="/" aria-label="<?= e(site_name()) ?> · αρχική"><span class="dot" aria-hidden="true"></span><span><?= e(site_name()) ?></span></a>
    <nav class="nav" aria-label="Κύριο μενού">
      <a class="hot" href="/prosfores">Προσφορές</a>
      <a class="opt" href="/kampanies">Καμπάνιες</a>
      <a class="opt" href="/katigories">Κατηγορίες</a>
      <a class="opt" href="/synergates">Για επιχειρήσεις</a>
      <a class="icon-btn opt" href="/anazitisi" aria-label="Αναζήτηση"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></a>
      <button class="icon-btn" type="button" data-mood-toggle aria-label="Αλλαγή εμφάνισης: ημέρα ή νύχτα"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
      <?php if ($u): ?>
        <a class="icon-btn opt" href="<?= e(home_for($u)) ?>" aria-label="Ο λογαριασμός μου"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg></a>
      <?php else: ?>
        <a class="opt" href="/eisodos">Είσοδος</a>
      <?php endif ?>
      <a class="bag" href="/kalathi" data-cart-open><span class="lbl">Καλάθι</span> <b data-cart-count><?= $cartCount ?></b></a>
    </nav>
  </div>
  <?php if ($flashes = take_flash()): ?>
    <div class="inner flash-stack" role="status"><?php foreach ($flashes as $f): ?><div class="alert <?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach ?></div>
  <?php endif ?>
</header>

<main id="main" class="wrap"><div class="inner">
<?= $content ?>
</div></main>

<footer class="foot wrap">
  <div class="inner">
    <div class="disp big"><?= e(site_name()) ?><span class="foil">.</span></div>
    <div class="foot-cols">
      <div>
        <p class="muted"><?= e(setting('site_tagline')) ?></p>
        <?php if (setting('contact_email')): ?><p class="small">Email: <?= e(setting('contact_email')) ?></p><?php endif ?>
        <?php if (setting('contact_phone')): ?><p class="small">Τηλέφωνο: <?= e(setting('contact_phone')) ?></p><?php endif ?>
        <?php if (setting('company_legal_name')): ?><p class="small"><?= e(setting('company_legal_name')) ?><?= setting('company_vat') ? ' · ΑΦΜ ' . e(setting('company_vat')) : '' ?></p><?php endif ?>
      </div>
      <div><h3>Πλατφόρμα</h3><ul><li><a href="/prosfores">Προσφορές</a></li><li><a href="/kampanies">Καμπάνιες</a></li><li><a href="/katigories">Κατηγορίες</a></li><li><a href="/pos-leitourgei">Πώς λειτουργεί</a></li><li><a href="/syxnes-erotiseis">Συχνές ερωτήσεις</a></li></ul></div>
      <div><h3>Συνεργάτες</h3><ul><li><a href="/synergates">Για επιχειρήσεις</a></li><li><a href="/business/redeem">Εξαργύρωση κωδικού</a></li><li><a href="/eisodos">Είσοδος συνεργατών</a></li><li><a href="/epikoinonia">Επικοινωνία</a></li></ul></div>
      <div><h3>Νομικά</h3><ul><li><a href="/oroi">Όροι χρήσης</a></li><li><a href="/aporrito">Απόρρητο</a></li><li><a href="/cookies">Cookies</a></li><li><button class="link" type="button" data-cookie-reopen style="background:none;border:0;padding:0;font:inherit;cursor:pointer">Ρυθμίσεις cookies</button></li></ul></div>
    </div>
  </div>
</footer>

<nav class="dock" aria-label="Γρήγορη πλοήγηση">
<?php foreach ($dockItems as [$href, $label, $icon]): $cur = $href === '/' ? $path === '/' : str_starts_with($path, $href); ?>
  <a href="<?= e($href) ?>" <?= $cur ? 'aria-current="page"' : '' ?>><?php partial('icon', ['name' => $icon]) ?><?= e($label) ?></a>
<?php endforeach ?>
</nav>

<div class="scrim" data-scrim></div>
<aside class="drawer" id="cart-drawer" aria-label="Καλάθι" aria-hidden="true" tabindex="-1">
  <div class="row between"><h2 class="disp">Καλάθι</h2><button class="icon-btn" type="button" data-cart-close aria-label="Κλείσιμο καλαθιού">✕</button></div>
  <ul data-cart-lines><li class="muted">Φόρτωση…</li></ul>
  <div class="totals"><div class="save" data-cart-save hidden><span>Εξοικονομείς</span><span></span></div><div class="grand"><span>Σύνολο</span><span data-cart-total>—</span></div></div>
  <a class="btn gold block" href="/tameio">Ολοκλήρωση →</a>
  <p class="small muted" style="margin-top:10px">Οι δωρεάν κωδικοί εκδίδονται αμέσως. Για τους επί πληρωμή ακολουθεί ένα βήμα πληρωμής.</p>
</aside>
<div class="toast" role="status" aria-live="polite" data-toast></div>

<div class="cookie" data-cookie hidden role="dialog" aria-label="Cookies">
  <p style="margin:0 0 12px">Χρησιμοποιούμε απαραίτητα cookies για τη λειτουργία της σελίδας. Με την άδειά σου χρησιμοποιούμε και cookies στατιστικών (Google Analytics). <a class="link" href="/cookies">Περισσότερα</a></p>
  <div class="row"><button class="btn sm" type="button" data-cookie-accept>Αποδοχή</button><button class="btn sm ghost" type="button" data-cookie-reject>Μόνο απαραίτητα</button></div>
</div>

<script>window.APP={csrf:<?= json_encode(csrf_token()) ?>,ga:<?= json_encode((string)setting('ga4_id')) ?>,loggedIn:<?= $u ? 'true' : 'false' ?>};</script>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?><script src="<?= asset($s) ?>" defer></script><?php endforeach ?>
</body>
</html>
