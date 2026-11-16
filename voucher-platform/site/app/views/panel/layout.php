<?php
$u = current_user();
$path = request_path();
$area = $area ?? 'admin';
$menus = [
    'admin' => [
        ['Πίνακας ελέγχου', '/admin'], ['Εγκρίσεις', '/admin/approvals', (int)val("SELECT (SELECT COUNT(*) FROM services WHERE status='pending') + (SELECT COUNT(*) FROM businesses WHERE status='pending') + (SELECT COUNT(*) FROM campaigns WHERE status='pending')")],
        'Περιεχόμενο', ['Καμπάνιες', '/admin/c/campaigns'], ['Προσφορές', '/admin/c/services'], ['Κατηγορίες', '/admin/c/categories'],
        'Συνεργάτες', ['Επιχειρήσεις', '/admin/c/businesses'], ['Καταστήματα', '/admin/c/stores'], ['Μέλη επιχειρήσεων', '/admin/c/members'], ['Αντιπρόσωποι', '/admin/c/agents'], ['Προμήθειες', '/admin/commissions'],
        'Πωλήσεις', ['Παραγγελίες', '/admin/orders'], ['Κωδικοί', '/admin/codes'],
        'Σύστημα', ['Χρήστες', '/admin/c/users'], ['Μηνύματα', '/admin/messages', (int)val('SELECT COUNT(*) FROM messages WHERE is_read = 0')], ['Ρυθμίσεις', '/admin/settings'], ['Δεδομένα επίδειξης', '/admin/demo'],
    ],
    'business' => ($u && $u['role'] === 'staff') ? [['Εξαργύρωση', '/business/redeem'], ['Εξαργυρώσεις', '/business/redemptions']] : [
        ['Πίνακας', '/business'], ['Εξαργύρωση', '/business/redeem'], ['Εξαργυρώσεις', '/business/redemptions'],
        'Κατάλογος', ['Προσφορές', '/business/c/services'], ['Καμπάνιες', '/business/c/campaigns'], ['Καταστήματα', '/business/c/stores'],
        'Ομάδα', ['Προσωπικό', '/business/staff'], ['Στοιχεία επιχείρησης', '/business/c/profile'],
    ],
    'agent' => [['Πίνακας', '/agent'], ['Νέα επιχείρηση', '/agent/new-business'], ['Οι επιχειρήσεις μου', '/agent/c/businesses'], ['Καμπάνιες', '/agent/c/campaigns'], ['Προμήθειες', '/agent/commissions']],
];
$areaName = ['admin' => 'Διαχείριση', 'business' => 'Επιχείρηση', 'agent' => 'Αντιπρόσωπος'][$area];
$mood = (string)setting('default_mood', 'auto');
?><!doctype html>
<html lang="el" <?= $mood !== 'auto' ? 'data-mood="' . e($mood) . '"' : '' ?>>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? $areaName) ?> · <?= e(site_name()) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#140A1E">
<link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/icon-192.png">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>"><link rel="stylesheet" href="<?= asset('css/app.css') ?>"><link rel="stylesheet" href="<?= asset('css/panel.css') ?>">
<script>(function(){try{var m=localStorage.getItem('mood');if(m==='day'||m==='night')document.documentElement.setAttribute('data-mood',m);}catch(e){}})();</script>
</head>
<body>
<a class="skip" href="#main">Μετάβαση στο περιεχόμενο</a>
<div class="pl">
  <nav class="pl-side" id="pl-side" aria-label="<?= e($areaName) ?>">
    <a class="logo" href="/"><span class="dot" aria-hidden="true"></span><span><?= e(site_name()) ?></span></a>
    <div class="grp"><?= e($areaName) ?></div>
    <?php if ($area === 'business' && ($b = my_business())): ?><div class="small muted" style="padding:0 12px 8px"><?= e($b['name']) ?><?= $b['status'] !== 'active' ? ' · <span class="badge badge-' . e($b['status']) . '">' . e(STATUS_OPTIONS[$b['status']] ?? $b['status']) . '</span>' : '' ?></div><?php endif ?>
    <?php foreach ($menus[$area] as $m): if (is_string($m)): ?><div class="grp"><?= e($m) ?></div><?php continue; endif;
      $cur = $m[1] === $path || ($m[1] !== '/' . $area && str_starts_with($path, $m[1] . '/')) || ($m[1] !== '/' . $area && $path === $m[1]); ?>
      <a href="<?= e($m[1]) ?>" <?= $cur ? 'aria-current="page"' : '' ?>><?= e($m[0]) ?><?= !empty($m[2]) ? '<span class="cnt">' . (int)$m[2] . '</span>' : '' ?></a>
    <?php endforeach ?>
    <div class="grp">Λογαριασμός</div>
    <span class="small muted" style="padding:0 12px"><?= e($u['name'] ?? '') ?></span>
    <button class="icon-btn" type="button" data-mood-toggle style="margin:6px 12px;justify-self:start;width:max-content">Ημέρα / Νύχτα</button>
    <form method="post" action="/exodos" style="padding:0 12px"><?= csrf_field() ?><button class="btn ghost sm" type="submit">Αποσύνδεση</button></form>
  </nav>
  <main class="pl-main" id="main">
    <div class="inner">
      <div class="pl-top">
        <div class="row"><button class="icon-btn pl-menu" type="button" data-side-toggle aria-controls="pl-side" aria-label="Μενού">☰</button><h1 class="disp"><?= e($title ?? '') ?></h1></div>
        <?php if (!empty($actions)) echo $actions; ?>
      </div>
      <?php foreach (take_flash() as $f): ?><div class="alert <?= e($f['type']) ?>" style="margin-bottom:12px" role="status"><?= e($f['msg']) ?></div><?php endforeach ?>
      <?= $content ?>
    </div>
  </main>
</div>
<div class="toast" role="status" aria-live="polite" data-toast></div>
<script>window.APP={csrf:<?= json_encode(csrf_token()) ?>,ga:'',panel:true};</script>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?><script src="<?= asset($s) ?>" defer></script><?php endforeach ?>
</body>
</html>
